<?php

declare(strict_types=1);

namespace App\Filament\Resources\CategorieChargeResource\Pages;

use App\Filament\Resources\CategorieChargeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCategorieCharges extends ListRecords
{
    protected static string $resource = CategorieChargeResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
