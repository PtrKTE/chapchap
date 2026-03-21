<?php

declare(strict_types=1);

namespace App\Filament\Resources\StockEmplacementResource\Widgets;

use App\Models\Produit;
use App\Models\StockEmplacement;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StockStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $valeurTotale  = StockEmplacement::sum('valeur_stock');
        $nbProduits    = StockEmplacement::where('quantite', '>', 0)->count();

        // Produits en alerte (quantité <= seuil_alerte_stock)
        $enAlerte = StockEmplacement::whereHas('produit', function ($q) {
            $q->whereColumn('stock_emplacements.quantite', '<=', 'produits.seuil_alerte_stock')
              ->where('produits.seuil_alerte_stock', '>', 0);
        })->count();

        // Produits en rupture (quantité = 0)
        $enRupture = Produit::where('actif', true)
            ->whereDoesntHave('stockEmplacements', fn ($q) => $q->where('quantite', '>', 0))
            ->count();

        return [
            Stat::make('Valeur stock total', number_format((float) $valeurTotale, 0, ',', ' ') . ' FCFA')
                ->description('Tous emplacements confondus')
                ->icon('heroicon-o-banknotes')
                ->color('primary'),

            Stat::make('Produits en stock', $nbProduits)
                ->description('Produits avec quantité > 0')
                ->icon('heroicon-o-cube')
                ->color('success'),

            Stat::make('Alertes stock bas', $enAlerte)
                ->description('Sous le seuil minimum')
                ->icon('heroicon-o-exclamation-triangle')
                ->color($enAlerte > 0 ? 'warning' : 'success'),

            Stat::make('Ruptures de stock', $enRupture)
                ->description('Produits actifs à 0')
                ->icon('heroicon-o-x-circle')
                ->color($enRupture > 0 ? 'danger' : 'success'),
        ];
    }
}
