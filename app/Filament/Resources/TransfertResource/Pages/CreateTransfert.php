<?php

declare(strict_types=1);

namespace App\Filament\Resources\TransfertResource\Pages;

use App\Enums\StatutTransfert;
use App\Filament\Resources\TransfertResource;
use App\Models\Transfert;
use Filament\Resources\Pages\CreateRecord;

/**
 * Page de création d'un transfert.
 *
 * Génère automatiquement la référence TRF-YYYYMMDD-XXX
 * et met le statut à "en_cours".
 */
class CreateTransfert extends CreateRecord
{
    protected static string $resource = TransfertResource::class;

    /**
     * Enrichit les données avant la sauvegarde en BDD.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Génération automatique de la référence : TRF-YYYYMMDD-XXX
        $date = now()->format('Ymd');
        $count = Transfert::whereDate('date_transfert', today())->count() + 1;
        $data['reference'] = sprintf('TRF-%s-%03d', $date, $count);

        // Le créateur est l'utilisateur connecté
        $data['created_by'] = auth()->id();

        // Le statut initial est toujours "en_cours"
        $data['statut'] = StatutTransfert::EN_COURS->value;

        return $data;
    }

    /**
     * Après la création, on redirige vers la page d'édition
     * pour permettre la réception.
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
