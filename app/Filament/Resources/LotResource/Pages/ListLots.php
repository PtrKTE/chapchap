<?php

declare(strict_types=1);

namespace App\Filament\Resources\LotResource\Pages;

use App\Exports\LotsExport;
use App\Filament\Resources\LotResource;
use App\Models\Lot;
use App\Traits\ExportableParDates;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Onglets de filtrage rapide pour les statuts de facture.
     * Filament 3 permet d'ajouter des onglets avec getTabs().
     */
    public function getTabs(): array
    {
        $total       = Lot::count();
        $nonReglees  = Lot::where('statut_facture', 'non_reglee')->count();
        $partielles  = Lot::where('statut_facture', 'partielle')->count();
        $reglees     = Lot::where('statut_facture', 'reglee')->count();

        return [
            'tous' => Tab::make('Tous')
                ->badge($total),

            'non_reglees' => Tab::make('Non réglées')
                ->badge($nonReglees)
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('statut_facture', 'non_reglee')),

            'partielles' => Tab::make('Partielles')
                ->badge($partielles)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('statut_facture', 'partielle')),

            'reglees' => Tab::make('Réglées')
                ->badge($reglees)
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('statut_facture', 'reglee')),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            LotResource\Widgets\LotStatsWidget::class,
        ];
    }
}
