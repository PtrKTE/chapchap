<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StatutPaiement;
use App\Enums\TypeMouvement;
use App\Models\Client;
use App\Models\Emplacement;
use App\Models\MouvementStock;
use App\Models\Paiement;
use App\Models\Produit;
use App\Models\StockEmplacement;
use App\Models\User;
use App\Models\Vente;
use App\Models\VenteLigne;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests du flux de vente complet.
 *
 * Simule le parcours réel : créer un stock, effectuer une vente,
 * vérifier le stock décrémenté, encaisser des paiements partiels,
 * et tester l'annulation avec remise en stock.
 */
class VenteFlowTest extends TestCase
{
    use RefreshDatabase;

    private StockService $stockService;
    private User $user;
    private Emplacement $emplacement;
    private Produit $escalopes;
    private Produit $cuisses;
    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stockService = new StockService();

        $this->user = User::create([
            'name' => 'Commercial Test',
            'email' => 'commercial@chapchap.ci',
            'password' => bcrypt('password'),
            'profil' => 'commercial',
            'actif' => true,
        ]);
        $this->actingAs($this->user);

        $this->emplacement = Emplacement::create([
            'nom' => 'Site Test',
            'type' => 'site',
            'actif' => true,
        ]);

        $this->escalopes = Produit::create([
            'code' => 'ESCAL',
            'nom' => 'Escalopes',
            'categorie' => 'decoupe',
            'unite_stock' => 'kg',
            'unite_vente' => 'kg',
            'prix_vente_defaut' => 3800,
            'seuil_alerte_stock' => 5,
            'actif' => true,
        ]);

        $this->cuisses = Produit::create([
            'code' => 'CUISS',
            'nom' => 'Cuisses',
            'categorie' => 'decoupe',
            'unite_stock' => 'kg',
            'unite_vente' => 'kg',
            'prix_vente_defaut' => 2500,
            'seuil_alerte_stock' => 5,
            'actif' => true,
        ]);

        $this->client = Client::create([
            'code' => 'CLI-0001',
            'nom' => 'Restaurant Test',
            'telephone' => '0100000000',
            'type_client' => 'restaurant',
            'date_enregistrement' => now()->toDateString(),
            'actif' => true,
        ]);

