<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TypeMouvement;
use App\Models\MouvementStock;
use App\Models\StockEmplacement;
use Illuminate\Support\Facades\DB;

/**
 * Service central de gestion des stocks.
 *
 * Gère les entrées, sorties, le calcul du CMP (Coût Moyen Pondéré)
 * et la création des mouvements de stock.
 *
 * Utilisé par : Production, Ventes, Transferts, Inventaires.
 */
class StockService
{
    /**
     * Enregistre une entrée en stock (production, achat, transfert entrant, ajustement+).
     *
     * Met à jour le CMP selon la formule :
     * Nouveau CMP = (ancien_stock × ancien_CMP + quantité_entrante × coût_entrée) / (ancien_stock + quantité_entrante)
     */
    public function entree(
        int $produitId,
        int $emplacementId,
        float $quantite,
        float $coutUnitaire,
        TypeMouvement $typeMouvement,
        ?int $lotId = null,
        ?int $productionId = null,
        ?int $venteId = null,
        ?int $transfertId = null,
        ?string $motif = null,
    ): MouvementStock {
        return DB::transaction(function () use (
            $produitId, $emplacementId, $quantite, $coutUnitaire,
            $typeMouvement, $lotId, $productionId, $venteId, $transfertId, $motif,
        ) {
            // Récupérer ou créer la ligne stock_emplacements
            $stock = StockEmplacement::firstOrCreate(
                ['produit_id' => $produitId, 'emplacement_id' => $emplacementId],
                ['quantite' => 0, 'cout_moyen_pondere' => 0, 'valeur_stock' => 0],
            );

            // Calcul du nouveau CMP
            $ancienStock = (float) $stock->quantite;
            $ancienCMP = (float) $stock->cout_moyen_pondere;
            $nouveauStock = $ancienStock + $quantite;

            $nouveauCMP = $nouveauStock > 0
                ? (($ancienStock * $ancienCMP) + ($quantite * $coutUnitaire)) / $nouveauStock
                : $coutUnitaire;

            // Mise à jour du stock
            $stock->update([
                'quantite' => round($nouveauStock, 3),
                'cout_moyen_pondere' => round($nouveauCMP, 4),
                'valeur_stock' => round($nouveauStock * $nouveauCMP, 2),
                'derniere_entree' => now(),
            ]);

            // Création du mouvement
            return $this->creerMouvement(
                produitId: $produitId,
                emplacementId: $emplacementId,
                quantite: $quantite,
                coutUnitaire: $coutUnitaire,
                typeMouvement: $typeMouvement,
                lotId: $lotId,
                productionId: $productionId,
                venteId: $venteId,
                transfertId: $transfertId,
                motif: $motif,
            );
        });
    }

    /**
     * Enregistre une sortie de stock (vente, perte, transfert sortant, ajustement-).
     *
     * Le coût unitaire utilisé est le CMP actuel du produit à cet emplacement.
     * Lève une exception si le stock est insuffisant.
     */
    public function sortie(
        int $produitId,
        int $emplacementId,
        float $quantite,
        TypeMouvement $typeMouvement,
        ?int $venteId = null,
        ?int $transfertId = null,
        ?string $motif = null,
    ): MouvementStock {
        return DB::transaction(function () use (
            $produitId, $emplacementId, $quantite,
            $typeMouvement, $venteId, $transfertId, $motif,
        ) {
            $stock = StockEmplacement::where('produit_id', $produitId)
                ->where('emplacement_id', $emplacementId)
                ->lockForUpdate()
                ->first();

            if (! $stock || (float) $stock->quantite < $quantite) {
                $qteDisponible = $stock ? (float) $stock->quantite : 0;
                throw new \RuntimeException(
                    "Stock insuffisant. Disponible : {$qteDisponible}, demandé : {$quantite}"
                );
            }

            $cmp = (float) $stock->cout_moyen_pondere;
            $nouveauStock = (float) $stock->quantite - $quantite;

            $stock->update([
                'quantite' => round(max(0, $nouveauStock), 3),
                'valeur_stock' => round(max(0, $nouveauStock) * $cmp, 2),
                'derniere_sortie' => now(),
            ]);

            return $this->creerMouvement(
                produitId: $produitId,
                emplacementId: $emplacementId,
                quantite: $quantite,
                coutUnitaire: $cmp,
                typeMouvement: $typeMouvement,
                venteId: $venteId,
                transfertId: $transfertId,
                motif: $motif,
            );
        });
    }

    /**
     * Récupère le CMP actuel d'un produit à un emplacement donné.
     */
    public function getCMP(int $produitId, int $emplacementId): float
    {
        $stock = StockEmplacement::where('produit_id', $produitId)
            ->where('emplacement_id', $emplacementId)
            ->first();

        return $stock ? (float) $stock->cout_moyen_pondere : 0;
    }

