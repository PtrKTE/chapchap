<?php

declare(strict_types=1);

namespace App\Filament\Resources\VenteResource\Pages;

use App\Enums\StatutPaiement;
use App\Filament\Resources\VenteResource;
use App\Models\Paiement;
use App\Models\Vente;
use App\Services\StockService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Page de création d'une vente.
 *
 * Filament 3 avec ->relationship() sur le Repeater sauvegarde d'abord
 * l'enregistrement parent, PUIS les lignes enfants.
 * → Les totaux NE PEUVENT PAS être calculés dans mutateFormDataBeforeCreate
 *   (les lignes n'existent pas encore en BDD à ce stade).
 * → On calcule les totaux dans afterCreate(), une fois les lignes sauvegardées.
 */
class CreateVente extends CreateRecord
{
    protected static string $resource = VenteResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Numéro de reçu auto : REC-YYYYMMDD-XXX
        $date = now()->format('Ymd');
        $count = Vente::whereDate('date_vente', today())->count() + 1;
        $data['numero_recu'] = sprintf('REC-%s-%03d', $date, $count);

        // Utilisateur connecté
        $data['created_by'] = auth()->id();

        // Valeurs provisoires — seront recalculées dans afterCreate()
        // une fois que Filament aura sauvegardé les lignes de la relation
        $data['montant_total']    = 0;
        $data['montant_net']      = 0;
        $data['montant_restant']  = 0;
        $data['statut_paiement']  = StatutPaiement::CREDIT->value;
        $data['annulee']          = false;

        return $data;
    }

    /**
     * Après création : les lignes sont maintenant en BDD.
     *
     * On peut donc :
     * 1. Recalculer les vrais totaux depuis les lignes sauvegardées
     * 2. Décrémenter le stock via StockService
     * 3. Enregistrer le paiement initial
     */
    protected function afterCreate(): void
    {
        $vente = $this->record;

        // ── Étape 1 : recalcul des totaux depuis les lignes réelles ──────────
        $lignes = $vente->lignes()->get();

        $montantTotal   = (float) $lignes->sum('montant_ligne');
        $remise         = (float) $vente->remise;
        $montantNet     = max(0, $montantTotal - $remise);
        $montantRecu    = (float) $vente->montant_recu;
        $montantRestant = max(0, $montantNet - $montantRecu);

        // Déterminer le statut paiement correct
        if ($montantNet <= 0) {
            $statut = StatutPaiement::PAYE->value;
        } elseif ($montantRecu <= 0) {
            $statut = StatutPaiement::CREDIT->value;
        } elseif ($montantRecu < $montantNet) {
            $statut = StatutPaiement::PARTIEL->value;
        } else {
            $statut = StatutPaiement::PAYE->value;
        }

        $vente->update([
            'montant_total'           => round($montantTotal, 2),
            'montant_net'             => round($montantNet, 2),
            'montant_restant'         => round($montantRestant, 2),
            'statut_paiement'         => $statut,
            'date_reglement_complet'  => $montantRestant <= 0 && $montantNet > 0
                                            ? now()->toDateString()
                                            : null,
        ]);

        // Recharger après mise à jour
        $vente->refresh();

        // ── Étape 2 : décrémenter le stock + enregistrer CMP sur les lignes ──
        try {
            DB::transaction(function () use ($vente, $lignes) {
                $stockService = app(StockService::class);

                foreach ($lignes as $ligne) {
                    $mouvement = $stockService->sortie(
                        produitId: $ligne->produit_id,
                        emplacementId: (int) $vente->emplacement_id,
                        quantite: (float) $ligne->quantite,
                        typeMouvement: \App\Enums\TypeMouvement::SORTIE_VENTE,
                        venteId: $vente->id,
                    );

                    // Enregistrer le CMP au moment de la vente sur la ligne
                    $ligne->update([
                        'cout_revient' => round((float) $mouvement->cout_unitaire, 4),
                        'marge_ligne'  => round(
                            (float) $ligne->montant_ligne
                                - ((float) $ligne->quantite * (float) $mouvement->cout_unitaire),
                            2
                        ),
                    ]);
                }

                // ── Étape 3 : paiement initial si montant reçu > 0 ──────────
                if ($vente->montant_recu > 0) {
                    Paiement::create([
                        'vente_id'      => $vente->id,
                        'date_paiement' => now(),
                        'montant'       => (float) $vente->montant_recu,
                        'mode_paiement' => $vente->mode_paiement->value,
                        'observations'  => 'Paiement initial à la vente',
                        'created_by'    => auth()->id(),
                    ]);
                }
            });
        } catch (\RuntimeException $e) {
            Log::error("Erreur stock vente {$vente->numero_recu}", ['error' => $e->getMessage()]);

            Notification::make()
                ->title('Attention : problème de stock')
                ->body($e->getMessage())
                ->warning()
                ->persistent()
                ->send();
        }
    }

    protected function getRedirectUrl(): string
    {
        // Redirige vers la page de vue (ViewVente) après création
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