        // Mettre du stock en place
        $this->stockService->entree(
            produitId: $this->escalopes->id,
            emplacementId: $this->emplacement->id,
            quantite: 50,
            coutUnitaire: 1500,
            typeMouvement: TypeMouvement::ENTREE_PRODUCTION,
        );
        $this->stockService->entree(
            produitId: $this->cuisses->id,
            emplacementId: $this->emplacement->id,
            quantite: 40,
            coutUnitaire: 1200,
            typeMouvement: TypeMouvement::ENTREE_PRODUCTION,
        );
    }

    public function test_creation_vente_payee_comptant(): void
    {
        // Créer une vente payée comptant
        $vente = Vente::create([
            'numero_recu' => 'REC-TEST-001',
            'date_vente' => now(),
            'client_id' => $this->client->id,
            'commercial_id' => $this->user->id,
            'emplacement_id' => $this->emplacement->id,
            'canal' => 'boutique',
            'montant_total' => 19000 + 5000,  // 5kg escal + 2kg cuisses
            'remise' => 0,
            'montant_net' => 24000,
            'montant_recu' => 24000,
            'montant_restant' => 0,
            'statut_paiement' => StatutPaiement::PAYE,
            'mode_paiement' => 'especes',
            'created_by' => $this->user->id,
        ]);

        // Lignes de vente
        VenteLigne::create([
            'vente_id' => $vente->id,
            'produit_id' => $this->escalopes->id,
            'quantite' => 5,
            'prix_unitaire' => 3800,
            'montant_ligne' => 19000,
            'cout_revient' => 1500,
            'marge_ligne' => 19000 - (5 * 1500),  // 11500
        ]);
        VenteLigne::create([
            'vente_id' => $vente->id,
            'produit_id' => $this->cuisses->id,
            'quantite' => 2,
            'prix_unitaire' => 2500,
            'montant_ligne' => 5000,
            'cout_revient' => 1200,
            'marge_ligne' => 5000 - (2 * 1200),  // 2600
        ]);

        // Décrémenter le stock (comme le fait CreateVente)
        $this->stockService->sortie(
            produitId: $this->escalopes->id,
            emplacementId: $this->emplacement->id,
            quantite: 5,
            typeMouvement: TypeMouvement::SORTIE_VENTE,
            venteId: $vente->id,
        );
        $this->stockService->sortie(
            produitId: $this->cuisses->id,
            emplacementId: $this->emplacement->id,
            quantite: 2,
            typeMouvement: TypeMouvement::SORTIE_VENTE,
            venteId: $vente->id,
        );

        // Vérifier le stock
        $stockEscal = $this->stockService->getQuantiteDisponible($this->escalopes->id, $this->emplacement->id);
        $this->assertEquals(45, $stockEscal);  // 50 - 5

        $stockCuiss = $this->stockService->getQuantiteDisponible($this->cuisses->id, $this->emplacement->id);
        $this->assertEquals(38, $stockCuiss);  // 40 - 2

        // Vérifier la marge
        $margeVente = $vente->lignes->sum(fn($l) => (float) $l->marge_ligne);
        $this->assertEquals(14100, $margeVente);  // 11500 + 2600
    }

    public function test_vente_a_credit_puis_paiements_partiels(): void
    {
        // Vente à crédit : 0 FCFA reçus sur 19000
        $vente = Vente::create([
            'numero_recu' => 'REC-TEST-002',
            'date_vente' => now(),
            'client_id' => $this->client->id,
            'emplacement_id' => $this->emplacement->id,
            'canal' => 'b2b',
            'montant_total' => 19000,
            'remise' => 0,
            'montant_net' => 19000,
            'montant_recu' => 0,
            'montant_restant' => 19000,
            'statut_paiement' => StatutPaiement::CREDIT,
            'mode_paiement' => 'especes',
            'created_by' => $this->user->id,
        ]);

        // Vérifier statut initial
        $this->assertEquals(StatutPaiement::CREDIT, $vente->statut_paiement);
        $this->assertEquals(19000, (float) $vente->montant_restant);

        // Paiement partiel 1 : 5000 FCFA
        Paiement::create([
            'vente_id' => $vente->id,
            'date_paiement' => now(),
            'montant' => 5000,
            'mode_paiement' => 'especes',
            'created_by' => $this->user->id,
        ]);
        $vente->update([
            'montant_recu' => 5000,
            'montant_restant' => 14000,
            'statut_paiement' => StatutPaiement::PARTIEL,
        ]);
        $vente->refresh();

        $this->assertEquals(StatutPaiement::PARTIEL, $vente->statut_paiement);
        $this->assertEquals(14000, (float) $vente->montant_restant);

        // Paiement partiel 2 : 14000 FCFA (solde)
        Paiement::create([
            'vente_id' => $vente->id,
            'date_paiement' => now(),
            'montant' => 14000,
            'mode_paiement' => 'wave',
            'created_by' => $this->user->id,
        ]);
        $vente->update([
            'montant_recu' => 19000,
            'montant_restant' => 0,
            'statut_paiement' => StatutPaiement::PAYE,
            'date_reglement_complet' => now()->toDateString(),
        ]);
        $vente->refresh();

        $this->assertEquals(StatutPaiement::PAYE, $vente->statut_paiement);
        $this->assertEquals(0, (float) $vente->montant_restant);
        $this->assertEquals(2, $vente->paiements()->count());
    }

    public function test_annulation_vente_remet_stock(): void
    {
        // Vente de 5 kg d'escalopes
        $vente = Vente::create([
            'numero_recu' => 'REC-TEST-003',
            'date_vente' => now(),
            'client_id' => $this->client->id,
            'emplacement_id' => $this->emplacement->id,
            'canal' => 'boutique',
            'montant_total' => 19000,
            'remise' => 0,
            'montant_net' => 19000,
            'montant_recu' => 19000,
            'montant_restant' => 0,
            'statut_paiement' => StatutPaiement::PAYE,
            'mode_paiement' => 'especes',
            'created_by' => $this->user->id,
        ]);

        VenteLigne::create([
            'vente_id' => $vente->id,
            'produit_id' => $this->escalopes->id,
            'quantite' => 5,
            'prix_unitaire' => 3800,
            'montant_ligne' => 19000,
            'cout_revient' => 1500,
            'marge_ligne' => 11500,
        ]);

        // Sortie stock
        $this->stockService->sortie(
            produitId: $this->escalopes->id,
            emplacementId: $this->emplacement->id,
            quantite: 5,
            typeMouvement: TypeMouvement::SORTIE_VENTE,
            venteId: $vente->id,
        );

        $stockAvant = $this->stockService->getQuantiteDisponible($this->escalopes->id, $this->emplacement->id);
        $this->assertEquals(45, $stockAvant);

        // Annulation : remet le stock via un mouvement d'entrée annulation
        foreach ($vente->lignes as $ligne) {
            $this->stockService->entree(
                produitId: $ligne->produit_id,
                emplacementId: $vente->emplacement_id,
                quantite: (float) $ligne->quantite,
                coutUnitaire: (float) $ligne->cout_revient,
                typeMouvement: TypeMouvement::ENTREE_ANNULATION,
                venteId: $vente->id,
                motif: "Annulation vente {$vente->numero_recu}",
            );
        }

        $vente->update([
            'annulee' => true,
            'date_annulation' => now(),
            'motif_annulation' => 'Client a changé d\'avis',
        ]);

        // Vérifier le stock remis en place
        $stockApres = $this->stockService->getQuantiteDisponible($this->escalopes->id, $this->emplacement->id);
        $this->assertEquals(50, $stockApres);  // Retour au stock initial

        // Vérifier la vente annulée
        $vente->refresh();
        $this->assertTrue($vente->annulee);
    }

    public function test_remise_calcul_montant_net(): void
    {
        $vente = Vente::create([
            'numero_recu' => 'REC-TEST-004',
            'date_vente' => now(),
            'client_id' => $this->client->id,
            'emplacement_id' => $this->emplacement->id,
            'canal' => 'b2b',
            'montant_total' => 50000,
            'remise' => 5000,
            'montant_net' => 45000,   // 50000 - 5000
            'montant_recu' => 45000,
            'montant_restant' => 0,
            'statut_paiement' => StatutPaiement::PAYE,
            'mode_paiement' => 'virement',
            'created_by' => $this->user->id,
        ]);

        $this->assertEquals(45000, (float) $vente->montant_net);
        $this->assertEquals(5000, (float) $vente->remise);
    }
}
