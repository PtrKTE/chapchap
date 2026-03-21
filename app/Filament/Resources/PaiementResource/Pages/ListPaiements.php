<?php

declare(strict_types=1);

namespace App\Filament\Resources\PaiementResource\Pages;

use App\Exports\PaiementsExport;
use App\Filament\Resources\PaiementResource;
use App\Traits\ExportableParDates;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;

class ListPaiements extends ListRecords
{
    use ExportableParDates;

    protected static string $resource = PaiementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->actionExportExcel('Exporter paiements'),
        ];
    }

    protected function telechargerExport(?string $dateDebut, ?string $dateFin)
    {
        return Excel::download(
            new PaiementsExport($dateDebut, $dateFin),
            'paiements_' . now()->format('Y-m-d') . '.xlsx'
        );
    }
}
