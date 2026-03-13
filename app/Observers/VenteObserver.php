<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Vente;
use App\Traits\Auditable;

/**
 * Observer pour les ventes — audit trail automatique.
 *
 * Enregistre dans journal_audit chaque opération :
 * - Création d'une vente
 * - Modification (changement de statut paiement, encaissement, etc.)
 * - Annulation (action dédiée avec motif)
 */
class VenteObserver
{
    use Auditable;

    public function created(Vente $vente): void
    {
        static::enregistrerAudit($vente, 'creation', donneesApres: $vente->toArray());
    }

    public function updated(Vente $vente): void
    {
        // Si la vente est annulée, on trace une "annulation"
        if ($vente->wasChanged('annulee') && $vente->annulee) {
            static::enregistrerAudit(
                $vente,
                'annulation',
                donneesAvant: ['annulee' => false],
                donneesApres: [
                    'annulee' => true,
                    'motif_annulation' => $vente->motif_annulation,
                    'date_annulation' => $vente->date_annulation,
                ],
            );
        } else {
            static::enregistrerAudit(
                $vente,
                'modification',
                donneesAvant: $vente->getOriginal(),
                donneesApres: $vente->getChanges(),
            );
        }
    }

    public function deleted(Vente $vente): void
    {
        static::enregistrerAudit($vente, 'suppression', donneesAvant: $vente->toArray());
    }
}
