<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TypeMouvement;
use App\Models\Emplacement;
use App\Models\MouvementStock;
use App\Models\Produit;
use App\Models\StockEmplacement;
use App\Models\Transfert;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests du StockService — logique métier la plus critique de CHAPCHAP.
 *
 * Teste le calcul du CMP (Coût Moyen Pondéré), les entrées/sorties,
 * les transferts, les ajustements d'inventaire et les cas limites.
 */
class StockServiceTest extends TestCase
{
    use RefreshDatabase;

    private StockService $stockService;
    private Produit $produit;
    private Emplacement $emplacementSite;
    private Emplacement $emplacementBelleville;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stockService = new StockService();

        // Créer un utilisateur de test et l'authentifier
        $this->user = User::create([
            'name' => 'Testeur',
            'email' => 'test@chapchap.ci',
            'password' => bcrypt('password'),
            'actif' => true,
        ]);
        $this->actingAs($this->user);

        // Créer un produit de test
        $this->produit = Produit::create([
            'code' => 'TEST01',
            'nom' => 'Produit Test',
            'categorie' => 'decoupe',
            'unite_stock' => 'kg',
            'unite_vente' => 'kg',
            'prix_vente_defaut' => 3000,
            'seuil_alerte_stock' => 5,
            'actif' => true,
        ]);

        // Créer deux emplacements
        $this->emplacementSite = Emplacement::create([
            'nom' => 'Site Principal Test',
            'type' => 'site',
            'actif' => true,
        ]);

