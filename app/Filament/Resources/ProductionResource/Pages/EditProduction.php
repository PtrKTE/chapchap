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
        $totalValorisationSortie = 0;

        // Pour chaque ligne de production : créer l'entrée stock
        foreach ($lignes as $ligne) {
            // Le coût unitaire de chaque produit est proportionnel au poids
            // On répartit le coût total au prorata des quantités
            $totalQuantiteLignes = $lignes->sum('quantite');
            $coutUnitaireLigne = $totalQuantiteLignes > 0
                ? ($coutTotalEntrant * (float) $ligne->quantite) / $totalQuantiteLignes / (float) $ligne->quantite
                : 0;

            // Mettre à jour la ligne avec le coût calculé
            $ligne->update([
                'cout_unitaire_calcule' => round($coutUnitaireLigne, 4),
                'valeur_totale' => round((float) $ligne->quantite * $coutUnitaireLigne, 2),
            ]);

            // Entrée en stock
            $stockService->entree(
                produitId: $ligne->produit_id,
                emplacementId: $emplacement->id,
                quantite: (float) $ligne->quantite,
                coutUnitaire: $coutUnitaireLigne,
                typeMouvement: TypeMouvement::ENTREE_PRODUCTION,
                lotId: $lot->id,
                productionId: $production->id,
                motif: "Production {$production->reference} — Lot {$lot->reference}",
            );

            // Valorisation pour le calcul du rendement
            $prixVente = $ligne->produit->prix_vente_defaut ?? 0;
            $totalValorisationSortie += (float) $ligne->quantite * (float) $prixVente;
        }

        // Calcul du rendement : valorisation sorties / coût entrées
        $rendement = $coutTotalEntrant > 0
            ? $totalValorisationSortie / $coutTotalEntrant
            : 0;

        // Mise à jour de la production
        $production->update([
            'statut' => StatutProduction::VALIDEE->value,
            'rendement' => round($rendement, 4),
            'valide_par' => auth()->id(),
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
