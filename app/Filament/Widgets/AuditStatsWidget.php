<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\JournalAudit;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Widget de statistiques pour la page de surveillance & audit.
 *
 * Affiche 4 indicateurs :
 * - Nombre d'actions enregistrées aujourd'hui
 * - Nombre d'utilisateurs actuellement en ligne (session < 15 min)
 * - Nombre de connexions aujourd'hui
 * - Nombre d'actions cette semaine
 *
 * Ce widget n'apparaît PAS sur le dashboard principal (canView = false).
 * Il est rendu manuellement via @livewire dans la page AuditMonitoring.
 */
class AuditStatsWidget extends BaseWidget
{
    // Rafraîchissement automatique toutes les 30 secondes
    protected static ?string $pollingInterval = '30s';

    /**
     * Masqué du dashboard principal — rendu uniquement sur la page AuditMonitoring.
     */
    public static function canView(): bool
    {
        return false;
    }

    protected function getStats(): array
    {
        $aujourdhui = Carbon::today();

        // Seuil "en ligne" : activité dans les 15 dernières minutes
        $seuilEnLigne = Carbon::now()->subMinutes(15)->getTimestamp();

        // 1. Nombre d'actions enregistrées aujourd'hui
        $actionsAujourdhui = JournalAudit::whereDate('created_at', $aujourdhui)->count();

        // 2. Utilisateurs en ligne (sessions actives < 15 min)
        $utilisateursEnLigne = DB::table('sessions')
            ->whereNotNull('user_id')
            ->where('last_activity', '>=', $seuilEnLigne)
            ->distinct('user_id')
            ->count('user_id');

        // 3. Connexions aujourd'hui
        $connexionsAujourdhui = JournalAudit::where('action', 'connexion')
            ->whereDate('created_at', $aujourdhui)
            ->count();

        // 4. Actions cette semaine (depuis lundi)
        $actionsSemaine = JournalAudit::where('created_at', '>=', Carbon::now()->startOfWeek())
            ->count();

        return [
            Stat::make('Actions aujourd\'hui', (string) $actionsAujourdhui)
                ->description('Enregistrements dans le journal')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('primary'),

            Stat::make('Utilisateurs en ligne', (string) $utilisateursEnLigne)
                ->description('Actifs dans les 15 dernières min.')
                ->descriptionIcon('heroicon-m-signal')
                ->color($utilisateursEnLigne > 0 ? 'success' : 'gray'),

            Stat::make('Connexions aujourd\'hui', (string) $connexionsAujourdhui)
                ->description('Connexions enregistrées')
                ->descriptionIcon('heroicon-m-arrow-right-on-rectangle')
                ->color('info'),

            Stat::make('Actions cette semaine', (string) $actionsSemaine)
                ->description('Depuis lundi')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('warning'),
        ];
    }
}
