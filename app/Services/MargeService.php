<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Lot;
use App\Models\Vente;
use App\Models\VenteLigne;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Service de calcul des marges.
 *
 * Calcule la marge brute par vente, par lot, par produit et par période.
 * La marge = prix de vente - coût de revient (CMP au moment de la vente).
 *
 * Utilisé par : Dashboards, Rapports, VenteResource.
 */
class MargeService
{
    /**
     * Calcule la marge brute d'une vente (somme des marges de ses lignes).
     *
     * Formule : marge_vente = somme(marge_ligne) = somme(montant_ligne - qté × coût_revient)
     */
    public function margeVente(Vente $vente): float
    {
        return (float) $vente->venteLignes->sum(function (VenteLigne $ligne) {
            return (float) $ligne->marge_ligne;
        });
    }

    /**
     * Calcule le taux de marge d'une vente en pourcentage.
     *
     * Formule : taux = (marge / montant_net) × 100
     */
    public function tauxMargeVente(Vente $vente): float
    {
        $montantNet = (float) $vente->montant_net;

        if ($montantNet <= 0) {
            return 0.0;
        }

        return round(($this->margeVente($vente) / $montantNet) * 100, 2);
    }

    /**
     * Calcule la marge totale d'un lot.
     *
     * Marge lot = somme des marges de toutes les ventes issues des productions de ce lot,
     * diminuée du coût total d'acquisition du lot.
     *
     * Cette méthode fait un calcul approximatif basé sur les lignes de vente
     * dont le coût de revient est tracé au moment de la vente.
     */
    public function margeLot(Lot $lot): array
    {
        // Chiffre d'affaires généré par les ventes des produits issus de ce lot
        $ca = (float) VenteLigne::whereHas('vente', fn ($q) => $q->where('annulee', false))
            ->whereHas('vente.mouvementsStock', fn ($q) => $q->where('lot_id', $lot->id))
            ->sum('montant_ligne');

        // Coût total du lot
        $coutLot = (float) $lot->cout_total;

        // Marge brute estimée
        $marge = $ca - $coutLot;
        $taux = $ca > 0 ? round(($marge / $ca) * 100, 2) : 0;

        return [
            'ca'        => round($ca, 2),
            'cout'      => round($coutLot, 2),
            'marge'     => round($marge, 2),
            'taux'      => $taux,
        ];
    }

    /**
     * Calcule la marge par produit sur une période donnée.
     *
     * Retourne un tableau trié par marge décroissante :
     * [produit_id, nom_produit, ca, cout_revient_total, marge, taux_marge]
     */
    public function margeParProduit(
        \Carbon\Carbon $dateDebut,
        \Carbon\Carbon $dateFin,
        ?int $emplacementId = null,
    ): Collection {
        $query = VenteLigne::select([
                'vente_lignes.produit_id',
                DB::raw('SUM(vente_lignes.montant_ligne) as ca'),
                DB::raw('SUM(vente_lignes.quantite * vente_lignes.cout_revient) as cout_total'),
                DB::raw('SUM(vente_lignes.marge_ligne) as marge_totale'),
            ])
            ->join('ventes', 'ventes.id', '=', 'vente_lignes.vente_id')
            ->join('produits', 'produits.id', '=', 'vente_lignes.produit_id')
            ->where('ventes.annulee', false)
            ->whereBetween('ventes.date_vente', [$dateDebut, $dateFin])
            ->groupBy('vente_lignes.produit_id');

        if ($emplacementId) {
            $query->where('ventes.emplacement_id', $emplacementId);
        }

        return $query->get()->map(function ($row) {
            $ca = (float) $row->ca;
            return [
                'produit_id'  => $row->produit_id,
                'ca'          => round($ca, 2),
                'cout_total'  => round((float) $row->cout_total, 2),
                'marge'       => round((float) $row->marge_totale, 2),
                'taux'        => $ca > 0 ? round(((float) $row->marge_totale / $ca) * 100, 2) : 0,
            ];
        })->sortByDesc('marge')->values();
    }

    /**
     * Calcule la marge globale sur une période.
     *
     * Retourne : CA total, coût total, marge brute, taux de marge.
     */
    public function margePeriode(
        \Carbon\Carbon $dateDebut,
        \Carbon\Carbon $dateFin,
        ?int $emplacementId = null,
    ): array {
        $query = VenteLigne::join('ventes', 'ventes.id', '=', 'vente_lignes.vente_id')
            ->where('ventes.annulee', false)
            ->whereBetween('ventes.date_vente', [$dateDebut, $dateFin]);

        if ($emplacementId) {
            $query->where('ventes.emplacement_id', $emplacementId);
        }

        $ca    = (float) $query->sum('vente_lignes.montant_ligne');
        $cout  = (float) $query->sum(DB::raw('vente_lignes.quantite * vente_lignes.cout_revient'));
        $marge = $ca - $cout;

        return [
            'ca'    => round($ca, 2),
            'cout'  => round($cout, 2),
            'marge' => round($marge, 2),
            'taux'  => $ca > 0 ? round(($marge / $ca) * 100, 2) : 0,
        ];
    }

    /**
     * Calcule la marge par commercial sur une période.
     *
     * Retourne un tableau [commercial_id, nom, ca, marge, taux_marge, nb_ventes].
     */
    public function margeParCommercial(
        \Carbon\Carbon $dateDebut,
        \Carbon\Carbon $dateFin,
    ): Collection {
        return DB::table('vente_lignes')
            ->join('ventes', 'ventes.id', '=', 'vente_lignes.vente_id')
            ->join('users', 'users.id', '=', 'ventes.commercial_id')
            ->where('ventes.annulee', false)
            ->whereBetween('ventes.date_vente', [$dateDebut, $dateFin])
            ->whereNotNull('ventes.commercial_id')
            ->select([
                'ventes.commercial_id',
                'users.nom',
                DB::raw('SUM(vente_lignes.montant_ligne) as ca'),
                DB::raw('SUM(vente_lignes.marge_ligne) as marge'),
                DB::raw('COUNT(DISTINCT ventes.id) as nb_ventes'),
            ])
            ->groupBy('ventes.commercial_id', 'users.nom')
            ->get()
            ->map(function ($row) {
                $ca = (float) $row->ca;
                return [
                    'commercial_id' => $row->commercial_id,
                    'nom'           => $row->nom,
                    'ca'            => round($ca, 2),
                    'marge'         => round((float) $row->marge, 2),
                    'taux'          => $ca > 0 ? round(((float) $row->marge / $ca) * 100, 2) : 0,
                    'nb_ventes'     => $row->nb_ventes,
                ];
            })
            ->sortByDesc('ca')
            ->values();
    }
}
