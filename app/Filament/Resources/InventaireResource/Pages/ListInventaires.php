<?php

declare(strict_types=1);

namespace App\Filament\Resources\InventaireResource\Pages;

use App\Filament\Resources\InventaireResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListInventaires extends ListRecords
{
    protected static string $resource = InventaireResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nouvel inventaire'),
        ];
    }
}
