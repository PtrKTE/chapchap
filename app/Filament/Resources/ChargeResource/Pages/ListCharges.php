<?php

declare(strict_types=1);

namespace App\Filament\Resources\ChargeResource\Pages;

use App\Exports\ChargesExport;
use App\Filament\Resources\ChargeResource;
use App\Traits\ExportableParDates;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;

class ListCharges extends ListRecords
{
    use ExportableParDates;

    protected static string $resource = ChargeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nouvelle charge'),
            $this->actionExportExcel('Exporter charges'),
        ];
    }

    protected function telechargerExport(?string $dateDebut, ?string $dateFin)
    {
        return Excel::download(
            new ChargesExport($dateDebut, $dateFin),
            'charges_' . now()->format('Y-m-d') . '.xlsx'
        );
    }
}
