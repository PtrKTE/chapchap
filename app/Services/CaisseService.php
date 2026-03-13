<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StatutCaisse;
use App\Models\Caisse;
use App\Models\Charge;
use App\Models\Paiement;
use App\Models\Vente;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Service de gestion de la caisse journalière.
 *
 * Gère l'ouverture, la clôture, le calcul des totaux,
 * les versements et le rapprochement de caisse.
 *
 * Règles métier :
 * - solde_caisse = total_encaisse + total_credits_encaisses - total_decaisse
 * - ecart = solde_caisse - (montant_verse + depot_wave_mtn + depot_cheque)
 * - Une seule caisse ouverte par jour par emplacement
 */
class CaisseService
{
    /**
     * Ouvre une caisse pour un emplacement et une date donnée.
     *
     * Bloque si une caisse est déjà ouverte pour ce couple (date, emplacement).
     */
    public function ouvrir(int $emplacementId, int $responsableId, ?Carbon $date = null): Caisse
    {
        $date ??= today();

        // Vérifier qu'il n'y a pas déjà une caisse ouverte pour ce jour
        $existante = Caisse::where('emplacement_id', $emplacementId)
            ->whereDate('date_caisse', $date)
            ->whereIn('statut', [StatutCaisse::OUVERTE->value, StatutCaisse::CLOTUREE->value])
            ->first();

        if ($existante) {
            throw new \RuntimeException(
                "Une caisse existe déjà pour cet emplacement le {$date->format('d/m/Y')}."
            );
        }

        return Caisse::create([
            'date_caisse'             => $date,
            'emplacement_id'          => $emplacementId,
            'statut'                  => StatutCaisse::OUVERTE->value,
            'responsable_id'          => $responsableId,
            'total_encaisse'          => 0,
            'total_credits_encaisses' => 0,
            'total_decaisse'          => 0,
            'solde_caisse'            => 0,
            'montant_verse'           => 0,
            'depot_wave_mtn'          => 0,
            'depot_cheque'            => 0,
            'montant_especes'         => 0,
            'ecart'                   => 0,
        ]);
    }

    /**
     * Recalcule et met à jour les totaux d'une caisse à partir des ventes et charges du jour.
     *
     * Appelé automatiquement par VenteObserver et ChargeObserver à chaque opération.
     */
    public function recalculer(Caisse $caisse): Caisse
    {
        return DB::transaction(function () use ($caisse) {
            $date         = $caisse->date_caisse;
            $emplacementId = $caisse->emplacement_id;

            // Total des encaissements (ventes payées ou partiellement payées)
            $totalEncaisse = (float) Vente::where('emplacement_id', $emplacementId)
                ->whereDate('date_vente', $date)
                ->where('annulee', false)
                ->sum('montant_recu');

            // Total des crédits encaissés via la table paiements (règlements de créances)
            $totalCreditsEncaisses = (float) Paiement::whereHas('vente', function ($q) use ($emplacementId, $date) {
                $q->where('emplacement_id', $emplacementId)->where('annulee', false);
            })
            ->whereDate('date_paiement', $date)
            ->sum('montant');

            // Total des décaissements (charges du jour pour cet emplacement)
            $totalDecaisse = (float) Charge::where('emplacement_id', $emplacementId)
                ->whereDate('date_charge', $date)
                ->sum('montant');

            // Solde = encaissé + crédits récupérés - décaissé
            $soldeCaisse = $totalEncaisse + $totalCreditsEncaisses - $totalDecaisse;

            // Écart = solde - (espèces versées + dépôts Wave/MTN + dépôts chèque)
            $ecart = $soldeCaisse
                - (float) $caisse->montant_verse
                - (float) $caisse->depot_wave_mtn
                - (float) $caisse->depot_cheque;

            $caisse->update([
                'total_encaisse'          => round($totalEncaisse, 2),
                'total_credits_encaisses' => round($totalCreditsEncaisses, 2),
                'total_decaisse'          => round($totalDecaisse, 2),
                'solde_caisse'            => round($soldeCaisse, 2),
                'ecart'                   => round($ecart, 2),
            ]);

            return $caisse->fresh();
        });
    }

