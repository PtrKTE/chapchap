<?php

declare(strict_types=1);

namespace App\Filament\Resources\ChargeResource\Pages;

use App\Filament\Resources\ChargeResource;
use Filament\Resources\Pages\CreateRecord;

/**
 * Page de création d'une charge.
 * Enregistre automatiquement l'utilisateur qui saisit la charge.
 */
class CreateCharge extends CreateRecord
{
    protected static string $resource = ChargeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $data;
    }
}
