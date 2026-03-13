<?php

declare(strict_types=1);

namespace App\Filament\Resources\InventaireResource\Pages;

use App\Enums\StatutInventaire;
use App\Filament\Resources\InventaireResource;
use App\Models\Inventaire;
use Filament\Resources\Pages\CreateRecord;

/**
 * Page de création d'un inventaire.
 *
 * Génère la référence INV-YYYYMMDD-XXX automatiquement
 * et met le statut à "en_cours".
 */
class CreateInventaire extends CreateRecord
{
    protected static string $resource = InventaireResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Référence auto : INV-YYYYMMDD-XXX
        $date = now()->format('Ymd');
        $count = Inventaire::whereDate('date_inventaire', today())->count() + 1;
        $data['reference'] = sprintf('INV-%s-%03d', $date, $count);

        $data['created_by'] = auth()->id();
        $data['statut'] = StatutInventaire::EN_COURS->value;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
