<?php

declare(strict_types=1);

namespace App\Filament\Resources\EauProductionResource\Pages;

use App\Filament\Resources\EauProductionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

/**
 * Page de consultation d'une production d'eau en sachet.
 */
class ViewEauProduction extends ViewRecord
{
    protected static string $resource = EauProductionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->label('Modifier'),
        ];
    }
}
