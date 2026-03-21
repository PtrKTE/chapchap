<?php

declare(strict_types=1);

namespace App\Filament\Resources\TransfertResource\Pages;

use App\Enums\StatutTransfert;
use App\Filament\Resources\TransfertResource;
use App\Services\StockService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewTransfert extends ViewRecord
{
    protected static string $resource = TransfertResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Modifier — uniquement si en cours
            Actions\EditAction::make()
                ->visible(fn () => $this->record->statut === StatutTransfert::EN_COURS),

            // Réceptionner → génère les mouvements de stock
            Actions\Action::make('receptionner')
                ->label('Réceptionner le transfert')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Confirmer la réception ?')
                ->modalDescription('Les mouvements de stock seront générés automatiquement pour chaque produit. Les quantités reçues non renseignées seront égales aux quantités envoyées.')
                ->modalSubmitActionLabel('Oui, réceptionner')
                ->visible(fn () => $this->record->statut === StatutTransfert::EN_COURS)
                ->action(fn () => $this->receptionnerTransfert()),

            // Annuler — uniquement si en cours
            Actions\Action::make('annuler')
                ->label('Annuler')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Annuler ce transfert ?')
                ->modalDescription('Le transfert sera marqué annulé. Aucun mouvement de stock ne sera créé.')
                ->visible(fn () => $this->record->statut === StatutTransfert::EN_COURS)
                ->action(function () {
                    $this->record->update(['statut' => StatutTransfert::ANNULE->value]);
                    Notification::make()->title('Transfert annulé')->warning()->send();
                    $this->refreshFormData(['statut']);
                }),
        ];
    }

    /**
     * Réceptionner le transfert : génère les mouvements stock via StockService.
     */
    protected function receptionnerTransfert(): void
    {
        $transfert = $this->record;
        $lignes    = $transfert->lignes;

        if ($lignes->isEmpty()) {
            Notification::make()
                ->title('Erreur')
                ->body('Aucun produit dans le transfert.')
                ->danger()->send();
            return;
        }

        // Si quantité reçue non renseignée → on la met égale à l'envoyée
        foreach ($lignes->filter(fn ($l) => $l->quantite_recue === null) as $ligne) {
            $ligne->update(['quantite_recue' => $ligne->quantite_envoyee, 'ecart' => 0]);
        }
        $lignes = $transfert->lignes()->get();

        try {
            $stockService = app(StockService::class);

            foreach ($lignes as $ligne) {
                $quantiteRecue = (float) ($ligne->quantite_recue ?? $ligne->quantite_envoyee);
                $ligne->update(['ecart' => round((float) $ligne->quantite_envoyee - $quantiteRecue, 3)]);

                $stockService->transfert(
                    produitId:          $ligne->produit_id,
                    emplacementSourceId: $transfert->emplacement_source_id,
                    emplacementDestId:   $transfert->emplacement_dest_id,
                    quantiteEnvoyee:    (float) $ligne->quantite_envoyee,
                    quantiteRecue:      $quantiteRecue,
                    transfertId:        $transfert->id,
                );
            }

            $transfert->update([
                'statut'        => StatutTransfert::RECU->value,
                'recu_par'      => auth()->id(),
                'date_reception' => now(),
            ]);

            Notification::make()
                ->title('Transfert réceptionné ✅')
                ->body("Stock mis à jour pour {$lignes->count()} produit(s).")
                ->success()->send();

            $this->refreshFormData(['statut']);

        } catch (\RuntimeException $e) {
            Notification::make()->title('Erreur stock')->body($e->getMessage())->danger()->send();
        }
    }
}
