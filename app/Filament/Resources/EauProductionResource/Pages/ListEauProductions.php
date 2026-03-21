<?php

declare(strict_types=1);

namespace App\Filament\Resources\EauProductionResource\Pages;

use App\Exports\EauProductionsExport;
use App\Filament\Resources\EauProductionResource;
use App\Filament\Resources\EauProductionResource\Widgets\EauStatsWidget;
use App\Models\EauProduction;
use App\Traits\ExportableParDates;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Facades\Excel;

class ListEauProductions extends ListRecords
{
    use ExportableParDates;

    protected static string $resource = EauProductionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nouvelle production eau'),
            $this->actionExportExcel('Exporter productions eau'),
        ];
    }

    protected function telechargerExport(?string $dateDebut, ?string $dateFin)
    {
        return Excel::download(
            new EauProductionsExport($dateDebut, $dateFin),
            'productions_eau_' . now()->format('Y-m-d') . '.xlsx'
        );
    }

    /**
     * Onglets de filtrage rapide.
     */
    public function getTabs(): array
    {
        return [
            'toutes' => Tab::make('Toutes')
                ->badge(EauProduction::count()),

            'aujourd_hui' => Tab::make("Aujourd'hui")
                ->badge(EauProduction::whereDate('date_production', today())->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $q) => $q->whereDate('date_production', today())),

            'mois' => Tab::make('Ce mois')
                ->badge(
                    EauProduction::whereBetween('date_production', [
                        now()->startOfMonth(), now()->endOfMonth(),
                    ])->count()
                )
                ->badgeColor('primary')
                ->modifyQueryUsing(fn (Builder $q) => $q->whereBetween('date_production', [
                    now()->startOfMonth(), now()->endOfMonth(),
                ])),

            'semaine' => Tab::make('Cette semaine')
                ->badge(
                    EauProduction::whereBetween('date_production', [
                        now()->startOfWeek(), now()->endOfWeek(),
                    ])->count()
                )
                ->badgeColor('info')
                ->modifyQueryUsing(fn (Builder $q) => $q->whereBetween('date_production', [
                    now()->startOfWeek(), now()->endOfWeek(),
                ])),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            EauStatsWidget::class,
        ];
    }
}
