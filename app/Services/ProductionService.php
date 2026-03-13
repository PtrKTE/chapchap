<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StatutProduction;
use App\Enums\TypeMouvement;
use App\Models\GrilleRendement;
use App\Models\Production;
use App\Models\ProductionLigne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Service de gestion de la production (abattage et découpe).
 *
 * Gère la création, la validation des sessions de production,
 * le calcul du rendement et la génération automatique des entrées en stock.
 *
 * Règles métier :
 * - Interdit de traiter plus de poulets que la quantite_utilisable du lot
 * - Validation = génère les mouvements de stock (entree_production) via StockService
 * - Rendement = somme(sorties valorisées) / (nb_poulets × cout_moyen_unitaire du lot)
 */
class ProductionService
{
    public function __construct(
        private readonly StockService $stockService,
    ) {}

    /**
     * Génère une référence unique pour une session de production.
     *
     * Format : PROD-YYYYMMDD-XXX (ex: PROD-20260313-001)
     */
    public function genererReference(?Carbon $date = null): string
    {
        $date ??= today();
        $dateStr = $date->format('Ymd');
        $count = Production::whereDate('date_production', $date)->count() + 1;

        return sprintf('PROD-%s-%03d', $dateStr, $count);
    }

    /**
     * Crée une session de production en mode "en_cours".
     *
     * Vérifie que le nombre de poulets à traiter ne dépasse pas
     * la quantité disponible dans le lot source.
     */
    public function creer(array $data, int $createdBy): Production
    {
        return DB::transaction(function () use ($data, $createdBy) {
            $lot = \App\Models\Lot::findOrFail($data['lot_id']);

            // Vérification : assez de poulets disponibles dans ce lot ?
            $dejaTraites  = $lot->productions()
                ->whereIn('statut', [StatutProduction::EN_COURS->value, StatutProduction::VALIDEE->value])
                ->sum('nb_poulets_traites');

            $disponibles  = (int) $lot->quantite_utilisable - (int) $dejaTraites;
            $nbATraiter   = (int) ($data['nb_poulets_traites'] ?? 0);

            if ($nbATraiter > $disponibles) {
                throw new \RuntimeException(
                    "Stock insuffisant : {$disponibles} poulets disponibles dans le lot {$lot->reference}, {$nbATraiter} demandés."
                );
            }

            return Production::create(array_merge($data, [
                'reference'  => $this->genererReference(
                    isset($data['date_production']) ? Carbon::parse($data['date_production']) : null
                ),
                'statut'     => StatutProduction::EN_COURS->value,
                'created_by' => $createdBy,
            ]));
        });
    }

    /**
     * Valide une production et génère les entrées en stock.
     *
     * C'est l'étape critique : chaque ligne de production (produit + quantité)
     * génère un mouvement "entree_production" dans le stock de l'emplacement cible.
     *
     * Le coût unitaire de chaque produit issu de la production est calculé
     * à partir du coût moyen du lot source.
     */
    public function valider(Production $production, int $validateurId, int $emplacementId): Production
    {
        if ($production->statut !== StatutProduction::EN_COURS->value) {
            throw new \RuntimeException('Seule une production en cours peut être validée.');
        }

        return DB::transaction(function () use ($production, $validateurId, $emplacementId) {
            $lot = $production->lot;
            $coutMoyenLot = (float) $lot->cout_moyen_unitaire;

            // Coût total affecté à cette production
            $coutTotalProduction = $coutMoyenLot * $production->nb_poulets_traites;

            // Poids total produit (somme des lignes)
            $poidsTotalProduit = (float) $production->productionLignes->sum('quantite');

            // Coût unitaire par kg de produit fini
            $coutParKg = $poidsTotalProduit > 0
                ? $coutTotalProduction / $poidsTotalProduit
                : 0;

            // Mise à jour du coût unitaire calculé sur chaque ligne + génération stock
            foreach ($production->productionLignes as $ligne) {
                $valeurLigne = round((float) $ligne->quantite * $coutParKg, 2);

                // Mise à jour de la ligne de production
                $ligne->update([
                    'cout_unitaire_calcule' => round($coutParKg, 4),
                    'valeur_totale'         => $valeurLigne,
                ]);

                // Génération du mouvement de stock : entree_production
                $this->stockService->entree(
                    produitId:      $ligne->produit_id,
                    emplacementId:  $emplacementId,
                    quantite:       (float) $ligne->quantite,
                    coutUnitaire:   $coutParKg,
                    typeMouvement:  TypeMouvement::ENTREE_PRODUCTION,
                    lotId:          $lot->id,
                    productionId:   $production->id,
                );
            }

            // Calcul du rendement : valeur produite / coût matière
            $valeurTotaleProduite = (float) $production->productionLignes->sum('valeur_totale');
            $rendement = $coutTotalProduction > 0
                ? round($valeurTotaleProduite / $coutTotalProduction, 4)
                : null;

            // Validation de la production
            $production->update([
                'statut'          => StatutProduction::VALIDEE->value,
                'rendement'       => $rendement,
                'valide_par'      => $validateurId,
                'date_validation' => now(),
            ]);

            return $production->fresh();
        });
    }

