<?php

declare(strict_types=1);

namespace App\Filament\Resources\VenteResource\Widgets;

use App\Models\Vente;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Widget de statistiques pour la liste des ventes.
 *
 * Affiché en en-tête de ListVentes avec 4 indicateurs clés :
 * - CA du jour
 * - CA du mois en cours
 * - Impayés en cours (montant_restant total)
 * - Nombre de ventes annulées ce mois
 */
class VenteStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        // Chiffre d'affaires du jour (ventes non annulées)
        $caJour = Vente::where('annulee', false)
            ->whereDate('date_vente', today())
            ->sum('montant_net');

        // Chiffre d'affaires du mois en cours
        $caMois = Vente::where('annulee', false)
            ->whereMonth('date_vente', now()->month)
            ->whereYear('date_vente', now()->year)
            ->sum('montant_net');

        // Total des impayés en cours (toutes ventes non annulées avec un restant > 0)
        $impayes = Vente::where('annulee', false)
            ->where('montant_restant', '>', 0)
            ->sum('montant_restant');

        // Nombre de ventes à crédit (non encore payées du tout)
        $enCredit = Vente::where('annulee', false)
            ->where('statut_paiement', 'credit')
            ->count();

        return [
            Stat::make('CA du jour', number_format((float) $caJour, 0, ',', ' ') . ' FCFA')
                ->description('Ventes non annulées aujourd\'hui')
                ->icon('heroicon-o-calendar-days')
                ->color('primary'),

            Stat::make('CA du mois', number_format((float) $caMois, 0, ',', ' ') . ' FCFA')
                ->description(now()->translatedFormat('F Y'))
                ->icon('heroicon-o-chart-bar')
                ->color('success'),

            Stat::make('Impayés en cours', number_format((float) $impayes, 0, ',', ' ') . ' FCFA')
                ->description('Montant total restant dû')
                ->icon('heroicon-o-exclamation-triangle')
                ->color($impayes > 0 ? 'warning' : 'success'),

            Stat::make('Ventes à crédit', $enCredit)
                ->description('Aucun paiement reçu')
                ->icon('heroicon-o-clock')
                ->color($enCredit > 0 ? 'danger' : 'success'),
        ];
    }
}
