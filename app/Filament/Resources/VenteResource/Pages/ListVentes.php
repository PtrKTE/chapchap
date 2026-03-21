<?php

declare(strict_types=1);

namespace App\Filament\Resources\VenteResource\Pages;

use App\Exports\VentesExport;
use App\Filament\Resources\VenteResource;
use App\Models\Vente;
use App\Traits\ExportableParDates;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Facades\Excel;

class ListVentes extends ListRecords
{
    use ExportableParDates;

    protected static string $resource = VenteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nouvelle vente'),
            $this->actionExportExcel('Exporter ventes'),
        ];
    }

    protected function telechargerExport(?string $dateDebut, ?string $dateFin)
    {
        return Excel::download(
            new VentesExport($dateDebut, $dateFin),
            'ventes_' . now()->format('Y-m-d') . '.xlsx'
        );
    }

    /**
     * Onglets de filtrage rapide par statut de paiement.
     *
     * Permet aux commerciaux de voir d'un coup d'œil les ventes à encaisser.
     */
    public function getTabs(): array
    {
        return [
            'toutes' => Tab::make('Toutes')
                ->badge(Vente::count()),

            'credit' => Tab::make('À crédit')
                ->badge(Vente::where('annulee', false)->where('statut_paiement', 'credit')->count())
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('annulee', false)->where('statut_paiement', 'credit')),

            'partiel' => Tab::make('Partielles')
                ->badge(Vente::where('annulee', false)->where('statut_paiement', 'partiel')->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('annulee', false)->where('statut_paiement', 'partiel')),

            'payees' => Tab::make('Payées')
                ->badge(Vente::where('annulee', false)->whereIn('statut_paiement', ['paye', 'regle_patronne'])->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('annulee', false)->whereIn('statut_paiement', ['paye', 'regle_patronne'])),

            'annulees' => Tab::make('Annulées')
                ->badge(Vente::where('annulee', true)->count())
                ->badgeColor('gray')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('annulee', true)),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            VenteResource\Widgets\VenteStatsWidget::class,
        ];
    }
}
