<?php

declare(strict_types=1);

namespace App\Filament\Resources\TransfertResource\Pages;

use App\Filament\Resources\TransfertResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTransferts extends ListRecords
{
    protected static string $resource = TransfertResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nouveau transfert'),
        ];
    }
}
