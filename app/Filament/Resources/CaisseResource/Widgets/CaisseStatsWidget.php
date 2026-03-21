<?php

declare(strict_types=1);

namespace App\Filament\Resources\CaisseResource\Widgets;

use App\Models\Caisse;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Widget de statistiques pour la liste des caisses.
 *
 * 4 KPIs du mois en cours :
 * - Total encaissé (ventes)
 * - Total décaissé (charges)
 * - Solde net (encaissé - décaissé)
 * - Caisses avec écart > 0
 */
class CaisseStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        // Statistiques du mois en cours
        $mois = now()->month;
        $annee = now()->year;

        $totalEncaisse = Caisse::whereMonth('date_caisse', $mois)
            ->whereYear('date_caisse', $annee)
            ->sum('total_encaisse');

        $totalDecaisse = Caisse::whereMonth('date_caisse', $mois)
            ->whereYear('date_caisse', $annee)
            ->sum('total_decaisse');

        $soldeNet = (float) $totalEncaisse - (float) $totalDecaisse;

        // Caisses avec un écart absolu > 1 FCFA (anomalie)
        $avecEcart = Caisse::whereMonth('date_caisse', $mois)
            ->whereYear('date_caisse', $annee)
            ->whereRaw('ABS(ecart) > 1')
            ->count();

        // Caisse ouverte aujourd'hui (indicateur temps réel)
        $caisseOuverte = Caisse::whereDate('date_caisse', today())
            ->where('statut', 'ouverte')
            ->exists();

        return [
            Stat::make('Encaissé ce mois', number_format((float) $totalEncaisse, 0, ',', ' ') . ' FCFA')
                ->description('Ventes + crédits récupérés')
                ->icon('heroicon-o-arrow-trending-up')
                ->color('success'),

            Stat::make('Décaissé ce mois', number_format((float) $totalDecaisse, 0, ',', ' ') . ' FCFA')
                ->description('Charges et dépenses')
                ->icon('heroicon-o-arrow-trending-down')
                ->color('danger'),

            Stat::make('Solde net', number_format($soldeNet, 0, ',', ' ') . ' FCFA')
                ->description('Encaissé - Décaissé')
                ->icon('heroicon-o-scale')
                ->color($soldeNet >= 0 ? 'primary' : 'danger'),

            Stat::make('Caisse du jour', $caisseOuverte ? 'Ouverte' : 'Non ouverte')
                ->description($avecEcart > 0 ? "{$avecEcart} caisse(s) avec écart ce mois" : 'Aucun écart ce mois')
                ->icon($caisseOuverte ? 'heroicon-o-lock-open' : 'heroicon-o-lock-closed')
                ->color($caisseOuverte ? 'success' : 'warning'),
        ];
    }
}
