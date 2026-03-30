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
use Filament\Resources\Pages\EditRecord;

class EditProduction extends EditRecord
{
    protected static string $resource = ProductionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Bouton "Valider la production"
            // Visible uniquement si le statut est "en_cours"
            Actions\Action::make('valider')
                ->label('Valider la production')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Valider cette production ?')
                ->modalDescription('La validation va générer les entrées en stock pour chaque produit. Cette action est irréversible.')
                ->modalSubmitActionLabel('Oui, valider')
                ->visible(fn() => $this->record->statut === StatutProduction::EN_COURS)
                ->action(function () {
                    $this->validerProduction();
                }),

            // Bouton "Annuler"
            Actions\Action::make('annuler')
                ->label('Annuler la production')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn() => $this->record->statut === StatutProduction::EN_COURS)
                ->action(function () {
                    $this->record->update([
                        'statut' => StatutProduction::ANNULEE->value,
                    ]);

                    Notification::make()
                        ->title('Production annulée')
                        ->warning()
                        ->send();

                    $this->refreshFormData(['statut']);
                }),

            Actions\DeleteAction::make()
                ->visible(fn() => $this->record->statut === StatutProduction::EN_COURS),
        ];
    }

    /**
     * Valide la production :
     * 1. Vérifie qu'il y a des lignes
     * 2. Pour chaque ligne → entrée stock via StockService
     * 3. Calcule le coût unitaire de chaque ligne et le rendement global
     * 4. Marque comme validée
     */
    protected function validerProduction(): void
    {
        $production = $this->record;
        $lot = $production->lot;
        $lignes = $production->lignes;

        // Vérification : il faut au moins une ligne
        if ($lignes->isEmpty()) {
            Notification::make()
                ->title('Impossible de valider')
                ->body('Ajoutez au moins une ligne de produit avant de valider.')
                ->danger()
                ->send();

            return;
        }

        // Vérification : le nombre de poulets traités ne dépasse pas le lot
        if ($production->nb_poulets_traites > $lot->quantite_utilisable) {
            Notification::make()
                ->title('Impossible de valider')
                ->body("Le lot ne contient que {$lot->quantite_utilisable} poulets utilisables.")
                ->danger()
                ->send();

            return;
        }

        $stockService = app(StockService::class);

        // Emplacement principal (site d'abattage = premier emplacement de type "site")
        $emplacement = Emplacement::where('type', 'site')->where('actif', true)->first();
        if (! $emplacement) {
            Notification::make()
                ->title('Erreur de configuration')
                ->body('Aucun emplacement de type "site" trouvé.')
                ->danger()
                ->send();

            return;
        }

        // Coût total des poulets utilisés pour cette production
        $coutTotalEntrant = $production->nb_poulets_traites * (float) $lot->cout_moyen_unitaire;

        // Quantité effective : quantite (kg) si renseignée, sinon quantite_unite (pièces)
        $quantiteEffective = fn($ligne): float => (float) $ligne->quantite > 0
            ? (float) $ligne->quantite
            : (float) ($ligne->quantite_unite ?? 0);

        // ─── Répartition du coût au PRORATA DE LA VALEUR MARCHANDE ───────────────
        // Chaque produit absorbe une part du coût proportionnelle à ce qu'il vaut à la vente.
        // Ex : une escalope (3 800 FCFA/kg) absorbe plus de coût qu'un intestin (500 FCFA/kg).
        // Formule : coût_unitaire_ligne = (valorisation_ligne / total_valorisation) × coût_total / quantité
        // ─────────────────────────────────────────────────────────────────────────

        // Étape 1 : calculer la valorisation théorique de chaque ligne (qté × prix vente)
        $valorisationsParLigne = $lignes->mapWithKeys(function ($ligne) use ($quantiteEffective) {
            $qte  = $quantiteEffective($ligne);
            $prix = (float) ($ligne->produit->prix_vente_defaut ?? 0);
            return [$ligne->id => $qte * $prix];
        });

        $totalValorisation = (float) $valorisationsParLigne->sum();

        // Fallback : si aucun prix de vente n'est défini, on répartit par quantité égale
        $totalQuantite = $totalValorisation === 0.0
            ? (float) $lignes->sum(fn($l) => $quantiteEffective($l))
            : 0.0;

        $totalValorisationSortie = $totalValorisation;

        // Pour chaque ligne de production : créer l'entrée stock
        foreach ($lignes as $ligne) {
            $qteEff = $quantiteEffective($ligne);

            // Étape 2 : part du coût total absorbée par cette ligne
            if ($totalValorisation > 0) {
                // Répartition par valeur marchande (méthode recommandée)
                $coutAbsorbe      = ($valorisationsParLigne[$ligne->id] / $totalValorisation) * $coutTotalEntrant;
                $coutUnitaireLigne = $qteEff > 0 ? $coutAbsorbe / $qteEff : 0;
            } else {
                // Fallback : répartition égale par quantité
                $coutUnitaireLigne = $totalQuantite > 0 ? $coutTotalEntrant / $totalQuantite : 0;
            }

            // Mettre à jour la ligne avec le coût calculé
            $ligne->update([
                'cout_unitaire_calcule' => round($coutUnitaireLigne, 4),
                'valeur_totale'         => round($qteEff * $coutUnitaireLigne, 2),
            ]);

            // Entrée en stock avec la quantité effective
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
        $poidsObtenus    = (float) $lignes->sum('quantite');
        $poidsEntrant    = (float) ($production->poids_total_entrant ?? 0);
        $rendement       = ($poidsEntrant > 0 && $poidsObtenus > 0)
            ? $poidsObtenus / $poidsEntrant
            : null; // null = non calculable (poids non renseignés)

        // Mise à jour de la production
        $production->update([
            'statut'          => StatutProduction::VALIDEE->value,
            'rendement'       => $rendement !== null ? round($rendement, 4) : null,
            'valide_par'      => auth()->id(),
            'date_validation' => now(),
        ]);

        // Décrémenter la quantité utilisable du lot
        $lot->decrement('quantite_utilisable', $production->nb_poulets_traites);

        // Mettre à jour la date de production du lot si pas encore renseignée
        if (! $lot->date_production) {
            $lot->update(['date_production' => $production->date_production]);
        }

        Notification::make()
            ->title('Production validée !')
            ->body("Stock mis à jour pour {$lignes->count()} produits. Rendement : " . number_format($rendement * 100, 1) . ' %')
            ->success()
            ->send();

        $this->refreshFormData(['statut']);
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Production mise à jour';
    }
}
