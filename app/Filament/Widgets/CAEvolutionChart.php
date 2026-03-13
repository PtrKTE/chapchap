<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\Profil;
use App\Models\Vente;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

/**
 * Graphique en ligne : évolution du CA sur les 30 derniers jours.
 *
 * Affiche 2 courbes :
 * - CA net du jour (montant_net des ventes non annulées)
 * - Marge brute du jour (somme marge_ligne via vente_lignes)
 *
 * Visible par le Gérant et le Resp. Opérations.
 */
class CAEvolutionChart extends ChartWidget
{
    protected static ?string $heading = 'Evolution du CA — 30 derniers jours';

    protected static ?int $sort = -2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $maxHeight = '300px';

    public static function canView(): bool
    {
        $profil = auth()->user()?->profil;

        return in_array($profil, [Profil::GERANT, Profil::RESP_OPERATIONS]);
    }

    protected function getData(): array
    {
        $dateDebut = Carbon::today()->subDays(29);

        // 1 seule requête pour le CA groupé par jour
        $caParJour = Vente::where('annulee', false)
            ->whereDate('date_vente', '>=', $dateDebut)
            ->selectRaw('DATE(date_vente) as jour, SUM(montant_net) as total')
            ->groupBy('jour')
            ->pluck('total', 'jour');

        // 1 seule requête pour la marge groupée par jour (via jointure)
        $margeParJour = \App\Models\VenteLigne::join('ventes', 'vente_lignes.vente_id', '=', 'ventes.id')
            ->where('ventes.annulee', false)
            ->whereDate('ventes.date_vente', '>=', $dateDebut)
            ->selectRaw('DATE(ventes.date_vente) as jour, SUM(vente_lignes.marge_ligne) as total')
            ->groupBy('jour')
            ->pluck('total', 'jour');

        // Remplir les tableaux pour les 30 jours
        $labels = [];
        $caData = [];
        $margeData = [];

        for ($i = 29; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $dateStr = $date->toDateString();
            $labels[] = $date->format('d/m');
            $caData[] = (float) ($caParJour[$dateStr] ?? 0);
            $margeData[] = (float) ($margeParJour[$dateStr] ?? 0);
        }

        return [
            'datasets' => [
                [
                    'label' => 'CA Net (FCFA)',
                    'data' => $caData,
                    'borderColor' => '#f97316',   // Orange (couleur primaire CHAPCHAP)
                    'backgroundColor' => 'rgba(249, 115, 22, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Marge brute (FCFA)',
                    'data' => $margeData,
                    'borderColor' => '#22c55e',   // Vert
                    'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
