<?php

declare(strict_types=1);

namespace App\Filament\Resources\TransfertResource\Pages;

use App\Enums\StatutTransfert;
use App\Filament\Resources\TransfertResource;
use App\Services\StockService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

/**
 * Page d'édition d'un transfert.
 *
 * Contient 2 actions métier importantes :
 * - "Réceptionner" : confirme la réception et met à jour le stock
 * - "Annuler" : annule le transfert (possible seulement si pas encore réceptionné)
 */
class EditTransfert extends EditRecord
{
    protected static string $resource = TransfertResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Bouton "Réceptionner le transfert"
            // Visible uniquement quand le statut est "en_cours"
            Actions\Action::make('receptionner')
                ->label('Réceptionner le transfert')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Confirmer la réception')
                ->modalDescription('Avez-vous vérifié les quantités reçues pour chaque produit ? Les mouvements de stock seront générés automatiquement.')
                ->visible(fn() => $this->record->statut === StatutTransfert::EN_COURS)
                ->action(fn() => $this->receptionnerTransfert()),

            // Bouton "Annuler le transfert"
            Actions\Action::make('annuler')
                ->label('Annuler')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Annuler le transfert')
                ->modalDescription('Êtes-vous sûr ? Le transfert sera marqué comme annulé. Aucun mouvement de stock ne sera créé.')
                ->visible(fn() => $this->record->statut === StatutTransfert::EN_COURS)
                ->action(fn() => $this->annulerTransfert()),

            // Supprimer : uniquement si en_cours
            Actions\DeleteAction::make()
                ->visible(fn() => $this->record->statut === StatutTransfert::EN_COURS),
        ];
    }

    /**
     * Réceptionner le transfert : crée les mouvements de stock.
     *
     * Pour chaque ligne du transfert :
     * 1. Sortie sur l'emplacement source (quantité envoyée)
     * 2. Entrée sur l'emplacement destination (quantité reçue)
     * 3. Si écart : mouvement de perte enregistré
     */
    protected function receptionnerTransfert(): void
    {
        $transfert = $this->record;
        $lignes = $transfert->lignes;

        // Vérifier qu'il y a des lignes
        if ($lignes->isEmpty()) {
            Notification::make()
                ->title('Erreur')
                ->body('Aucun produit dans le transfert. Ajoutez au moins un produit.')
                ->danger()
                ->send();

            return;
        }

        // Vérifier que les quantités reçues sont renseignées
        $lignesSansReception = $lignes->filter(fn($l) => $l->quantite_recue === null);
        if ($lignesSansReception->isNotEmpty()) {
            // Si pas de quantité reçue, on met par défaut = quantité envoyée
            foreach ($lignesSansReception as $ligne) {
                $ligne->update([
                    'quantite_recue' => $ligne->quantite_envoyee,
                    'ecart' => 0,
                ]);
            }
            $lignes = $transfert->lignes()->get(); // Recharger
        }

        try {
            $stockService = app(StockService::class);

            foreach ($lignes as $ligne) {
                $quantiteRecue = (float) ($ligne->quantite_recue ?? $ligne->quantite_envoyee);

                // Calculer l'écart
                $ecart = (float) $ligne->quantite_envoyee - $quantiteRecue;
                $ligne->update(['ecart' => round($ecart, 3)]);

                // Effectuer le transfert dans le StockService
                $stockService->transfert(
                    produitId: $ligne->produit_id,
                    emplacementSourceId: $transfert->emplacement_source_id,
                    emplacementDestId: $transfert->emplacement_dest_id,
                    quantiteEnvoyee: (float) $ligne->quantite_envoyee,
                    quantiteRecue: $quantiteRecue,
                    transfertId: $transfert->id,
                );
            }

            // Mettre à jour le statut du transfert
            $transfert->update([
                'statut' => StatutTransfert::RECU->value,
                'recu_par' => auth()->id(),
                'date_reception' => now(),
            ]);

            Notification::make()
                ->title('Transfert réceptionné')
                ->body("Le transfert {$transfert->reference} a été réceptionné. Les stocks ont été mis à jour.")
                ->success()
                ->send();

        } catch (\RuntimeException $e) {
            Notification::make()
                ->title('Erreur de stock')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Annuler le transfert.
     */
    protected function annulerTransfert(): void
    {
        $this->record->update([
            'statut' => StatutTransfert::ANNULE->value,
        ]);

        Notification::make()
            ->title('Transfert annulé')
            ->body("Le transfert {$this->record->reference} a été annulé.")
            ->warning()
            ->send();
    }
}
