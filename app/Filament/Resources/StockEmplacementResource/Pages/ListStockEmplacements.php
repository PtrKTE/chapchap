<?php

declare(strict_types=1);

namespace App\Filament\Resources\StockEmplacementResource\Pages;

use App\Exports\StockExport;
use App\Filament\Resources\StockEmplacementResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;

class ListStockEmplacements extends ListRecords
{
    protected static string $resource = StockEmplacementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('export_excel')
                ->label('Exporter stock')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(fn () => Excel::download(
                    new StockExport(),
                    'stock_' . now()->format('Y-m-d') . '.xlsx'
                )),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            StockEmplacementResource\Widgets\StockStatsWidget::class,
        ];
    }
}
