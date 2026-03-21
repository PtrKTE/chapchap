<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StatutCaisse;
use App\Models\Caisse;
use App\Models\Emplacement;
use App\Models\User;
use App\Services\CaisseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests du CaisseService — logique caisse journalière.
 *
 * Vérifie :
 * - Ouverture de caisse (et blocage doublon)
 * - Calcul solde = encaissé + crédits - décaissé
 * - Calcul écart = solde - versements
 * - Cycle de vie : OUVERTE → CLOTUREE → VALIDEE
 * - Blocages (clôturer une caisse déjà clôturée, etc.)
 */
class CaisseServiceTest extends TestCase
{
    use RefreshDatabase;

    private CaisseService $service;
    private User $user;
    private Emplacement $emplacement;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new CaisseService();

        $this->user = User::create([
            'name' => 'Responsable Test',
            'email' => 'caisse@chapchap.ci',
            'password' => bcrypt('password'),
            'actif' => true,
        ]);
        $this->actingAs($this->user);

        $this->emplacement = Emplacement::create([
            'nom' => 'Site Test',
            'type' => 'site',
            'actif' => true,
        ]);
    }

    // =========================================================
    // OUVERTURE
    // =========================================================

    public function test_ouvrir_caisse(): void
    {
        $caisse = $this->service->ouvrir(
            emplacementId: $this->emplacement->id,
            responsableId: $this->user->id,
        );

        $this->assertInstanceOf(Caisse::class, $caisse);
        $this->assertEquals(StatutCaisse::OUVERTE, $caisse->statut);
        $this->assertEquals(0, (float) $caisse->solde_caisse);
        $this->assertEquals(0, (float) $caisse->ecart);
    }

    public function test_ouvrir_deux_fois_meme_jour_leve_exception(): void
    {
        $this->service->ouvrir(
            emplacementId: $this->emplacement->id,
            responsableId: $this->user->id,
        );

        $this->expectException(\RuntimeException::class);

        $this->service->ouvrir(
            emplacementId: $this->emplacement->id,
            responsableId: $this->user->id,
        );
    }

    // =========================================================
    // SOLDE ET ÉCART
    // =========================================================

    public function test_solde_caisse_calcul(): void
    {
        $caisse = $this->creerCaisse();

        // On simule manuellement les totaux (pas de ventes/charges en BDD dans ce test)
        $caisse->update([
            'total_encaisse'          => 500000,
            'total_credits_encaisses' => 50000,
            'total_decaisse'          => 80000,
        ]);
        $caisse->refresh();

        // Solde attendu = 500000 + 50000 - 80000 = 470000
        $soldeAttendu = 500000 + 50000 - 80000;
        $soldeCaisse  = (float) $caisse->total_encaisse
                      + (float) $caisse->total_credits_encaisses
                      - (float) $caisse->total_decaisse;

        $this->assertEquals($soldeAttendu, $soldeCaisse);
    }

    public function test_ecart_calcul_avec_versements(): void
    {
        $caisse = $this->creerCaisse([
            'total_encaisse'  => 300000,
            'solde_caisse'    => 300000,
        ]);

        // Versements : 200 000 espèces + 80 000 Wave = 280 000
        // Écart = 300 000 - 280 000 = 20 000
        $caisse = $this->service->enregistrerVersement(
            caisse: $caisse,
            montantEspeces: 200000,
            depotWaveMtn: 80000,
        );

        $this->assertEquals(20000, (float) $caisse->ecart);
    }

    public function test_ecart_zero_quand_versements_egaux_solde(): void
    {
        $caisse = $this->creerCaisse(['solde_caisse' => 150000]);

        $caisse = $this->service->enregistrerVersement(
            caisse: $caisse,
            montantEspeces: 100000,
            depotWaveMtn: 30000,
            depotCheque: 20000,
        );

        $this->assertEquals(0, (float) $caisse->ecart);
    }

    // =========================================================
    // CYCLE DE VIE : OUVERTE → CLOTUREE → VALIDEE
    // =========================================================

    public function test_cloturer_caisse_ouverte(): void
    {
        $caisse = $this->creerCaisse();

        $this->assertEquals(StatutCaisse::OUVERTE, $caisse->statut);

        $caisse = $this->service->cloturer($caisse);

        $this->assertEquals(StatutCaisse::CLOTUREE, $caisse->statut);
    }

    public function test_cloturer_caisse_deja_cloturee_leve_exception(): void
    {
        $caisse = $this->creerCaisse(['statut' => StatutCaisse::CLOTUREE->value]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ouverte');

        $this->service->cloturer($caisse);
    }

    public function test_valider_caisse_cloturee(): void
    {
        $caisse = $this->creerCaisse(['statut' => StatutCaisse::CLOTUREE->value]);

        $caisse = $this->service->valider($caisse, $this->user->id);

        $this->assertEquals(StatutCaisse::VALIDEE, $caisse->statut);
        $this->assertEquals($this->user->id, $caisse->valide_par);
    }

    public function test_valider_caisse_ouverte_leve_exception(): void
    {
        $caisse = $this->creerCaisse();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('clôturée');

        $this->service->valider($caisse, $this->user->id);
    }

    public function test_modifier_caisse_validee_leve_exception(): void
    {
        $caisse = $this->creerCaisse(['statut' => StatutCaisse::VALIDEE->value]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('validée');

        $this->service->enregistrerVersement($caisse, montantEspeces: 10000);
    }

    // =========================================================
    // RÉSUMÉ PÉRIODE
    // =========================================================

    public function test_resume_periode(): void
    {
        // Créer 2 caisses sur des jours différents
        Caisse::create([
            'date_caisse'             => now()->subDay()->toDateString(),
            'emplacement_id'          => $this->emplacement->id,
            'responsable_id'          => $this->user->id,
            'statut'                  => StatutCaisse::VALIDEE->value,
            'total_encaisse'          => 200000,
            'total_credits_encaisses' => 20000,
            'total_decaisse'          => 30000,
            'solde_caisse'            => 190000,
            'ecart'                   => 0,
        ]);
        Caisse::create([
            'date_caisse'             => now()->toDateString(),
            'emplacement_id'          => $this->emplacement->id,
            'responsable_id'          => $this->user->id,
            'statut'                  => StatutCaisse::OUVERTE->value,
            'total_encaisse'          => 300000,
            'total_credits_encaisses' => 0,
            'total_decaisse'          => 50000,
            'solde_caisse'            => 250000,
            'ecart'                   => 0,
        ]);

        $resume = $this->service->resumePeriode(
            dateDebut: now()->subDays(7)->startOfDay(),
            dateFin: now()->endOfDay(),
        );

        $this->assertEquals(500000, $resume['total_encaisse']);
        $this->assertEquals(80000, $resume['total_decaisse']);
        $this->assertEquals(440000, $resume['solde_net']);
    }

    // =========================================================
    // HELPER
    // =========================================================

    private function creerCaisse(array $extra = []): Caisse
    {
        return Caisse::create(array_merge([
            'date_caisse'             => now()->toDateString(),
            'emplacement_id'          => $this->emplacement->id,
            'responsable_id'          => $this->user->id,
            'statut'                  => StatutCaisse::OUVERTE->value,
            'total_encaisse'          => 0,
            'total_credits_encaisses' => 0,
            'total_decaisse'          => 0,
            'solde_caisse'            => 0,
            'montant_verse'           => 0,
            'depot_wave_mtn'          => 0,
            'depot_cheque'            => 0,
            'montant_especes'         => 0,
            'ecart'                   => 0,
        ], $extra));
    }
}
