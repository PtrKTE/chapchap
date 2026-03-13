<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\Profil;
use App\Models\Produit;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

/**
 * Graphique doughnut : Top 5 produits vendus par CA ce mois.
 *
 * Affiche la répartition des ventes par produit (montant_ligne).
 * Utile pour identifier les produits les plus rentables.
 *
 * Visible par le Gérant et le Resp. Opérations.
 */
class TopProduitsChart extends ChartWidget
{
    protected static ?string $heading = 'Top 5 produits — mois en cours';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 1;

    protected static ?string $maxHeight = '300px';

    public static function canView(): bool
    {
        $profil = auth()->user()?->profil;

        return in_array($profil, [Profil::GERANT, Profil::RESP_OPERATIONS]);
    }

    protected function getData(): array
    {
        $debutMois = Carbon::now()->startOfMonth();

        // Top 5 produits par CA du mois (somme montant_ligne)
        $topProduits = Produit::withSum(
            ['venteLignes as ca_mois' => function ($q) use ($debutMois) {
                $q->whereHas('vente', function ($qv) use ($debutMois) {
                    $qv->where('annulee', false)
                        ->whereBetween('date_vente', [$debutMois, Carbon::now()]);
                });
            }],
            'montant_ligne'
        )
            ->having('ca_mois', '>', 0)
            ->orderByDesc('ca_mois')
            ->limit(5)
            ->get();

        $labels = $topProduits->pluck('nom')->toArray();
        $data = $topProduits->pluck('ca_mois')->map(fn($v) => (float) $v)->toArray();

        return [
            'datasets' => [
                [
                    'data' => $data,
                    'backgroundColor' => [
                        '#f97316', // Orange
                        '#3b82f6', // Bleu
                        '#22c55e', // Vert
                        '#eab308', // Jaune
                        '#ef4444', // Rouge
                    ],
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