        $this->emplacementBelleville = Emplacement::create([
            'nom' => 'Belleville Test',
            'type' => 'point_de_vente',
            'actif' => true,
        ]);
    }

    // =========================================================
    // ENTRÉES DE STOCK
    // =========================================================

    public function test_entree_stock_premiere_fois(): void
    {
        // Première entrée : 10 kg à 1500 FCFA/kg
        $mouvement = $this->stockService->entree(
            produitId: $this->produit->id,
            emplacementId: $this->emplacementSite->id,
            quantite: 10,
            coutUnitaire: 1500,
            typeMouvement: TypeMouvement::ENTREE_PRODUCTION,
        );

        // Vérifier le mouvement créé
        $this->assertInstanceOf(MouvementStock::class, $mouvement);
        $this->assertEquals(10, (float) $mouvement->quantite);
        $this->assertEquals(1500, (float) $mouvement->cout_unitaire);
        $this->assertEquals('entree_production', $mouvement->type_mouvement->value);

        // Vérifier le stock
        $stock = StockEmplacement::where('produit_id', $this->produit->id)
            ->where('emplacement_id', $this->emplacementSite->id)
            ->first();

        $this->assertNotNull($stock);
        $this->assertEquals(10, (float) $stock->quantite);
        $this->assertEquals(1500, (float) $stock->cout_moyen_pondere);
        $this->assertEquals(15000, (float) $stock->valeur_stock);
    }

    public function test_entree_stock_calcul_cmp(): void
    {
        // 1ère entrée : 10 kg à 1500 FCFA/kg → CMP = 1500
        $this->stockService->entree(
            produitId: $this->produit->id,
            emplacementId: $this->emplacementSite->id,
            quantite: 10,
            coutUnitaire: 1500,
            typeMouvement: TypeMouvement::ENTREE_PRODUCTION,
        );

        // 2e entrée : 5 kg à 2100 FCFA/kg
        // Nouveau CMP = (10 × 1500 + 5 × 2100) / (10 + 5) = 25500 / 15 = 1700
        $this->stockService->entree(
            produitId: $this->produit->id,
            emplacementId: $this->emplacementSite->id,
            quantite: 5,
            coutUnitaire: 2100,
            typeMouvement: TypeMouvement::ENTREE_PRODUCTION,
        );

        $stock = StockEmplacement::where('produit_id', $this->produit->id)
            ->where('emplacement_id', $this->emplacementSite->id)
            ->first();

        $this->assertEquals(15, (float) $stock->quantite);
        $this->assertEquals(1700, (float) $stock->cout_moyen_pondere);
        $this->assertEquals(25500, (float) $stock->valeur_stock);
    }

    public function test_entree_stock_cmp_avec_trois_entrees(): void
    {
        // Simulation réelle : 3 lots à des prix différents
        // Lot 1 : 20 kg à 1200
        $this->stockService->entree(
            produitId: $this->produit->id,
            emplacementId: $this->emplacementSite->id,
            quantite: 20,
            coutUnitaire: 1200,
            typeMouvement: TypeMouvement::ENTREE_PRODUCTION,
        );

        // Lot 2 : 10 kg à 1500
        // CMP = (20×1200 + 10×1500) / 30 = 39000 / 30 = 1300
        $this->stockService->entree(
            produitId: $this->produit->id,
            emplacementId: $this->emplacementSite->id,
            quantite: 10,
            coutUnitaire: 1500,
            typeMouvement: TypeMouvement::ENTREE_PRODUCTION,
        );

        // Lot 3 : 15 kg à 1800
        // CMP = (30×1300 + 15×1800) / 45 = (39000+27000) / 45 = 66000 / 45 ≈ 1466.6667
        $this->stockService->entree(
            produitId: $this->produit->id,
            emplacementId: $this->emplacementSite->id,
            quantite: 15,
            coutUnitaire: 1800,
            typeMouvement: TypeMouvement::ENTREE_PRODUCTION,
        );

        $stock = StockEmplacement::where('produit_id', $this->produit->id)
            ->where('emplacement_id', $this->emplacementSite->id)
            ->first();

        $this->assertEquals(45, (float) $stock->quantite);
        $this->assertEqualsWithDelta(1466.6667, (float) $stock->cout_moyen_pondere, 0.001);
    }

    // =========================================================
    // SORTIES DE STOCK
    // =========================================================

    public function test_sortie_stock_normale(): void
    {
        // Entrée : 10 kg à 1500
        $this->stockService->entree(
            produitId: $this->produit->id,
            emplacementId: $this->emplacementSite->id,
            quantite: 10,
            coutUnitaire: 1500,
            typeMouvement: TypeMouvement::ENTREE_PRODUCTION,
        );

        // Sortie : 3 kg → il doit rester 7 kg au CMP 1500
        $mouvement = $this->stockService->sortie(
            produitId: $this->produit->id,
            emplacementId: $this->emplacementSite->id,
            quantite: 3,
            typeMouvement: TypeMouvement::SORTIE_VENTE,
        );

        $this->assertEquals(3, (float) $mouvement->quantite);
        $this->assertEquals(1500, (float) $mouvement->cout_unitaire);

        $stock = StockEmplacement::where('produit_id', $this->produit->id)
            ->where('emplacement_id', $this->emplacementSite->id)
            ->first();

        $this->assertEquals(7, (float) $stock->quantite);
        // Le CMP ne change pas lors d'une sortie
        $this->assertEquals(1500, (float) $stock->cout_moyen_pondere);
        $this->assertEquals(10500, (float) $stock->valeur_stock);
    }

    public function test_sortie_stock_insuffisant_leve_exception(): void
    {
        // Entrée : 5 kg
        $this->stockService->entree(
            produitId: $this->produit->id,
            emplacementId: $this->emplacementSite->id,
            quantite: 5,
            coutUnitaire: 1500,
            typeMouvement: TypeMouvement::ENTREE_PRODUCTION,
        );

        // Sortie : 10 kg → doit lever une exception
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Stock insuffisant');

        $this->stockService->sortie(
            produitId: $this->produit->id,
            emplacementId: $this->emplacementSite->id,
            quantite: 10,
            typeMouvement: TypeMouvement::SORTIE_VENTE,
        );
    }

    public function test_sortie_stock_inexistant_leve_exception(): void
    {
        // Aucune entrée, stock = 0
        $this->expectException(\RuntimeException::class);

        $this->stockService->sortie(
            produitId: $this->produit->id,
            emplacementId: $this->emplacementSite->id,
            quantite: 1,
            typeMouvement: TypeMouvement::SORTIE_VENTE,
        );
    }

    // =========================================================
    // TRANSFERTS
    // =========================================================

    /**
     * Crée un vrai enregistrement Transfert en BDD pour satisfaire la FK.
     */
    private function creerTransfert(): Transfert
    {
        return Transfert::create([
            'reference' => 'TRF-TEST-' . uniqid(),
            'date_transfert' => now(),
            'emplacement_source_id' => $this->emplacementSite->id,
            'emplacement_dest_id' => $this->emplacementBelleville->id,
            'statut' => 'en_cours',
            'created_by' => $this->user->id,
        ]);
    }

    public function test_transfert_complet_sans_perte(): void
    {
        // Mettre du stock sur le site
        $this->stockService->entree(
            produitId: $this->produit->id,
            emplacementId: $this->emplacementSite->id,
            quantite: 20,
            coutUnitaire: 1500,
            typeMouvement: TypeMouvement::ENTREE_PRODUCTION,
        );

        $transfert = $this->creerTransfert();

        // Transférer 8 kg vers Belleville (tout reçu)
        $this->stockService->transfert(
            produitId: $this->produit->id,
            emplacementSourceId: $this->emplacementSite->id,
            emplacementDestId: $this->emplacementBelleville->id,
            quantiteEnvoyee: 8,
            quantiteRecue: 8,
            transfertId: $transfert->id,
        );

        // Site : 20 - 8 = 12 kg
        $stockSite = StockEmplacement::where('produit_id', $this->produit->id)
            ->where('emplacement_id', $this->emplacementSite->id)
            ->first();
        $this->assertEquals(12, (float) $stockSite->quantite);

        // Belleville : 8 kg au CMP du site (1500)
        $stockBelleville = StockEmplacement::where('produit_id', $this->produit->id)
            ->where('emplacement_id', $this->emplacementBelleville->id)
            ->first();
        $this->assertEquals(8, (float) $stockBelleville->quantite);
        $this->assertEquals(1500, (float) $stockBelleville->cout_moyen_pondere);
    }

    public function test_transfert_avec_perte(): void
    {
        // Mettre du stock
        $this->stockService->entree(
            produitId: $this->produit->id,
            emplacementId: $this->emplacementSite->id,
            quantite: 20,
            coutUnitaire: 1500,
            typeMouvement: TypeMouvement::ENTREE_PRODUCTION,
        );

        $transfert = $this->creerTransfert();

        // Transférer 10 kg, mais seulement 9.5 reçus (0.5 kg de perte)
        $this->stockService->transfert(
            produitId: $this->produit->id,
            emplacementSourceId: $this->emplacementSite->id,
            emplacementDestId: $this->emplacementBelleville->id,
            quantiteEnvoyee: 10,
            quantiteRecue: 9.5,
            transfertId: $transfert->id,
        );

        // Site : 20 - 10 = 10 kg
        $stockSite = StockEmplacement::where('produit_id', $this->produit->id)
            ->where('emplacement_id', $this->emplacementSite->id)
            ->first();
        $this->assertEquals(10, (float) $stockSite->quantite);

        // Belleville : 9.5 kg
        $stockBelleville = StockEmplacement::where('produit_id', $this->produit->id)
            ->where('emplacement_id', $this->emplacementBelleville->id)
            ->first();
        $this->assertEquals(9.5, (float) $stockBelleville->quantite);

        // Un mouvement de perte doit exister
        $perte = MouvementStock::where('type_mouvement', TypeMouvement::SORTIE_PERTE)
            ->where('transfert_id', $transfert->id)
            ->first();
        $this->assertNotNull($perte);
        $this->assertEquals(0.5, (float) $perte->quantite);
    }

    // =========================================================
    // AJUSTEMENTS INVENTAIRE
    // =========================================================

    public function test_ajustement_inventaire_positif(): void
    {
        // Stock initial : 10 kg
        $this->stockService->entree(
            produitId: $this->produit->id,
            emplacementId: $this->emplacementSite->id,
            quantite: 10,
            coutUnitaire: 1500,
            typeMouvement: TypeMouvement::ENTREE_PRODUCTION,
        );

        // Inventaire constate 12 kg (écart +2)
        $mouvement = $this->stockService->ajustementInventaire(
            produitId: $this->produit->id,
            emplacementId: $this->emplacementSite->id,
            ecart: 2,
            motif: 'Stock trouvé en surplus',
        );

        $this->assertNotNull($mouvement);
        $this->assertEquals('ajustement_plus', $mouvement->type_mouvement->value);

        $stock = StockEmplacement::where('produit_id', $this->produit->id)
            ->where('emplacement_id', $this->emplacementSite->id)
            ->first();
        $this->assertEquals(12, (float) $stock->quantite);
    }

    public function test_ajustement_inventaire_negatif(): void
    {
        // Stock initial : 10 kg
        $this->stockService->entree(
            produitId: $this->produit->id,
            emplacementId: $this->emplacementSite->id,
            quantite: 10,
            coutUnitaire: 1500,
            typeMouvement: TypeMouvement::ENTREE_PRODUCTION,
        );

        // Inventaire constate 8 kg (écart -2)
        $mouvement = $this->stockService->ajustementInventaire(
            produitId: $this->produit->id,
            emplacementId: $this->emplacementSite->id,
            ecart: -2,
            motif: 'Manquant constaté',
        );

        $this->assertNotNull($mouvement);
        $this->assertEquals('ajustement_moins', $mouvement->type_mouvement->value);

        $stock = StockEmplacement::where('produit_id', $this->produit->id)
            ->where('emplacement_id', $this->emplacementSite->id)
            ->first();
        $this->assertEquals(8, (float) $stock->quantite);
    }

    public function test_ajustement_inventaire_zero_ne_cree_pas_mouvement(): void
    {
        $mouvement = $this->stockService->ajustementInventaire(
            produitId: $this->produit->id,
            emplacementId: $this->emplacementSite->id,
            ecart: 0,
        );

        $this->assertNull($mouvement);
    }

    // =========================================================
    // HELPERS
    // =========================================================

    public function test_get_cmp(): void
    {
        $this->stockService->entree(
            produitId: $this->produit->id,
            emplacementId: $this->emplacementSite->id,
            quantite: 10,
            coutUnitaire: 1800,
            typeMouvement: TypeMouvement::ENTREE_PRODUCTION,
        );

        $cmp = $this->stockService->getCMP($this->produit->id, $this->emplacementSite->id);
        $this->assertEquals(1800, $cmp);
    }

    public function test_get_quantite_disponible(): void
    {
        $this->stockService->entree(
            produitId: $this->produit->id,
            emplacementId: $this->emplacementSite->id,
            quantite: 25.5,
            coutUnitaire: 1500,
            typeMouvement: TypeMouvement::ENTREE_PRODUCTION,
        );

        $qte = $this->stockService->getQuantiteDisponible($this->produit->id, $this->emplacementSite->id);
        $this->assertEquals(25.5, $qte);
    }

    public function test_references_mouvements_uniques(): void
    {
        // Créer 3 mouvements et vérifier que les références sont uniques
        $m1 = $this->stockService->entree(
            produitId: $this->produit->id,
            emplacementId: $this->emplacementSite->id,
            quantite: 5,
            coutUnitaire: 1000,
            typeMouvement: TypeMouvement::ENTREE_PRODUCTION,
        );
        $m2 = $this->stockService->entree(
            produitId: $this->produit->id,
            emplacementId: $this->emplacementSite->id,
            quantite: 3,
            coutUnitaire: 1200,
            typeMouvement: TypeMouvement::ENTREE_PRODUCTION,
        );

        $this->assertNotEquals($m1->reference, $m2->reference);
        $this->assertStringStartsWith('MVT-', $m1->reference);
    }

    // =========================================================
    // SCÉNARIO COMPLET
    // =========================================================

    public function test_scenario_complet_production_vente_transfert(): void
    {
        // 1. Production : entrée de 50 kg d'escalopes à 1200 FCFA/kg
        $this->stockService->entree(
            produitId: $this->produit->id,
            emplacementId: $this->emplacementSite->id,
            quantite: 50,
            coutUnitaire: 1200,
            typeMouvement: TypeMouvement::ENTREE_PRODUCTION,
        );

        // 2. Vente : sortie de 15 kg
        $this->stockService->sortie(
            produitId: $this->produit->id,
            emplacementId: $this->emplacementSite->id,
            quantite: 15,
            typeMouvement: TypeMouvement::SORTIE_VENTE,
        );
        // Stock site = 35 kg, CMP = 1200

        // 3. Nouvelle production : 20 kg à 1400 FCFA/kg
        // CMP = (35 × 1200 + 20 × 1400) / 55 = (42000 + 28000) / 55 ≈ 1272.7273
        $this->stockService->entree(
            produitId: $this->produit->id,
            emplacementId: $this->emplacementSite->id,
            quantite: 20,
            coutUnitaire: 1400,
            typeMouvement: TypeMouvement::ENTREE_PRODUCTION,
        );

        $stock = StockEmplacement::where('produit_id', $this->produit->id)
            ->where('emplacement_id', $this->emplacementSite->id)
            ->first();
        $this->assertEquals(55, (float) $stock->quantite);
        $this->assertEqualsWithDelta(1272.7273, (float) $stock->cout_moyen_pondere, 0.001);

        // 4. Transfert : 10 kg vers Belleville (9.8 kg reçus)
        $transfert = $this->creerTransfert();
        $this->stockService->transfert(
            produitId: $this->produit->id,
            emplacementSourceId: $this->emplacementSite->id,
            emplacementDestId: $this->emplacementBelleville->id,
            quantiteEnvoyee: 10,
            quantiteRecue: 9.8,
            transfertId: $transfert->id,
        );

        // Site : 55 - 10 = 45 kg
        $stockSite = StockEmplacement::where('produit_id', $this->produit->id)
            ->where('emplacement_id', $this->emplacementSite->id)
            ->first();
        $this->assertEquals(45, (float) $stockSite->quantite);

        // Belleville : 9.8 kg au CMP ≈ 1272.7273
        $stockBelleville = StockEmplacement::where('produit_id', $this->produit->id)
            ->where('emplacement_id', $this->emplacementBelleville->id)
            ->first();
        $this->assertEquals(9.8, (float) $stockBelleville->quantite);

        // 5. Total mouvements créés
        $nbMouvements = MouvementStock::where('produit_id', $this->produit->id)->count();
        // entree_production + sortie_vente + entree_production + transfert_sortie + transfert_entree + sortie_perte = 6
        $this->assertEquals(6, $nbMouvements);
    }
}
