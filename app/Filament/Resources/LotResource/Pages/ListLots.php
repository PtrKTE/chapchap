<?php

declare(strict_types=1);

namespace App\Filament\Resources\LotResource\Pages;

use App\Exports\LotsExport;
use App\Filament\Resources\LotResource;
use App\Traits\ExportableParDates;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;

class ListLots extends ListRecords
{
    use ExportableParDates;

    protected static string $resource = LotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Nouveau lot'),
            $this->actionExportExcel('Exporter lots'),
        ];
    }

    protected function telechargerExport(?string $dateDebut, ?string $dateFin)
    {
        return Excel::download(
            new LotsExport($dateDebut, $dateFin),
            'lots_' . now()->format('Y-m-d') . '.xlsx'
        );
    }
}
