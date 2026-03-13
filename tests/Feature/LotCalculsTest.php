<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StatutFacture;
use App\Models\Fournisseur;
use App\Models\Lot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests des calculs automatiques sur les lots (achats).
 *
 * Vérifie les règles de gestion :
 * - quantite_utilisable = quantite_recue - quantite_morts - quantite_refuses
 * - cout_total = (prix_unitaire × quantite_recue) + cout_transport + autres_couts
 * - cout_moyen_unitaire = cout_total / quantite_utilisable
 * - statut_facture selon montant_regle vs montant_facture
 */
class LotCalculsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Fournisseur $fournisseur;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Testeur Lots',
            'email' => 'lots@chapchap.ci',
            'password' => bcrypt('password'),
            'actif' => true,
        ]);
        $this->actingAs($this->user);

        $this->fournisseur = Fournisseur::create([
            'nom' => 'OVECH RANCH Test',
            'telephone' => '0100000000',
            'type' => 'volaille',
            'actif' => true,
        ]);
    }

    public function test_calcul_quantite_utilisable(): void
    {
        $lot = Lot::create([
            'reference' => 'LOT-TEST-001',
            'date_reception' => now()->toDateString(),
            'fournisseur_id' => $this->fournisseur->id,
            'type_produit' => 'poulet',
            'quantite_recue' => 200,
            'quantite_morts' => 8,
            'quantite_refuses' => 5,
            'quantite_utilisable' => 200 - 8 - 5,  // 187
            'prix_unitaire' => 2500,
            'cout_transport' => 50000,
            'autres_couts' => 10000,
            'cout_total' => (2500 * 200) + 50000 + 10000,  // 560000
            'cout_moyen_unitaire' => 560000 / 187,
            'montant_facture' => 500000,
            'statut_facture' => StatutFacture::NON_REGLEE,
            'montant_regle' => 0,
            'created_by' => $this->user->id,
        ]);

        $this->assertEquals(187, $lot->quantite_utilisable);
        $this->assertEquals(560000, (float) $lot->cout_total);
        $this->assertEqualsWithDelta(2994.6524, (float) $lot->cout_moyen_unitaire, 0.1);
    }

    public function test_statut_facture_non_reglee(): void
    {
        $lot = $this->creerLotBase();
        $lot->update([
            'montant_facture' => 500000,
            'montant_regle' => 0,
            'statut_facture' => $this->calculerStatutFacture(500000, 0),
        ]);

        $this->assertEquals(StatutFacture::NON_REGLEE, $lot->fresh()->statut_facture);
    }

    public function test_statut_facture_partielle(): void
    {
        $lot = $this->creerLotBase();
        $lot->update([
            'montant_facture' => 500000,
            'montant_regle' => 200000,
            'statut_facture' => $this->calculerStatutFacture(500000, 200000),
        ]);

        $this->assertEquals(StatutFacture::PARTIELLE, $lot->fresh()->statut_facture);
    }

    public function test_statut_facture_reglee(): void
    {
        $lot = $this->creerLotBase();
        $lot->update([
            'montant_facture' => 500000,
            'montant_regle' => 500000,
            'statut_facture' => $this->calculerStatutFacture(500000, 500000),
        ]);

        $this->assertEquals(StatutFacture::REGLEE, $lot->fresh()->statut_facture);
    }

    public function test_lot_oeufs_avec_casses(): void
    {
        $lot = Lot::create([
            'reference' => 'LOT-OEUF-001',
            'date_reception' => now()->toDateString(),
            'fournisseur_id' => $this->fournisseur->id,
            'type_produit' => 'oeuf',
            'quantite_recue' => 1000,
            'quantite_morts' => 0,
            'quantite_refuses' => 0,
            'quantite_utilisable' => 1000,
            'oeufs_casses' => 15,
            'prix_unitaire' => 100,
            'cout_transport' => 5000,
            'autres_couts' => 0,
            'cout_total' => (100 * 1000) + 5000,  // 105000
            'cout_moyen_unitaire' => 105000 / 1000,
            'montant_facture' => 100000,
            'statut_facture' => StatutFacture::NON_REGLEE,
            'montant_regle' => 0,
            'created_by' => $this->user->id,
        ]);

        $this->assertEquals(1000, $lot->quantite_utilisable);
        $this->assertEquals(15, $lot->oeufs_casses);
        $this->assertEquals(105, (float) $lot->cout_moyen_unitaire);
    }

    public function test_cout_moyen_unitaire_avec_zero_utilisable(): void
    {
        // Cas extrême : tous les poulets sont morts/refusés
        $lot = Lot::create([
            'reference' => 'LOT-ZERO-001',
            'date_reception' => now()->toDateString(),
            'fournisseur_id' => $this->fournisseur->id,
            'type_produit' => 'poulet',
            'quantite_recue' => 10,
            'quantite_morts' => 7,
            'quantite_refuses' => 3,
            'quantite_utilisable' => 0,
            'prix_unitaire' => 2500,
            'cout_transport' => 5000,
            'autres_couts' => 0,
            'cout_total' => 30000,
            'cout_moyen_unitaire' => 0,  // Éviter division par zéro
            'montant_facture' => 25000,
            'statut_facture' => StatutFacture::NON_REGLEE,
            'montant_regle' => 0,
            'created_by' => $this->user->id,
        ]);

        $this->assertEquals(0, $lot->quantite_utilisable);
        $this->assertEquals(0, (float) $lot->cout_moyen_unitaire);
    }

    // --- Helpers ---

    private function creerLotBase(): Lot
    {
        return Lot::create([
            'reference' => 'LOT-BASE-' . uniqid(),
            'date_reception' => now()->toDateString(),
            'fournisseur_id' => $this->fournisseur->id,
            'type_produit' => 'poulet',
            'quantite_recue' => 100,
            'quantite_morts' => 5,
            'quantite_refuses' => 2,
            'quantite_utilisable' => 93,
            'prix_unitaire' => 2500,
            'cout_transport' => 30000,
            'autres_couts' => 5000,
            'cout_total' => 285000,
            'cout_moyen_unitaire' => 285000 / 93,
            'montant_facture' => 250000,
            'statut_facture' => StatutFacture::NON_REGLEE,
            'montant_regle' => 0,
            'created_by' => $this->user->id,
        ]);
    }

    /**
     * Reproduit la logique métier de calcul du statut facture.
     */
    private function calculerStatutFacture(float $montantFacture, float $montantRegle): StatutFacture
    {
        if ($montantRegle <= 0) {
            return StatutFacture::NON_REGLEE;
        }
        if ($montantRegle >= $montantFacture) {
            return StatutFacture::REGLEE;
        }

        return StatutFacture::PARTIELLE;
    }
}
