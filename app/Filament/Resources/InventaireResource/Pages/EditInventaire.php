<?php

declare(strict_types=1);

namespace App\Filament\Resources\InventaireResource\Pages;

use App\Enums\StatutInventaire;
use App\Filament\Resources\InventaireResource;
use App\Services\StockService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

/**
 * Page d'édition d'un inventaire.
 *
 * Contient 2 actions métier :
 * - "Valider l'inventaire" : génère les ajustements de stock pour chaque écart
 * - "Annuler" : passe le statut à terminé sans ajuster le stock
 */
class EditInventaire extends EditRecord
{
    protected static string $resource = InventaireResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Bouton "Valider l'inventaire"
            // Génère les mouvements d'ajustement pour chaque écart détecté
            Actions\Action::make('valider')
                ->label('Valider l\'inventaire')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Valider l\'inventaire')
                ->modalDescription('Les écarts détectés seront ajustés dans le stock. Un mouvement de type ajustement (+/-) sera créé pour chaque produit avec un écart. Continuer ?')
                ->visible(fn() => in_array($this->record->statut, [StatutInventaire::EN_COURS, StatutInventaire::TERMINE]))
                ->action(fn() => $this->validerInventaire()),

            // Bouton "Marquer comme terminé" (comptage fini, mais pas encore validé)
            Actions\Action::make('terminer')
                ->label('Comptage terminé')
                ->icon('heroicon-o-clipboard-document-check')
                ->color('info')
                ->requiresConfirmation()
                ->visible(fn() => $this->record->statut === StatutInventaire::EN_COURS)
                ->action(function () {
                    $this->record->update(['statut' => StatutInventaire::TERMINE->value]);
                    Notification::make()
                        ->title('Comptage terminé')
                        ->body('L\'inventaire est prêt pour validation.')
                        ->info()
                        ->send();
                }),

            Actions\DeleteAction::make()
                ->visible(fn() => $this->record->statut === StatutInventaire::EN_COURS),
        ];
    }

    /**
     * Valider l'inventaire et ajuster le stock.
     *
     * Pour chaque ligne avec un écart :
     * - Si physique > théorique → ajustement_plus (on a trouvé plus)
     * - Si physique < théorique → ajustement_moins (il manque du stock)
     * - Si écart = 0 → pas de mouvement
     *
     * La justification est obligatoire pour les écarts.
     */
    protected function validerInventaire(): void
    {
        $inventaire = $this->record;
        $lignes = $inventaire->lignes;

        if ($lignes->isEmpty()) {
            Notification::make()
                ->title('Erreur')
                ->body('Aucun produit dans l\'inventaire. Ajoutez au moins un produit.')
                ->danger()
                ->send();

            return;
        }

        // Vérifier que les écarts ont une justification
        $ecartsSansJustification = $lignes->filter(function ($l) {
            $ecart = (float) $l->ecart;

            return abs($ecart) > 0.001 && empty($l->justification);
        });

        if ($ecartsSansJustification->isNotEmpty()) {
            $produits = $ecartsSansJustification->map(fn($l) => $l->produit->nom)->join(', ');
            Notification::make()
                ->title('Justification manquante')
                ->body("Les produits suivants ont un écart sans justification : {$produits}")
                ->danger()
                ->send();

            return;
        }

        try {
            $stockService = app(StockService::class);
            $nbAjustements = 0;

            foreach ($lignes as $ligne) {
                $ecart = (float) $ligne->ecart;

                if (abs($ecart) < 0.001) {
                    continue; // Pas d'écart significatif
                }

                $stockService->ajustementInventaire(
                    produitId: $ligne->produit_id,
                    emplacementId: $inventaire->emplacement_id,
                    ecart: $ecart,
                    motif: "Inventaire {$inventaire->reference} : {$ligne->justification}",
                );

                $nbAjustements++;
            }

            // Mettre à jour le statut de l'inventaire
            $inventaire->update([
                'statut' => StatutInventaire::VALIDE->value,
                'valide_par' => auth()->id(),
            ]);

            Notification::make()
                ->title('Inventaire validé')
                ->body("{$nbAjustements} ajustement(s) de stock effectué(s) pour l'inventaire {$inventaire->reference}.")
                ->success()
                ->send();

        } catch (\RuntimeException $e) {
            Notification::make()
                ->title('Erreur lors de la validation')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
