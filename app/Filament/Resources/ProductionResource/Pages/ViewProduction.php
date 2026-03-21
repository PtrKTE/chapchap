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

        $stockService            = app(StockService::class);
        $coutTotalEntrant        = $production->nb_poulets_traites * (float) $lot->cout_moyen_unitaire;
        $totalQuantiteLignes     = (float) $lignes->sum('quantite');
        $totalValorisationSortie = 0;

        // Coût unitaire par kg : réparti équitablement sur le poids total produit
        $coutParKg = $totalQuantiteLignes > 0 ? $coutTotalEntrant / $totalQuantiteLignes : 0;

        foreach ($lignes as $ligne) {
            $valeurLigne = round((float) $ligne->quantite * $coutParKg, 2);

            $ligne->update([
                'cout_unitaire_calcule' => round($coutParKg, 4),
                'valeur_totale'         => $valeurLigne,
            ]);

            $stockService->entree(
                produitId:     $ligne->produit_id,
                emplacementId: $emplacement->id,
                quantite:      (float) $ligne->quantite,
                coutUnitaire:  $coutParKg,
                typeMouvement: TypeMouvement::ENTREE_PRODUCTION,
                lotId:         $lot->id,
                productionId:  $production->id,
                motif:         "Production {$production->reference} — Lot {$lot->reference}",
            );

            $totalValorisationSortie += (float) $ligne->quantite * (float) ($ligne->produit->prix_vente_defaut ?? 0);
        }

        // Rendement = valorisation vente / coût matière
        $rendement = $coutTotalEntrant > 0 ? $totalValorisationSortie / $coutTotalEntrant : 0;

        $production->update([
            'statut'          => StatutProduction::VALIDEE->value,
            'rendement'       => round($rendement, 4),
            'valide_par'      => auth()->id(),
            'date_validation' => now(),
        ]);

        // Décrémenter les poulets disponibles du lot
        $lot->decrement('quantite_utilisable', $production->nb_poulets_traites);

        if (! $lot->date_production) {
            $lot->update(['date_production' => $production->date_production]);
        }

        Notification::make()
            ->title('Production validée !')
            ->body("Stock mis à jour pour {$lignes->count()} produits. Rendement : " . number_format($rendement * 100, 1) . ' %')
            ->success()->send();

        $this->refreshFormData(['statut']);
    }
}
