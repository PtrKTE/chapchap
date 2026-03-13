<?php

declare(strict_types=1);

namespace App\Filament\Resources\CaisseResource\Pages;

use App\Exports\CaissesExport;
use App\Filament\Resources\CaisseResource;
use App\Traits\ExportableParDates;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;

class ListCaisses extends ListRecords
{
    use ExportableParDates;

    protected static string $resource = CaisseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Ouvrir une caisse'),
            $this->actionExportExcel('Exporter caisses'),
        ];
    }

    protected function telechargerExport(?string $dateDebut, ?string $dateFin)
    {
        return Excel::download(
            new CaissesExport($dateDebut, $dateFin),
            'caisses_' . now()->format('Y-m-d') . '.xlsx'
        );
    }
}
