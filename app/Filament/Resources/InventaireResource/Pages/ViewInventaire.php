<?php

declare(strict_types=1);

namespace App\Filament\Resources\InventaireResource\Pages;

use App\Enums\StatutInventaire;
use App\Filament\Resources\InventaireResource;
use App\Services\StockService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewInventaire extends ViewRecord
{
    protected static string $resource = InventaireResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Modifier — uniquement si en cours
            Actions\EditAction::make()
                ->visible(fn () => $this->record->statut === StatutInventaire::EN_COURS),

            // Marquer comme terminé (comptage fini, pas encore validé)
            Actions\Action::make('terminer')
                ->label('Comptage terminé')
                ->icon('heroicon-o-clipboard-document-check')
                ->color('info')
                ->requiresConfirmation()
                ->modalDescription('Marquer le comptage comme terminé ? L\'inventaire sera prêt pour validation.')
                ->visible(fn () => $this->record->statut === StatutInventaire::EN_COURS)
                ->action(function () {
                    $this->record->update(['statut' => StatutInventaire::TERMINE->value]);
                    Notification::make()->title('Comptage terminé')->info()->send();
                    $this->refreshFormData(['statut']);
                }),

            // Valider → applique les ajustements de stock
            Actions\Action::make('valider')
                ->label('Valider l\'inventaire')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Valider l\'inventaire ?')
                ->modalDescription('Les écarts seront ajustés dans le stock (mouvements ajustement+ et ajustement-). Irréversible.')
                ->modalSubmitActionLabel('Oui, valider et ajuster le stock')
                ->visible(fn () => in_array($this->record->statut, [StatutInventaire::EN_COURS, StatutInventaire::TERMINE]))
                ->action(fn () => $this->validerInventaire()),
        ];
    }

    /**
     * Valide l'inventaire et génère les mouvements d'ajustement.
     */
    protected function validerInventaire(): void
    {
        $inventaire = $this->record;
        $lignes     = $inventaire->lignes;

        if ($lignes->isEmpty()) {
            Notification::make()->title('Erreur')->body('Aucun produit dans l\'inventaire.')->danger()->send();
            return;
        }

        // Vérifier les justifications manquantes
        $sansJustification = $lignes->filter(fn ($l) => abs((float) $l->ecart) > 0.001 && empty($l->justification));
        if ($sansJustification->isNotEmpty()) {
            $noms = $sansJustification->map(fn ($l) => $l->produit->nom)->join(', ');
            Notification::make()
                ->title('Justification manquante')
                ->body("Écarts sans justification : {$noms}")
                ->danger()->send();
            return;
        }

        try {
            $stockService   = app(StockService::class);
            $nbAjustements  = 0;

            foreach ($lignes as $ligne) {
                $ecart = (float) $ligne->ecart;
                if (abs($ecart) < 0.001) continue;

                $stockService->ajustementInventaire(
                    produitId:     $ligne->produit_id,
                    emplacementId: $inventaire->emplacement_id,
                    ecart:         $ecart,
                    motif:         "Inventaire {$inventaire->reference} : {$ligne->justification}",
                );
                $nbAjustements++;
            }

            $inventaire->update([
                'statut'    => StatutInventaire::VALIDE->value,
                'valide_par' => auth()->id(),
            ]);

            Notification::make()
                ->title('Inventaire validé ✅')
                ->body("{$nbAjustements} ajustement(s) de stock effectué(s).")
                ->success()->send();

            $this->refreshFormData(['statut']);

        } catch (\RuntimeException $e) {
            Notification::make()->title('Erreur')->body($e->getMessage())->danger()->send();
        }
    }
}
