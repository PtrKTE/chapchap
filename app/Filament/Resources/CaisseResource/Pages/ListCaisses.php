<?php

declare(strict_types=1);

namespace App\Filament\Resources\CaisseResource\Pages;

use App\Exports\CaissesExport;
use App\Filament\Resources\CaisseResource;
use App\Models\Caisse;
use App\Traits\ExportableParDates;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Onglets par statut de caisse.
     *
     * Permet de voir rapidement les caisses en attente de validation.
     */
    public function getTabs(): array
    {
        return [
            'toutes' => Tab::make('Toutes')
                ->badge(Caisse::count()),

            'ouvertes' => Tab::make('Ouvertes')
                ->badge(Caisse::where('statut', 'ouverte')->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('statut', 'ouverte')),

            'cloturees' => Tab::make('Clôturées')
                ->badge(Caisse::where('statut', 'cloturee')->count())
                ->badgeColor('info')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('statut', 'cloturee')),

            'validees' => Tab::make('Validées')
                ->badge(Caisse::where('statut', 'validee')->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('statut', 'validee')),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            CaisseResource\Widgets\CaisseStatsWidget::class,
        ];
    }
}
