<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\Profil;
use App\Traits\WidgetVisibleParProfil;
use App\Enums\StatutProduction;
use App\Models\Production;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

/**
 * Widget production — indicateurs de la semaine.
 *
 * Affiche 3 indicateurs :
 * 1. Poulets traités cette semaine (somme nb_poulets_traites)
 * 2. Rendement moyen des productions validées de la semaine
 * 3. Pertes/casse totales de la semaine (kg)
 *
 * Visible par le Gérant, le Resp. Opérations et l'Agent de Production.
 */
class ProductionStatsWidget extends BaseWidget
{
    use WidgetVisibleParProfil;

    protected static ?int $sort = 7;

    protected static ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        return static::visiblePour([Profil::GERANT, Profil::RESP_OPERATIONS, Profil::AGENT_PRODUCTION]);
    }

    protected function getStats(): array
    {
        $debutSemaine = Carbon::now()->startOfWeek();
        $debutMois = Carbon::now()->startOfMonth();

        // Productions validées de la semaine
        $prodsSemaine = Production::where('statut', StatutProduction::VALIDEE)
            ->whereBetween('date_production', [$debutSemaine, Carbon::now()])
            ->get();

        // Productions validées du mois
        $prodsMois = Production::where('statut', StatutProduction::VALIDEE)
            ->whereBetween('date_production', [$debutMois, Carbon::now()])
            ->get();

        // Poulets traités cette semaine
        $pouletsTraitesSemaine = $prodsSemaine->sum('nb_poulets_traites');

        // Poulets traités ce mois
        $pouletsTraitesMois = $prodsMois->sum('nb_poulets_traites');

        // Rendement moyen de la semaine (en %)
        $rendementMoyen = $prodsSemaine->count() > 0
            ? $prodsSemaine->avg(fn($p) => (float) $p->rendement * 100)
            : 0;

        // Pertes/casse de la semaine (kg)
        $pertesSemaine = $prodsSemaine->sum(fn($p) => (float) $p->pertes_casse);

        // Pertes du mois (kg)
        $pertesMois = $prodsMois->sum(fn($p) => (float) $p->pertes_casse);

        return [
            Stat::make('Poulets traités', number_format($pouletsTraitesSemaine, 0, ',', ' '))
                ->description("Semaine en cours | Mois : {$pouletsTraitesMois}")
                ->descriptionIcon('heroicon-m-scissors')
                ->color('primary'),

            Stat::make('Rendement moyen', round($rendementMoyen, 1) . '%')
                ->description($prodsSemaine->count() . ' production(s) validée(s)')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color($rendementMoyen >= 85 ? 'success' : ($rendementMoyen >= 70 ? 'warning' : 'danger')),

            Stat::make('Pertes / Casse', number_format($pertesSemaine, 2, ',', ' ') . ' kg')
                ->description("Mois : " . number_format($pertesMois, 2, ',', ' ') . ' kg')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($pertesSemaine > 10 ? 'danger' : ($pertesSemaine > 5 ? 'warning' : 'success')),
        ];
    }
}
