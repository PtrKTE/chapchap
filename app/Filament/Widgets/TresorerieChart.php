<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\Profil;
use App\Models\Charge;
use App\Models\Paiement;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

/**
 * Graphique en barres groupées : Encaissements vs Décaissements.
 *
 * Compare jour par jour sur les 7 derniers jours :
 * - Barres vertes : paiements reçus (table paiements)
 * - Barres rouges : charges/dépenses (table charges)
 *
 * Permet au gérant de visualiser rapidement la trésorerie nette.
 *
 * Visible par le Gérant et le Resp. Opérations.
 */
class TresorerieChart extends ChartWidget
{
    protected static ?string $heading = 'Trésorerie — 7 derniers jours';

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $maxHeight = '280px';

    public static function canView(): bool
    {
        $profil = auth()->user()?->profil;

        return in_array($profil, [Profil::GERANT, Profil::RESP_OPERATIONS]);
    }

    protected function getData(): array
    {
        $dateDebut = Carbon::today()->subDays(6);

        // 2 requêtes GROUP BY au lieu de 14 requêtes individuelles
        $encParJour = Paiement::whereDate('date_paiement', '>=', $dateDebut)
            ->selectRaw('DATE(date_paiement) as jour, SUM(montant) as total')
            ->groupBy('jour')
            ->pluck('total', 'jour');

        $decParJour = Charge::whereDate('date_charge', '>=', $dateDebut)
            ->selectRaw('DATE(date_charge) as jour, SUM(montant) as total')
            ->groupBy('jour')
            ->pluck('total', 'jour');

        $labels = [];
        $encaissements = [];
        $decaissements = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $dateStr = $date->toDateString();
            $labels[] = $date->translatedFormat('D d/m');
            $encaissements[] = (float) ($encParJour[$dateStr] ?? 0);
            $decaissements[] = (float) ($decParJour[$dateStr] ?? 0);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Encaissements',
                    'data' => $encaissements,
                    'backgroundColor' => 'rgba(34, 197, 94, 0.8)',
                    'borderRadius' => 4,
                ],
                [
                    'label' => 'Décaissements',
                    'data' => $decaissements,
                    'backgroundColor' => 'rgba(239, 68, 68, 0.8)',
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
