<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StatutFacture;
use App\Models\Lot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Service de gestion des lots d'achat.
 *
 * Gère la création des lots, les calculs automatiques (coût, CMP, statut facture),
 * et les règlements partiels ou complets.
 *
 * Règles métier :
 * - cout_total = (prix_unitaire × quantite_recue) + cout_transport + autres_couts
 * - quantite_utilisable = quantite_recue - quantite_morts - quantite_refuses
 * - cout_moyen_unitaire = cout_total / quantite_utilisable
 * - statut_facture : non_reglee → partielle → reglee
 */
class LotService
{
    /**
     * Génère une référence unique pour un lot.
     *
     * Format : LOT-YYYYMMDD-XXX (ex: LOT-20260313-001)
     */
    public function genererReference(?Carbon $date = null): string
    {
        $date ??= today();
        $dateStr = $date->format('Ymd');
        $count = Lot::whereDate('date_reception', $date)->count() + 1;

        return sprintf('LOT-%s-%03d', $dateStr, $count);
    }

    /**
     * Calcule les champs dérivés d'un lot avant création ou mise à jour.
     *
     * Retourne un tableau des valeurs calculées à fusionner dans les données du lot.
     */
    public function calculer(array $data): array
    {
        $quantiteRecue   = (int)   ($data['quantite_recue']   ?? 0);
        $quantiteMorts   = (int)   ($data['quantite_morts']   ?? 0);
        $quantiteRefuses = (int)   ($data['quantite_refuses'] ?? 0);
        $prixUnitaire    = (float) ($data['prix_unitaire']    ?? 0);
        $coutTransport   = (float) ($data['cout_transport']   ?? 0);
        $autresCouts     = (float) ($data['autres_couts']     ?? 0);

        // Quantité réellement utilisable pour la production
        $quantiteUtilisable = max(0, $quantiteRecue - $quantiteMorts - $quantiteRefuses);

        // Coût total d'acquisition du lot
        $coutTotal = ($prixUnitaire * $quantiteRecue) + $coutTransport + $autresCouts;

        // Coût moyen par poulet utilisable
        $coutMoyenUnitaire = $quantiteUtilisable > 0
            ? $coutTotal / $quantiteUtilisable
            : 0;

        return [
            'quantite_utilisable'  => $quantiteUtilisable,
            'cout_total'           => round($coutTotal, 2),
            'cout_moyen_unitaire'  => round($coutMoyenUnitaire, 4),
        ];
    }

    /**
     * Calcule le statut de la facture selon les montants réglés.
     *
     * - non_reglee  : montant_regle == 0
     * - partielle   : 0 < montant_regle < montant_facture
     * - reglee      : montant_regle >= montant_facture
     */
    public function calculerStatutFacture(float $montantFacture, float $montantRegle): StatutFacture
    {
        if ($montantRegle <= 0) {
            return StatutFacture::NON_REGLEE;
        }

        if ($montantRegle < $montantFacture) {
            return StatutFacture::PARTIELLE;
        }

        return StatutFacture::REGLEE;
    }

    /**
     * Crée un lot avec tous les calculs automatiques.
     *
     * Cette méthode est le point d'entrée unique pour créer un lot
     * et garantit la cohérence des données calculées.
     */
    public function creer(array $data, int $createdBy): Lot
    {
        return DB::transaction(function () use ($data, $createdBy) {
            // Calculs automatiques
            $calculs = $this->calculer($data);

            $montantFacture = (float) ($data['montant_facture'] ?? $calculs['cout_total']);
            $montantRegle   = (float) ($data['montant_regle']   ?? 0);

            $statutFacture = $this->calculerStatutFacture($montantFacture, $montantRegle);

            $lot = Lot::create(array_merge($data, $calculs, [
                'reference'      => $data['reference'] ?? $this->genererReference(
                    isset($data['date_reception']) ? Carbon::parse($data['date_reception']) : null
                ),
                'statut_facture' => $statutFacture->value,
                'montant_regle'  => $montantRegle,
                'created_by'     => $createdBy,
            ]));

            return $lot;
        });
    }

    /**
     * Enregistre un règlement partiel ou complet d'un lot.
     *
     * Met à jour montant_regle, date_reglement et statut_facture.
     */
    public function reglement(
        Lot $lot,
        float $montant,
        string $modeReglement,
        ?Carbon $dateReglement = null,
    ): Lot {
        $dateReglement ??= today();
        $nouveauMontantRegle = (float) $lot->montant_regle + $montant;
        $montantFacture      = (float) $lot->montant_facture;

        $statutFacture = $this->calculerStatutFacture($montantFacture, $nouveauMontantRegle);

        $lot->update([
            'montant_regle'  => round($nouveauMontantRegle, 2),
            'statut_facture' => $statutFacture->value,
            'date_reglement' => $statutFacture === StatutFacture::REGLEE ? $dateReglement : $lot->date_reglement,
            'mode_reglement' => $modeReglement,
        ]);

        return $lot->fresh();
    }

    /**
     * Met à jour un lot existant et recalcule les champs dérivés.
     */
    public function mettrAJour(Lot $lot, array $data): Lot
    {
        return DB::transaction(function () use ($lot, $data) {
            $calculs = $this->calculer(array_merge($lot->toArray(), $data));

            $montantFacture = (float) ($data['montant_facture'] ?? $lot->montant_facture);
            $montantRegle   = (float) ($data['montant_regle']   ?? $lot->montant_regle);

            $statutFacture = $this->calculerStatutFacture($montantFacture, $montantRegle);

            $lot->update(array_merge($data, $calculs, [
                'statut_facture' => $statutFacture->value,
            ]));

            return $lot->fresh();
        });
    }

    /**
     * Retourne le nombre de poulets disponibles pour la production.
     *
     * Un poulet est "disponible" s'il n'a pas encore été affecté à une production.
     */
    public function quantiteDisponibleProduction(Lot $lot): int
    {
        // Nombre de poulets déjà utilisés dans les productions
        $utilises = $lot->productions()->sum('nb_poulets_traites');

        return max(0, (int) $lot->quantite_utilisable - (int) $utilises);
    }

    /**
     * Retourne un résumé du lot : coûts, marges estimées, statut.
     */
    public function resume(Lot $lot): array
    {
        return [
            'reference'             => $lot->reference,
            'fournisseur'           => $lot->fournisseur?->nom,
            'quantite_recue'        => $lot->quantite_recue,
            'quantite_morts'        => $lot->quantite_morts,
            'quantite_refuses'      => $lot->quantite_refuses,
            'quantite_utilisable'   => $lot->quantite_utilisable,
            'taux_mortalite'        => $lot->quantite_recue > 0
                ? round(($lot->quantite_morts / $lot->quantite_recue) * 100, 2)
                : 0,
            'cout_total'            => $lot->cout_total,
            'cout_moyen_unitaire'   => $lot->cout_moyen_unitaire,
            'statut_facture'        => $lot->statut_facture,
            'montant_facture'       => $lot->montant_facture,
            'montant_regle'         => $lot->montant_regle,
            'reste_a_payer'         => max(0, (float) $lot->montant_facture - (float) $lot->montant_regle),
            'dispo_production'      => $this->quantiteDisponibleProduction($lot),
        ];
    }
}
