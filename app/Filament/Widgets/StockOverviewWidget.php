<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\Profil;
use App\Models\StockEmplacement;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Widget dashboard : Vue d'ensemble du stock.
 *
 * Affiche 3 indicateurs clés :
 * 1. Valeur totale du stock (toutes emplacements confondues)
 * 2. Nombre de produits en stock
 * 3. Nombre de produits en alerte (sous le seuil)
 */
class StockOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 9;

    public static function canView(): bool
    {
        $profil = auth()->user()?->profil;

        return in_array($profil, [
            Profil::GERANT,
            Profil::RESP_OPERATIONS,
            Profil::GESTIONNAIRE_STOCK,
        ]);
    }

    protected function getStats(): array
    {
        // Valeur totale du stock en FCFA
        $valeurTotale = StockEmplacement::sum('valeur_stock');

        // Nombre de lignes de stock (produit × emplacement) avec quantité > 0
        $nbProduitsEnStock = StockEmplacement::where('quantite', '>', 0)->count();

        // Nombre de produits en alerte stock bas
        $nbAlertes = StockEmplacement::where('quantite', '>', 0)
            ->whereHas('produit', function ($q) {
                $q->where('seuil_alerte_stock', '>', 0);
            })
            ->get()
            ->filter(function ($stock) {
                $seuil = (float) ($stock->produit->seuil_alerte_stock ?? 0);

                return $seuil > 0 && (float) $stock->quantite <= $seuil;
            })
            ->count();

        return [
            Stat::make('Valeur stock totale', number_format((float) $valeurTotale, 0, ',', ' ') . ' FCFA')
                ->description('Toutes emplacements')
                ->descriptionIcon('heroicon-m-building-storefront')
                ->color('primary'),

            Stat::make('Produits en stock', (string) $nbProduitsEnStock)
                ->description('Références avec quantité > 0')
                ->descriptionIcon('heroicon-m-cube')
                ->color('success'),

            Stat::make('Alertes stock bas', (string) $nbAlertes)
                ->description($nbAlertes > 0 ? 'Produits sous le seuil' : 'Aucune alerte')
                ->descriptionIcon($nbAlertes > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($nbAlertes > 0 ? 'danger' : 'success'),
        ];
    }
}
