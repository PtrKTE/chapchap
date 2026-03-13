<?php

declare(strict_types=1);

namespace App\Filament\Resources\ClientResource\Pages;

use App\Filament\Resources\ClientResource;
use App\Models\Client;
use Filament\Resources\Pages\CreateRecord;

class CreateClient extends CreateRecord
{
    protected static string $resource = ClientResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Génération automatique du code client CLI-XXXX
        $lastClient = Client::orderByDesc('id')->first();
        $nextNumber = $lastClient ? $lastClient->id + 1 : 1;
        $data['code'] = 'CLI-' . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
        $data['date_enregistrement'] = now()->toDateString();

        return $data;
    }
}
