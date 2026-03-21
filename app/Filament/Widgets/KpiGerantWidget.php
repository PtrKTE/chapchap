<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\Profil;
use App\Models\Vente;
use App\Traits\WidgetVisibleParProfil;
use App\Models\VenteLigne;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

/**
 * Widget KPI principal — visible par le Gérant et le Resp. Opérations.
 *
 * Affiche 4 indicateurs clés :
 * 1. CA du jour (montant_net des ventes non annulées)
 * 2. CA de la semaine en cours
 * 3. CA du mois en cours
 * 4. Marge brute du mois (somme des marge_ligne)
 *
 * Chaque stat affiche une tendance via description (comparaison N-1).
 */
class KpiGerantWidget extends BaseWidget
{
    use WidgetVisibleParProfil;

    protected static ?int $sort = -3;

    // Rafraîchissement automatique toutes les 30 secondes
    protected static ?string $pollingInterval = '30s';

    public static function canView(): bool
    {
        return static::visiblePour([Profil::GERANT, Profil::RESP_OPERATIONS]);
    }

    protected function getStats(): array
    {
        $aujourdhui = Carbon::today();
        $debutSemaine = Carbon::now()->startOfWeek();
        $debutMois = Carbon::now()->startOfMonth();

        // --- CA du jour ---
        $caJour = (float) Vente::whereDate('date_vente', $aujourdhui)
            ->where('annulee', false)
            ->sum('montant_net');

        // CA du jour précédent (comparaison)
        $caHier = (float) Vente::whereDate('date_vente', $aujourdhui->copy()->subDay())
            ->where('annulee', false)
            ->sum('montant_net');

        // --- CA semaine ---
        $caSemaine = (float) Vente::whereBetween('date_vente', [$debutSemaine, Carbon::now()])
            ->where('annulee', false)
            ->sum('montant_net');

        // CA semaine précédente (même nb de jours)
        $joursSemaine = Carbon::now()->diffInDays($debutSemaine) + 1;
        $debutSemPrec = $debutSemaine->copy()->subWeek();
        $finSemPrec = $debutSemPrec->copy()->addDays($joursSemaine - 1);
        $caSemainePrec = (float) Vente::whereBetween('date_vente', [$debutSemPrec, $finSemPrec])
            ->where('annulee', false)
            ->sum('montant_net');

        // --- CA mois ---
        $caMois = (float) Vente::whereBetween('date_vente', [$debutMois, Carbon::now()])
            ->where('annulee', false)
            ->sum('montant_net');

        // CA mois précédent (même nb de jours)
        $joursMois = Carbon::now()->day;
        $debutMoisPrec = $debutMois->copy()->subMonth();
        $finMoisPrec = $debutMoisPrec->copy()->addDays($joursMois - 1);
        $caMoisPrec = (float) Vente::whereBetween('date_vente', [$debutMoisPrec, $finMoisPrec])
            ->where('annulee', false)
            ->sum('montant_net');

        // --- Marge brute mois ---
        $margeMois = (float) VenteLigne::whereHas('vente', function ($q) use ($debutMois) {
            $q->whereBetween('date_vente', [$debutMois, Carbon::now()])
                ->where('annulee', false);
        })->sum('marge_ligne');

        // Taux de marge (marge / CA × 100)
        $tauxMarge = $caMois > 0 ? round(($margeMois / $caMois) * 100, 1) : 0;

        return [
            Stat::make('CA du jour', $this->formatFcfa($caJour))
                ->description($this->tendance($caJour, $caHier, 'vs hier'))
                ->descriptionIcon($this->icone($caJour, $caHier))
                ->color($this->couleur($caJour, $caHier))
                ->chart($this->miniGrapheCA(7)),

            Stat::make('CA semaine', $this->formatFcfa($caSemaine))
                ->description($this->tendance($caSemaine, $caSemainePrec, 'vs sem. préc.'))
                ->descriptionIcon($this->icone($caSemaine, $caSemainePrec))
                ->color($this->couleur($caSemaine, $caSemainePrec)),

            Stat::make('CA mois', $this->formatFcfa($caMois))
                ->description($this->tendance($caMois, $caMoisPrec, 'vs mois préc.'))
                ->descriptionIcon($this->icone($caMois, $caMoisPrec))
                ->color($this->couleur($caMois, $caMoisPrec)),

            Stat::make('Marge brute mois', $this->formatFcfa($margeMois))
                ->description("Taux de marge : {$tauxMarge}%")
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color($tauxMarge >= 30 ? 'success' : ($tauxMarge >= 15 ? 'warning' : 'danger')),
        ];
    }

    /**
     * Mini-graphe sparkline du CA des N derniers jours.
     * Optimisé : une seule requête SQL avec GROUP BY au lieu de N requêtes.
     */
    private function miniGrapheCA(int $jours): array
    {
        $dateDebut = Carbon::today()->subDays($jours - 1);

        // Une seule requête groupée par date
        $resultats = Vente::where('annulee', false)
            ->whereDate('date_vente', '>=', $dateDebut)
            ->selectRaw('DATE(date_vente) as jour, SUM(montant_net) as total')
            ->groupBy('jour')
            ->pluck('total', 'jour');

        // Remplir les jours sans vente avec 0
        $data = [];
        for ($i = $jours - 1; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i)->toDateString();
            $data[] = (float) ($resultats[$date] ?? 0);
        }

        return $data;
    }

    private function formatFcfa(float $montant): string
    {
        return number_format($montant, 0, ',', ' ') . ' F';
    }

    /**
     * Texte de tendance : "+15% vs hier", "-8% vs sem. préc.", etc.
     */
    private function tendance(float $actuel, float $precedent, string $label): string
    {
        if ($precedent == 0) {
            return $actuel > 0 ? "Nouveau {$label}" : "Pas de ventes {$label}";
        }

        $variation = round((($actuel - $precedent) / $precedent) * 100, 1);
        $signe = $variation >= 0 ? '+' : '';

        return "{$signe}{$variation}% {$label}";
    }

    private function icone(float $actuel, float $precedent): string
    {
        return $actuel >= $precedent
            ? 'heroicon-m-arrow-trending-up'
            : 'heroicon-m-arrow-trending-down';
    }

    private function couleur(float $actuel, float $precedent): string
    {
        return $actuel >= $precedent ? 'success' : 'danger';
    }
}