    /**
     * Annule une production en cours (impossible si déjà validée).
     *
     * N'annule PAS les mouvements de stock si la production était déjà validée.
     */
    public function annuler(Production $production, string $motif = ''): Production
    {
        if ($production->statut === StatutProduction::VALIDEE->value) {
            throw new \RuntimeException(
                'Une production validée ne peut pas être annulée. Créez un mouvement correctif.'
            );
        }

        $production->update([
            'statut'      => StatutProduction::ANNULEE->value,
            'observations' => trim(($production->observations ?? '') . "\nAnnulation : {$motif}"),
        ]);

        return $production->fresh();
    }

    /**
     * Pré-remplit les lignes de production à partir de la grille de rendement standard.
     *
     * Utilise le poids moyen des poulets du lot pour trouver la grille correspondante.
     * Retourne un tableau de lignes suggérées [produit_id, quantite_estimee].
     */
    public function suggererLignesDepuisGrille(int $lotId, int $nbPoulets): Collection
    {
        $lot = \App\Models\Lot::findOrFail($lotId);

        // Utiliser le poids moyen déclaré ou le poids par défaut du lot
        $poidsMoyen = (float) ($lot->poids_moyen ?? 1.8);

        // Chercher la grille la plus proche du poids moyen
        $grilles = GrilleRendement::where('poids_poulet_kg', '<=', $poidsMoyen)
            ->orderByDesc('poids_poulet_kg')
            ->get();

        if ($grilles->isEmpty()) {
            return collect([]);
        }

        // Regrouper par produit et calculer la quantité estimée pour le nombre de poulets
        return $grilles->map(function (GrilleRendement $grille) use ($nbPoulets) {
            return [
                'produit_id'       => $grille->produit_id,
                'quantite_estimee' => round((float) $grille->poids_produit_kg * $nbPoulets, 3),
                'valorisation'     => round((float) $grille->valorisation * $nbPoulets, 2),
            ];
        });
    }

    /**
     * Calcule les statistiques de rendement d'une production.
     */
    public function statistiques(Production $production): array
    {
        $lot = $production->lot;
        $coutMatiere = (float) $lot->cout_moyen_unitaire * $production->nb_poulets_traites;
        $valeurProduite = (float) $production->productionLignes->sum('valeur_totale');
        $poidsProduit = (float) $production->productionLignes->sum('quantite');

        return [
            'reference'          => $production->reference,
            'nb_poulets_traites' => $production->nb_poulets_traites,
            'cout_matiere'       => round($coutMatiere, 2),
            'valeur_produite'    => round($valeurProduite, 2),
            'poids_total_kg'     => round($poidsProduit, 3),
            'rendement'          => $production->rendement,
            'rendement_pct'      => $production->rendement
                ? round((float) $production->rendement * 100, 2) . '%'
                : null,
            'pertes_casse_kg'    => $production->pertes_casse,
            'lignes'             => $production->productionLignes->map(fn ($l) => [
                'produit'   => $l->produit?->nom,
                'quantite'  => $l->quantite,
                'cout_kg'   => $l->cout_unitaire_calcule,
                'valeur'    => $l->valeur_totale,
            ]),
        ];
    }
}
