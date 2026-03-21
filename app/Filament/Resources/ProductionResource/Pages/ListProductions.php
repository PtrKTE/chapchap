<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductionResource\Pages;

use App\Exports\ProductionsExport;
use App\Filament\Resources\ProductionResource;
use App\Models\Production;
use App\Traits\ExportableParDates;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Facades\Excel;

class ListProductions extends ListRecords
{
    use ExportableParDates;

    protected static string $resource = ProductionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Nouvelle production'),
            $this->actionExportExcel('Exporter productions'),
        ];
    }

    protected function telechargerExport(?string $dateDebut, ?string $dateFin)
    {
        return Excel::download(
            new ProductionsExport($dateDebut, $dateFin),
            'productions_' . now()->format('Y-m-d') . '.xlsx'
        );
    }

    /**
     * Onglets de filtrage rapide par statut de production.
     */
    public function getTabs(): array
    {
        return [
            'tous' => Tab::make('Toutes')
                ->badge(Production::count()),

            'en_cours' => Tab::make('En cours')
                ->badge(Production::where('statut', 'en_cours')->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('statut', 'en_cours')),

            'validees' => Tab::make('Validées')
                ->badge(Production::where('statut', 'validee')->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('statut', 'validee')),

            'annulees' => Tab::make('Annulées')
                ->badge(Production::where('statut', 'annulee')->count())
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('statut', 'annulee')),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ProductionResource\Widgets\ProductionStatsWidget::class,
        ];
    }
}
