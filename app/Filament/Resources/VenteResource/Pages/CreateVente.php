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
 * À la création :
 * 1. Génère le numéro de reçu automatiquement (REC-YYYYMMDD-XXX)
 * 2. Calcule les totaux (montant_total, montant_net, montant_restant)
 * 3. Détermine le statut de paiement (payé, partiel, crédit)
 * 4. Décrémente le stock via StockService pour chaque ligne
 * 5. Enregistre le paiement initial si montant_recu > 0
 */
class CreateVente extends CreateRecord
{
    protected static string $resource = VenteResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // 1. Numéro de reçu auto : REC-YYYYMMDD-XXX
        $date = now()->format('Ymd');
        $count = Vente::whereDate('date_vente', today())->count() + 1;
        $data['numero_recu'] = sprintf('REC-%s-%03d', $date, $count);

        // 2. Utilisateur connecté
        $data['created_by'] = auth()->id();

        // 3. Calculer les totaux à partir des lignes
        $lignes = $data['lignes'] ?? [];
        $montantTotal = collect($lignes)->sum(fn($l) => (float) ($l['montant_ligne'] ?? 0));
        $remise = (float) ($data['remise'] ?? 0);
        $montantNet = max(0, $montantTotal - $remise);
        $montantRecu = (float) ($data['montant_recu'] ?? 0);
        $montantRestant = max(0, $montantNet - $montantRecu);

        $data['montant_total'] = round($montantTotal, 2);
        $data['montant_net'] = round($montantNet, 2);
        $data['montant_restant'] = round($montantRestant, 2);
        $data['annulee'] = false;

        // 4. Déterminer le statut paiement
        if ($montantNet <= 0) {
            $data['statut_paiement'] = StatutPaiement::PAYE->value;
        } elseif ($montantRecu <= 0) {
            $data['statut_paiement'] = StatutPaiement::CREDIT->value;
        } elseif ($montantRecu < $montantNet) {
            $data['statut_paiement'] = StatutPaiement::PARTIEL->value;
        } else {
            $data['statut_paiement'] = StatutPaiement::PAYE->value;
            $data['date_reglement_complet'] = now()->toDateString();
        }

        return $data;
    }

    /**
     * Après la création de la vente : décrémente le stock et enregistre le paiement.
     *
     * Toutes les opérations stock + paiement sont dans une transaction DB :
     * si une sortie de stock échoue, aucune sortie n'est enregistrée
     * (la vente reste créée mais sans impact stock — notification d'erreur).
     */
    protected function afterCreate(): void
    {
        $vente = $this->record;

        try {
            DB::transaction(function () use ($vente) {
                $stockService = app(StockService::class);

                // Décrémente le stock pour chaque ligne de vente
                foreach ($vente->lignes()->with('produit')->get() as $ligne) {
                    $mouvement = $stockService->sortie(
                        produitId: $ligne->produit_id,
                        emplacementId: $vente->emplacement_id,
                        quantite: (float) $ligne->quantite,
                        typeMouvement: \App\Enums\TypeMouvement::SORTIE_VENTE,
                        venteId: $vente->id,
                    );

                    // Enregistrer le CMP au moment de la vente sur la ligne
                    $ligne->update([
                        'cout_revient' => round((float) $mouvement->cout_unitaire, 4),
                        'marge_ligne' => round(
                            (float) $ligne->montant_ligne - ((float) $ligne->quantite * (float) $mouvement->cout_unitaire),
                            2
                        ),
                    ]);
                }

                // Enregistrer le paiement initial si montant reçu > 0
                $montantRecu = (float) $vente->montant_recu;
                if ($montantRecu > 0) {
                    Paiement::create([
                        'vente_id' => $vente->id,
                        'date_paiement' => now(),
                        'montant' => $montantRecu,
                        'mode_paiement' => $vente->mode_paiement->value,
                        'observations' => 'Paiement initial à la vente',
                        'created_by' => auth()->id(),
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
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
