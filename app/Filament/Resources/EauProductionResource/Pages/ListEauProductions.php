<?php

declare(strict_types=1);

namespace App\Filament\Resources\EauProductionResource\Pages;

use App\Filament\Resources\EauProductionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEauProductions extends ListRecords
{
    protected static string $resource = EauProductionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nouvelle production eau'),
        ];
    }
}
