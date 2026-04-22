<?php

declare(strict_types=1);

namespace App\Filament\Resources\ChargeResource\Widgets;

use App\Models\Charge;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Widget KPI affiché en haut de la liste des charges.
 *
 * 4 indicateurs clés :
 * - Total dépensé ce mois
 * - Total dépensé aujourd'hui
 * - Catégorie la plus coûteuse du mois (avec montant)
 * - Nombre de charges saisies ce mois
 */
class ChargeStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $debutMois = now()->startOfMonth();
        $finMois   = now()->endOfMonth();

        // Total charges ce mois
        $totalMois = (float) Charge::whereBetween('date_charge', [$debutMois, $finMois])
            ->sum('montant');

        // Total charges aujourd'hui
        $totalJour = (float) Charge::whereDate('date_charge', today())
            ->sum('montant');

        // Catégorie la plus coûteuse du mois
        $topCategorie = Charge::whereBetween('date_charge', [$debutMois, $finMois])
            ->with('categorie')
            ->selectRaw('categorie_id, SUM(montant) as total')
            ->groupBy('categorie_id')
            ->orderByDesc('total')
            ->first();

        $topNom     = $topCategorie?->categorie?->nom ?? '';
        $topMontant = $topCategorie ? number_format((float) $topCategorie->total, 0, ',', ' ') . ' FCFA' : '';

        // Nombre de charges ce mois
        $nbMois = Charge::whereBetween('date_charge', [$debutMois, $finMois])->count();

        return [
            Stat::make('Dépenses du mois', number_format($totalMois, 0, ',', ' ') . ' FCFA')
                ->description('Total charges enregistrées')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('danger'),

            Stat::make("Dépenses aujourd'hui", number_format($totalJour, 0, ',', ' ') . ' FCFA')
                ->description('Charges saisies ce jour')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('warning'),

            Stat::make('Top catégorie du mois', $topNom)
                ->description($topMontant)
                ->descriptionIcon('heroicon-m-tag')
                ->color('info'),

            Stat::make('Nb charges ce mois', (string) $nbMois)
                ->description('Lignes de dépenses')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('gray'),
        ];
    }
}
