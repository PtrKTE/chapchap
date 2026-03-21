<?php

declare(strict_types=1);

namespace App\Filament\Resources\ChargeResource\Pages;

use App\Exports\ChargesExport;
use App\Filament\Resources\ChargeResource;
use App\Filament\Resources\ChargeResource\Widgets\ChargeStatsWidget;
use App\Models\CategorieCharge;
use App\Models\Charge;
use App\Traits\ExportableParDates;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Onglets de filtrage rapide.
     *
     * - Toutes : vue globale
     * - Aujourd'hui : ce qui a été saisi / dépensé aujourd'hui
     * - Ce mois : périmètre mensuel
     * - Par catégorie de charge (fixe vs variable via le type)
     */
    public function getTabs(): array
    {
        $tabs = [
            'toutes' => Tab::make('Toutes')
                ->badge(Charge::count()),

            'aujourd_hui' => Tab::make("Aujourd'hui")
                ->badge(Charge::whereDate('date_charge', today())->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $q) => $q->whereDate('date_charge', today())),

            'mois' => Tab::make('Ce mois')
                ->badge(
                    Charge::whereBetween('date_charge', [
                        now()->startOfMonth(), now()->endOfMonth(),
                    ])->count()
                )
                ->badgeColor('info')
                ->modifyQueryUsing(fn (Builder $q) => $q->whereBetween('date_charge', [
                    now()->startOfMonth(), now()->endOfMonth(),
                ])),

            'fixes' => Tab::make('Charges fixes')
                ->badge(
                    Charge::whereHas('categorie', fn (Builder $q) => $q->where('type', 'fixe'))->count()
                )
                ->badgeColor('primary')
                ->modifyQueryUsing(fn (Builder $q) => $q->whereHas(
                    'categorie', fn (Builder $q) => $q->where('type', 'fixe')
                )),

            'variables' => Tab::make('Charges variables')
                ->badge(
                    Charge::whereHas('categorie', fn (Builder $q) => $q->where('type', 'variable'))->count()
                )
                ->badgeColor('gray')
                ->modifyQueryUsing(fn (Builder $q) => $q->whereHas(
                    'categorie', fn (Builder $q) => $q->where('type', 'variable')
                )),
        ];

        return $tabs;
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ChargeStatsWidget::class,
        ];
    }
}
