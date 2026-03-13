<?php

declare(strict_types=1);

namespace App\Filament\Resources\VenteResource\Pages;

use App\Exports\VentesExport;
use App\Filament\Resources\VenteResource;
use App\Traits\ExportableParDates;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;

class ListVentes extends ListRecords
{
    use ExportableParDates;

    protected static string $resource = VenteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nouvelle vente'),
            $this->actionExportExcel('Exporter ventes'),
        ];
    }

    protected function telechargerExport(?string $dateDebut, ?string $dateFin)
    {
        return Excel::download(
            new VentesExport($dateDebut, $dateFin),
            'ventes_' . now()->format('Y-m-d') . '.xlsx'
        );
    }
}
