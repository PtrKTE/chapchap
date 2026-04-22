<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductionResource\Widgets;

use App\Models\Production;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ProductionStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $query = Production::query()
            ->whereMonth('date_production', now()->month)
            ->whereYear('date_production', now()->year);

        $totalProductions  = (clone $query)->count();
        $totalPoulets      = (clone $query)->where('statut', 'validee')->sum('nb_poulets_traites');
        $rendementMoyen    = (clone $query)->where('statut', 'validee')->whereNotNull('rendement')->avg('rendement');
        $enCours           = Production::where('statut', 'en_cours')->count();

        return [
            Stat::make('Productions ce mois', $totalProductions)
                ->description(now()->translatedFormat('F Y'))
                ->icon('heroicon-o-cog-6-tooth')
                ->color('primary'),

            Stat::make('Poulets abattus', number_format((int) $totalPoulets, 0, ',', ' '))
                ->description('Productions validées ce mois')
                ->icon('heroicon-o-check-circle')
                ->color('success'),

            Stat::make('Rendement moyen', $rendementMoyen ? number_format((float) $rendementMoyen * 100, 1) . ' %' : '')
                ->description('Valorisation / coût matière')
                ->icon('heroicon-o-chart-bar')
                ->color((float) $rendementMoyen > 1 ? 'success' : 'warning'),

            Stat::make('En attente de validation', $enCours)
                ->description('Productions en cours')
                ->icon('heroicon-o-clock')
                ->color($enCours > 0 ? 'warning' : 'success'),
        ];
    }
}
