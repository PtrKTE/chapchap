<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\Profil;
use App\Traits\WidgetVisibleParProfil;
use App\Models\Client;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

/**
 * Graphique en barres : Top 5 clients par CA du mois.
 *
 * Calcule le montant_net total des ventes non annulées du mois en cours
 * pour chaque client, et affiche les 5 meilleurs.
 *
 * Visible par le Gérant et les Commerciaux.
 */
class TopClientsChart extends ChartWidget
{
    use WidgetVisibleParProfil;

    protected static ?string $heading = 'Top 5 clients — mois en cours';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    protected static ?string $maxHeight = '300px';

    public static function canView(): bool
    {
        return static::visiblePour([Profil::GERANT, Profil::RESP_OPERATIONS, Profil::COMMERCIAL]);
    }

    protected function getData(): array
    {
        $debutMois = Carbon::now()->startOfMonth();

        // Top 5 clients par CA du mois
        $topClients = Client::withSum(
            ['ventes as ca_mois' => function ($q) use ($debutMois) {
                $q->where('annulee', false)
                    ->whereBetween('date_vente', [$debutMois, Carbon::now()]);
            }],
            'montant_net'
        )
            ->having('ca_mois', '>', 0)
            ->orderByDesc('ca_mois')
            ->limit(5)
            ->get();

        $labels = $topClients->pluck('nom')->map(function ($nom) {
            // Tronquer les noms longs pour l'affichage
            return mb_strlen($nom) > 15 ? mb_substr($nom, 0, 15) . '...' : $nom;
        })->toArray();

        $data = $topClients->pluck('ca_mois')->map(fn($v) => (float) $v)->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'CA (FCFA)',
                    'data' => $data,
                    'backgroundColor' => [
                        '#f97316', // Orange
                        '#fb923c',
                        '#fdba74',
                        '#fed7aa',
                        '#ffedd5',
                    ],
                    'borderRadius' => 4,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