    /**
     * Récupère la quantité disponible d'un produit à un emplacement.
     */
    public function getQuantiteDisponible(int $produitId, int $emplacementId): float
    {
        $stock = StockEmplacement::where('produit_id', $produitId)
            ->where('emplacement_id', $emplacementId)
            ->first();

        return $stock ? (float) $stock->quantite : 0;
    }

    /**
     * Effectue un transfert de stock entre deux emplacements.
     *
     * Génère 2 mouvements :
     * - transfert_sortie sur l'emplacement source
     * - transfert_entree sur l'emplacement destination (au CMP de la source)
     *
     * Utilisé lors de la réception d'un transfert inter-sites.
     */
    public function transfert(
        int $produitId,
        int $emplacementSourceId,
        int $emplacementDestId,
        float $quantiteEnvoyee,
        float $quantiteRecue,
        int $transfertId,
    ): void {
        DB::transaction(function () use (
            $produitId, $emplacementSourceId, $emplacementDestId,
            $quantiteEnvoyee, $quantiteRecue, $transfertId,
        ) {
            // Le CMP de la source est utilisé pour valoriser le transfert
            $cmp = $this->getCMP($produitId, $emplacementSourceId);

            // 1. Sortie sur l'emplacement source (quantité envoyée)
            $this->sortie(
                produitId: $produitId,
                emplacementId: $emplacementSourceId,
                quantite: $quantiteEnvoyee,
                typeMouvement: TypeMouvement::TRANSFERT_SORTIE,
                transfertId: $transfertId,
            );

            // 2. Entrée sur l'emplacement destination (quantité effectivement reçue)
            $this->entree(
                produitId: $produitId,
                emplacementId: $emplacementDestId,
                quantite: $quantiteRecue,
                coutUnitaire: $cmp,
                typeMouvement: TypeMouvement::TRANSFERT_ENTREE,
                transfertId: $transfertId,
            );

            // Si écart (reçu < envoyé), on enregistre la perte
            $ecart = $quantiteEnvoyee - $quantiteRecue;
            if ($ecart > 0.001) {
                $this->creerMouvement(
                    produitId: $produitId,
                    emplacementId: $emplacementSourceId,
                    quantite: $ecart,
                    coutUnitaire: $cmp,
                    typeMouvement: TypeMouvement::SORTIE_PERTE,
                    transfertId: $transfertId,
                    motif: 'Écart de transfert (perte en transit)',
                );
            }
        });
    }

    /**
     * Effectue un ajustement de stock suite à un inventaire.
     *
     * Si écart positif (physique > théorique) → ajustement_plus (entrée)
     * Si écart négatif (physique < théorique) → ajustement_moins (sortie)
     */
    public function ajustementInventaire(
        int $produitId,
        int $emplacementId,
        float $ecart,
        ?string $motif = null,
    ): ?MouvementStock {
        if (abs($ecart) < 0.001) {
            return null; // Pas d'écart significatif, rien à faire
        }

        $cmp = $this->getCMP($produitId, $emplacementId);

        if ($ecart > 0) {
            // Stock physique > stock théorique → on ajoute
            return $this->entree(
                produitId: $produitId,
                emplacementId: $emplacementId,
                quantite: $ecart,
                coutUnitaire: $cmp > 0 ? $cmp : 0,
                typeMouvement: TypeMouvement::AJUSTEMENT_PLUS,
                motif: $motif ?? 'Ajustement inventaire (+)',
            );
        }

        // Stock physique < stock théorique → on retire
        return $this->sortie(
            produitId: $produitId,
            emplacementId: $emplacementId,
            quantite: abs($ecart),
            typeMouvement: TypeMouvement::AJUSTEMENT_MOINS,
            motif: $motif ?? 'Ajustement inventaire (-)',
        );
    }

    /**
     * Génère une référence unique pour un mouvement de stock.
     */
    protected function genererReference(): string
    {
        $date = now()->format('Ymd');
        $count = MouvementStock::whereDate('date_mouvement', today())->count() + 1;

        return sprintf('MVT-%s-%04d', $date, $count);
    }

    /**
     * Crée l'enregistrement du mouvement de stock.
     */
    protected function creerMouvement(
        int $produitId,
        int $emplacementId,
        float $quantite,
        float $coutUnitaire,
        TypeMouvement $typeMouvement,
        ?int $lotId = null,
        ?int $productionId = null,
        ?int $venteId = null,
        ?int $transfertId = null,
        ?string $motif = null,
    ): MouvementStock {
        return MouvementStock::create([
            'reference' => $this->genererReference(),
            'date_mouvement' => now(),
            'produit_id' => $produitId,
            'emplacement_id' => $emplacementId,
            'type_mouvement' => $typeMouvement->value,
            'quantite' => round($quantite, 3),
            'cout_unitaire' => round($coutUnitaire, 4),
            'valeur' => round($quantite * $coutUnitaire, 2),
            'lot_id' => $lotId,
            'production_id' => $productionId,
            'vente_id' => $venteId,
            'transfert_id' => $transfertId,
            'motif' => $motif,
            'created_by' => auth()->id() ?? 1,
        ]);
    }
}