    /**
     * Enregistre un versement (espèces, Wave, MTN, chèque) et recalcule l'écart.
     */
    public function enregistrerVersement(
        Caisse $caisse,
        float $montantEspeces = 0,
        float $depotWaveMtn = 0,
        float $depotCheque = 0,
    ): Caisse {
        if ($caisse->statut === StatutCaisse::VALIDEE->value) {
            throw new \RuntimeException('Impossible de modifier une caisse déjà validée.');
        }

        $totalVerse = $montantEspeces + $depotWaveMtn + $depotCheque;
        $solde = (float) $caisse->solde_caisse;
        $ecart = $solde - $totalVerse;

        $caisse->update([
            'montant_verse'   => round($montantEspeces, 2),
            'depot_wave_mtn'  => round($depotWaveMtn, 2),
            'depot_cheque'    => round($depotCheque, 2),
            'montant_especes' => round($montantEspeces, 2),
            'ecart'           => round($ecart, 2),
        ]);

        return $caisse->fresh();
    }

    /**
     * Clôture une caisse (passage en statut CLOTUREE).
     *
     * Déclenche un recalcul final avant clôture.
     * Une caisse clôturée peut encore être validée par le gérant.
     */
    public function cloturer(Caisse $caisse): Caisse
    {
        if ($caisse->statut !== StatutCaisse::OUVERTE->value) {
            throw new \RuntimeException('Seule une caisse ouverte peut être clôturée.');
        }

        // Recalcul final avant clôture
        $this->recalculer($caisse);
        $caisse->refresh();

        $caisse->update(['statut' => StatutCaisse::CLOTUREE->value]);

        return $caisse->fresh();
    }

    /**
     * Valide une caisse clôturée (réservé au gérant ou responsable des opérations).
     */
    public function valider(Caisse $caisse, int $validateurId): Caisse
    {
        if ($caisse->statut !== StatutCaisse::CLOTUREE->value) {
            throw new \RuntimeException('Seule une caisse clôturée peut être validée.');
        }

        $caisse->update([
            'statut'     => StatutCaisse::VALIDEE->value,
            'valide_par' => $validateurId,
        ]);

        return $caisse->fresh();
    }

    /**
     * Récupère ou crée la caisse du jour pour un emplacement.
     *
     * Utile pour s'assurer qu'une caisse existe avant d'y imputer une opération.
     */
    public function getCaisseOuverte(int $emplacementId, ?Carbon $date = null): ?Caisse
    {
        $date ??= today();

        return Caisse::where('emplacement_id', $emplacementId)
            ->whereDate('date_caisse', $date)
            ->where('statut', StatutCaisse::OUVERTE->value)
            ->first();
    }

    /**
     * Calcule un résumé de trésorerie sur une période.
     *
     * Retourne : total encaissé, total décaissé, solde net, par mode de paiement.
     */
    public function resumePeriode(
        Carbon $dateDebut,
        Carbon $dateFin,
        ?int $emplacementId = null,
    ): array {
        $query = Caisse::whereBetween('date_caisse', [$dateDebut, $dateFin]);

        if ($emplacementId) {
            $query->where('emplacement_id', $emplacementId);
        }

        $totaux = $query->selectRaw(
            'SUM(total_encaisse) as encaisse,
             SUM(total_credits_encaisses) as credits,
             SUM(total_decaisse) as decaisse,
             SUM(solde_caisse) as solde,
             SUM(depot_wave_mtn) as wave_mtn,
             SUM(depot_cheque) as cheque,
             SUM(montant_especes) as especes'
        )->first();

        return [
            'total_encaisse'  => round((float) $totaux?->encaisse, 2),
            'total_credits'   => round((float) $totaux?->credits, 2),
            'total_decaisse'  => round((float) $totaux?->decaisse, 2),
            'solde_net'       => round((float) $totaux?->solde, 2),
            'par_mode'        => [
                'especes'  => round((float) $totaux?->especes, 2),
                'wave_mtn' => round((float) $totaux?->wave_mtn, 2),
                'cheque'   => round((float) $totaux?->cheque, 2),
            ],
        ];
    }
}
