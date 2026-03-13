<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Paiement;
use App\Traits\Auditable;

/**
 * Observer pour les paiements — audit trail automatique.
 *
 * Chaque encaissement (initial ou partiel) est tracé dans journal_audit.
 * Utile pour le suivi financier et la réconciliation de caisse.
 */
class PaiementObserver
{
    use Auditable;

    public function created(Paiement $paiement): void
    {
        static::enregistrerAudit(
            model: $paiement,
            action: 'creation',
            donneesApres: $paiement->toArray(),
        );
    }

    public function updated(Paiement $paiement): void
    {
        static::enregistrerAudit(
            model: $paiement,
            action: 'modification',
            donneesAvant: $paiement->getOriginal(),
            donneesApres: $paiement->getChanges(),
        );
    }

    public function deleted(Paiement $paiement): void
    {
        static::enregistrerAudit(
            model: $paiement,
            action: 'suppression',
            donneesAvant: $paiement->toArray(),
        );
    }
}
