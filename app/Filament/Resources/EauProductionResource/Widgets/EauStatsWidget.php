<?php

declare(strict_types=1);

namespace App\Filament\Resources\EauProductionResource\Widgets;

use App\Models\EauProduction;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Widget KPI production eau en sachet.
 *
 * 4 indicateurs :
 * - Production du mois (paquets)
 * - Production aujourd'hui
 * - Consommation sachets ce mois
 * - Énergie consommée ce mois (kWh)
 */
class EauStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $debutMois = now()->startOfMonth();
        $finMois   = now()->endOfMonth();

        // Production ce mois (paquets)
        $paquetsMois = (int) EauProduction::whereBetween('date_production', [$debutMois, $finMois])
            ->sum('nb_paquets_produits');

        // Production aujourd'hui
        $paquetsJour = (int) EauProduction::whereDate('date_production', today())
            ->sum('nb_paquets_produits');

        // Consommation sachets ce mois
        $sachetsMois = (int) EauProduction::whereBetween('date_production', [$debutMois, $finMois])
            ->sum('consommation_sachets');

        // Énergie ce mois (kWh)
        $energieMois = (float) EauProduction::whereBetween('date_production', [$debutMois, $finMois])
            ->sum('consommation_energie');

        // Nb de saisies ce mois
        $nbSaisies = EauProduction::whereBetween('date_production', [$debutMois, $finMois])->count();

        // Rendement moyen sachets → paquets
        $rendement = $sachetsMois > 0
            ? round(($paquetsMois / $sachetsMois) * 100, 1) . '%'
            : '—';

        return [
            Stat::make('Production ce mois', number_format($paquetsMois, 0, ',', ' ') . ' paquets')
                ->description("{$nbSaisies} journée(s) de production")
                ->descriptionIcon('heroicon-m-beaker')
                ->color('primary'),

            Stat::make("Production aujourd'hui", number_format($paquetsJour, 0, ',', ' ') . ' paquets')
                ->description('Paquets produits ce jour')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color($paquetsJour > 0 ? 'success' : 'gray'),

            Stat::make('Sachets consommés', number_format($sachetsMois, 0, ',', ' ') . ' unités')
                ->description('Rendement : ' . $rendement)
                ->descriptionIcon('heroicon-m-archive-box')
                ->color('warning'),

            Stat::make('Énergie consommée', number_format($energieMois, 2, ',', ' ') . ' kWh')
                ->description('Consommation électrique du mois')
                ->descriptionIcon('heroicon-m-bolt')
                ->color('info'),
        ];
    }
}
