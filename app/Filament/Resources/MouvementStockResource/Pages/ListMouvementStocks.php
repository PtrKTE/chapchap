<?php

declare(strict_types=1);

namespace App\Filament\Resources\MouvementStockResource\Pages;

use App\Filament\Resources\MouvementStockResource;
use Filament\Resources\Pages\ListRecords;

class ListMouvementStocks extends ListRecords
{
    protected static string $resource = MouvementStockResource::class;

    protected function getHeaderActions(): array
    {
        // Pas de bouton "Créer" — les mouvements sont automatiques
        return [];
    }
}
