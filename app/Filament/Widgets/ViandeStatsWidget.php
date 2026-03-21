<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\CategorieProduit;
use App\Enums\Profil;
use App\Models\VenteLigne;
use App\Traits\WidgetVisibleParProfil;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

/**
 * Widget Viande — KPI de l'activité viande complémentaire.
 *
 * Affiche 3 indicateurs clés sur les ventes de produits catégorie "viande" :
 * - CA viande du mois
 * - Volume vendu ce mois (kg)
 * - Marge brute viande du mois
 *
 * Visible par le Gérant et le Resp. Opérations.
 */
class ViandeStatsWidget extends StatsOverviewWidget
{
    use WidgetVisibleParProfil;

    protected static ?int $sort = 6;

    public static function canView(): bool
    {
        return static::visiblePour([Profil::GERANT, Profil::RESP_OPERATIONS]);
    }

    protected function getStats(): array
    {
        $debutMois = Carbon::now()->startOfMonth();

        // Lignes de vente de produits catégorie "viande" ce mois
        $lignesMois = VenteLigne::whereHas('produit', fn ($q) => $q->where('categorie', CategorieProduit::VIANDE->value))
            ->whereHas('vente', fn ($q) => $q->where('annulee', false)->whereBetween('date_vente', [$debutMois, now()]))
            ->with('vente')
            ->get();

        $caMois     = (float) $lignesMois->sum('montant_ligne');
        $volumeMois = (float) $lignesMois->sum('quantite');
        $margeMois  = (float) $lignesMois->sum('marge_ligne');
        $tauxMarge  = $caMois > 0 ? round(($margeMois / $caMois) * 100, 1) : 0;

        // CA viande aujourd'hui
        $caJour = (float) VenteLigne::whereHas('produit', fn ($q) => $q->where('categorie', CategorieProduit::VIANDE->value))
            ->whereHas('vente', fn ($q) => $q->where('annulee', false)->whereDate('date_vente', today()))
            ->sum('montant_ligne');

        return [
            Stat::make('CA Viande ce mois', number_format($caMois, 0, ',', ' ') . ' F')
                ->description(number_format($caJour, 0, ',', ' ') . ' F aujourd\'hui')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('primary'),

            Stat::make('Volume vendu', number_format($volumeMois, 2, ',', ' ') . ' kg')
                ->description('Quantité viande ce mois')
                ->descriptionIcon('heroicon-m-scale')
                ->color('info'),

            Stat::make('Marge brute viande', number_format($margeMois, 0, ',', ' ') . ' F')
                ->description("Taux de marge : {$tauxMarge}%")
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color($tauxMarge >= 20 ? 'success' : 'warning'),
        ];
    }
}
