<?php

declare(strict_types=1);

namespace App\Filament\Resources\ClientResource\Widgets;

use App\Models\Client;
use App\Models\Vente;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Widget de statistiques pour la liste des clients.
 *
 * Affiché en en-tête de ListClients avec 4 indicateurs :
 * - Nombre de clients actifs
 * - Clients avec impayés
 * - Total des impayés
 * - Nouveaux clients ce mois
 */
class ClientStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $nbActifs = Client::where('actif', true)->count();

        // Clients qui ont au moins une vente impayée (montant_restant > 0)
        $avecImpayes = Client::whereHas('ventes', fn ($q) =>
            $q->where('annulee', false)->where('montant_restant', '>', 0)
        )->count();

        // Montant total des impayés toutes ventes confondues
        $totalImpayes = Vente::where('annulee', false)
            ->where('montant_restant', '>', 0)
            ->sum('montant_restant');

        // Nouveaux clients enregistrés ce mois
        $nouveauxMois = Client::whereMonth('date_enregistrement', now()->month)
            ->whereYear('date_enregistrement', now()->year)
            ->count();

        return [
            Stat::make('Clients actifs', $nbActifs)
                ->description('Clients en activité')
                ->icon('heroicon-o-user-group')
                ->color('primary'),

            Stat::make('Clients avec impayés', $avecImpayes)
                ->description('Ont un solde restant > 0')
                ->icon('heroicon-o-exclamation-triangle')
                ->color($avecImpayes > 0 ? 'warning' : 'success'),

            Stat::make('Total impayés', number_format((float) $totalImpayes, 0, ',', ' ') . ' FCFA')
                ->description('Somme de tous les restants dus')
                ->icon('heroicon-o-banknotes')
                ->color($totalImpayes > 0 ? 'danger' : 'success'),

            Stat::make('Nouveaux ce mois', $nouveauxMois)
                ->description(now()->translatedFormat('F Y'))
                ->icon('heroicon-o-user-plus')
                ->color('info'),
        ];
    }
}
