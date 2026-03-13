<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\Profil;
use App\Enums\StatutPaiement;
use App\Models\Vente;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

/**
 * Widget impayés — vue rapide de la situation des crédits clients.
 *
 * Affiche 3 indicateurs :
 * 1. Montant total des impayés (montant_restant > 0, non annulées)
 * 2. Nombre de ventes avec crédit en cours
 * 3. Ancienneté moyenne des impayés en jours
 *
 * Visible par le Gérant et les Commerciaux.
 */
class ImpayesWidget extends BaseWidget
{
    protected static ?int $sort = 5;

    protected static ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        $profil = auth()->user()?->profil;

        return in_array($profil, [Profil::GERANT, Profil::RESP_OPERATIONS, Profil::COMMERCIAL]);
    }

    protected function getStats(): array
    {
        // Ventes non annulées avec un restant > 0 (crédits ou partiels)
        $ventesImpayes = Vente::where('annulee', false)
            ->where('montant_restant', '>', 0)
            ->whereIn('statut_paiement', [StatutPaiement::CREDIT, StatutPaiement::PARTIEL])
            ->get();

        $montantTotal = $ventesImpayes->sum(fn($v) => (float) $v->montant_restant);
        $nbImpayes = $ventesImpayes->count();

        // Ancienneté moyenne en jours
        $anciennetesMoyenne = $nbImpayes > 0
            ? $ventesImpayes->avg(fn($v) => Carbon::now()->diffInDays($v->date_vente))
            : 0;

        // Nombre d'impayés > 30 jours (critique)
        $impayesCritiques = $ventesImpayes->filter(
            fn($v) => Carbon::now()->diffInDays($v->date_vente) > 30
        )->count();

        return [
            Stat::make('Impayés en cours', number_format($montantTotal, 0, ',', ' ') . ' F')
                ->description($nbImpayes . ' vente(s) concernée(s)')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($montantTotal > 500000 ? 'danger' : ($montantTotal > 100000 ? 'warning' : 'success')),

            Stat::make('Ancienneté moyenne', round($anciennetesMoyenne, 0) . ' jours')
                ->description($impayesCritiques > 0 ? "{$impayesCritiques} > 30 jours" : 'Aucun impayé critique')
                ->descriptionIcon($impayesCritiques > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($impayesCritiques > 0 ? 'danger' : 'success'),

            Stat::make('Taux de recouvrement', $this->tauxRecouvrement() . '%')
                ->description('Mois en cours')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('info'),
        ];
    }

    /**
     * Taux de recouvrement du mois = paiements reçus / montant total vendu × 100
     */
    private function tauxRecouvrement(): float
    {
        $debutMois = Carbon::now()->startOfMonth();

        // 1 requête au lieu de 2 : récupère les 2 sommes en un seul SELECT
        $totaux = Vente::where('annulee', false)
            ->whereBetween('date_vente', [$debutMois, Carbon::now()])
            ->selectRaw('SUM(montant_net) as total_vendu, SUM(montant_recu) as total_encaisse')
            ->first();

        $totalVendu = (float) ($totaux->total_vendu ?? 0);
        $totalEncaisse = (float) ($totaux->total_encaisse ?? 0);

        return $totalVendu > 0 ? round(($totalEncaisse / $totalVendu) * 100, 1) : 100;
    }
}
