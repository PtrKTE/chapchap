<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductionResource\Pages;

use App\Enums\StatutProduction;
use App\Enums\TypeMouvement;
use App\Filament\Resources\ProductionResource;
use App\Models\Emplacement;
use App\Services\StockService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewProduction extends ViewRecord
{
    protected static string $resource = ProductionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Modifier — uniquement si en cours
            Actions\EditAction::make()
                ->visible(fn () => $this->record->statut === StatutProduction::EN_COURS),

            // Valider → génère les entrées stock
            Actions\Action::make('valider')
                ->label('Valider la production')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Valider cette production ?')
                ->modalDescription('La validation génère les entrées en stock pour chaque produit obtenu. Cette action est irréversible.')
                ->modalSubmitActionLabel('Oui, valider et générer le stock')
                ->visible(fn () => $this->record->statut === StatutProduction::EN_COURS)
                ->action(fn () => $this->validerProduction()),

            // Annuler — uniquement si en cours
            Actions\Action::make('annuler')
                ->label('Annuler')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Annuler cette production ?')
                ->modalDescription('La production sera marquée annulée. Aucun stock ne sera généré.')
                ->visible(fn () => $this->record->statut === StatutProduction::EN_COURS)
                ->action(function () {
                    $this->record->update(['statut' => StatutProduction::ANNULEE->value]);
                    Notification::make()->title('Production annulée')->warning()->send();
                    $this->refreshFormData(['statut']);
                }),
        ];
    }

    /**
     * Valide la production et génère les entrées en stock.
     * Le coût unitaire de chaque produit est réparti au prorata des quantités.
     */
    protected function validerProduction(): void
    {
        $production = $this->record;
        $lot        = $production->lot;
        $lignes     = $production->lignes;

        if ($lignes->isEmpty()) {
            Notification::make()
                ->title('Impossible de valider')
                ->body('Ajoutez au moins une ligne de produit avant de valider.')
                ->danger()->send();
            return;
        }

        if ($production->nb_poulets_traites > $lot->quantite_utilisable) {
            Notification::make()
                ->title('Impossible de valider')
                ->body("Le lot ne contient que {$lot->quantite_utilisable} poulets utilisables.")
                ->danger()->send();
            return;
        }

        $emplacement = Emplacement::where('type', 'site')->where('actif', true)->first();
        if (! $emplacement) {
            Notification::make()
                ->title('Erreur de configuration')
                ->body('Aucun emplacement de type "site" trouvé. Vérifiez les emplacements.')
                ->danger()->send();
            return;
        }

        $stockService     = app(StockService::class);
        $coutTotalEntrant = $production->nb_poulets_traites * (float) $lot->cout_moyen_unitaire;

        // Quantité effective : quantite (kg) si renseignée, sinon quantite_unite (pièces)
        $quantiteEffective = fn($ligne): float => (float) $ligne->quantite > 0
            ? (float) $ligne->quantite
            : (float) ($ligne->quantite_unite ?? 0);

        // ─── Répartition du coût au PRORATA DE LA VALEUR MARCHANDE ───────────────
        // Chaque produit absorbe une part du coût proportionnelle à ce qu'il vaut à la vente.
        // Ex : une escalope (3 800 FCFA/kg) absorbe plus de coût qu'un intestin (500 FCFA/kg).
        // ─────────────────────────────────────────────────────────────────────────

        // Étape 1 : valorisation théorique de chaque ligne (qté × prix vente)
        $valorisationsParLigne = $lignes->mapWithKeys(function ($ligne) use ($quantiteEffective) {
            $qte  = $quantiteEffective($ligne);
            $prix = (float) ($ligne->produit->prix_vente_defaut ?? 0);
            return [$ligne->id => $qte * $prix];
        });

        $totalValorisation = (float) $valorisationsParLigne->sum();

        // Fallback : répartition par quantité égale si aucun prix défini
        $totalQuantite = $totalValorisation === 0.0
            ? (float) $lignes->sum(fn($l) => $quantiteEffective($l))
            : 0.0;

        $totalValorisationSortie = $totalValorisation;

        foreach ($lignes as $ligne) {
            $qteEff = $quantiteEffective($ligne);

            // Étape 2 : coût unitaire proportionnel à la valeur marchande du produit
            if ($totalValorisation > 0) {
                $coutAbsorbe       = ($valorisationsParLigne[$ligne->id] / $totalValorisation) * $coutTotalEntrant;
                $coutUnitaireLigne = $qteEff > 0 ? $coutAbsorbe / $qteEff : 0;
            } else {
                $coutUnitaireLigne = $totalQuantite > 0 ? $coutTotalEntrant / $totalQuantite : 0;
            }

            $ligne->update([
                'cout_unitaire_calcule' => round($coutUnitaireLigne, 4),
                'valeur_totale'         => round($qteEff * $coutUnitaireLigne, 2),
            ]);

            if ($qteEff > 0) {
                $stockService->entree(
                    produitId:     $ligne->produit_id,
                    emplacementId: $emplacement->id,
                    quantite:      $qteEff,
                    coutUnitaire:  $coutUnitaireLigne,
                    typeMouvement: TypeMouvement::ENTREE_PRODUCTION,
                    lotId:         $lot->id,
                    productionId:  $production->id,
                    motif:         "Production {$production->reference} — Lot {$lot->reference}",
                );
            }
        }

        // Rendement POIDS = poids total des produits obtenus (kg) / poids total entrant (kg)
        // C'est la métrique standard en abattage avicole.
        // Ex : 75% = 75% du poids des poulets vivants est récupéré en produits utilisables.
        // On ne prend que les lignes avec une quantité en kg (quantite > 0).
        $poidsObtenus = (float) $lignes->sum('quantite');
        $poidsEntrant = (float) ($production->poids_total_entrant ?? 0);
        $rendement    = ($poidsEntrant > 0 && $poidsObtenus > 0)
            ? round($poidsObtenus / $poidsEntrant, 4)
            : null;

        $production->update([
            'statut'          => StatutProduction::VALIDEE->value,
            'rendement'       => $rendement,
            'valide_par'      => auth()->id(),
            'date_validation' => now(),
        ]);

        // Décrémenter les poulets disponibles du lot
        $lot->decrement('quantite_utilisable', $production->nb_poulets_traites);

        if (! $lot->date_production) {
            $lot->update(['date_production' => $production->date_production]);
        }

        $rendementTexte = $rendement !== null
            ? number_format($rendement * 100, 1) . ' %'
            : 'non calculable (poids entrant non renseigné)';

        Notification::make()
            ->title('Production validée !')
            ->body("Stock mis à jour pour {$lignes->count()} produits. Rendement poids : {$rendementTexte}")
            ->success()->send();

        $this->refreshFormData(['statut']);
    }
}
