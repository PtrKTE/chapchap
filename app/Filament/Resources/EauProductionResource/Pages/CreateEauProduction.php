<?php

declare(strict_types=1);

namespace App\Filament\Resources\EauProductionResource\Pages;

use App\Filament\Resources\EauProductionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEauProduction extends CreateRecord
{
    protected static string $resource = EauProductionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $data;
    }
}
