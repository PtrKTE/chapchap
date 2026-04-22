<?php

declare(strict_types=1);

namespace App\Filament\Resources\LotResource\Widgets;

use App\Models\Lot;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Widget de statistiques affiché en haut de la liste des lots.
 *
 * Concept Laravel/Filament : un Widget StatsOverview affiche des cartes KPI
 * en haut d'une page. Ici on affiche les totaux des lots du mois en cours.
 */
class LotStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        // Lots du mois en cours
        $lotsQuery = Lot::query()->whereMonth('date_reception', now()->month)
            ->whereYear('date_reception', now()->year);

        $totalLots       = (clone $lotsQuery)->count();
        $totalPoulets    = (clone $lotsQuery)->where('type_produit', 'poulet')->sum('quantite_utilisable');
        $totalCout       = (clone $lotsQuery)->sum('cout_total');
        $totalImpayes    = Lot::whereIn('statut_facture', ['non_reglee', 'partielle'])
            ->selectRaw('SUM(montant_facture - montant_regle) as reste')
            ->value('reste') ?? 0;

        return [
            Stat::make('Lots ce mois', $totalLots)
                ->description('Lots réceptionnés en ' . now()->translatedFormat('F Y'))
                ->icon('heroicon-o-archive-box')
                ->color('primary'),

            Stat::make('Poulets utilisables', number_format((float) $totalPoulets, 0, ',', ' '))
                ->description('Ce mois, après pertes et refus')
                ->icon('heroicon-o-check-circle')
                ->color('success'),

            Stat::make('Coût total achats', number_format((float) $totalCout, 0, ',', ' ') . ' FCFA')
                ->description('Ce mois')
                ->icon('heroicon-o-currency-dollar')
                ->color('warning'),

            Stat::make('Impayés fournisseurs', number_format((float) $totalImpayes, 0, ',', ' ') . ' FCFA')
                ->description('Toutes périodes confondues')
                ->icon('heroicon-o-exclamation-triangle')
                ->color($totalImpayes > 0 ? 'danger' : 'success'),
        ];
    }
}
