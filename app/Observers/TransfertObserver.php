<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Transfert;
use App\Traits\Auditable;

/**
 * Observer pour les transferts — audit trail automatique.
 *
 * Enregistre dans journal_audit chaque création, modification et suppression.
 * Enregistre aussi spécifiquement la réception (changement de statut vers "recu").
 */
class TransfertObserver
{
    use Auditable;

    public function created(Transfert $transfert): void
    {
        static::enregistrerAudit($transfert, 'creation', donneesApres: $transfert->toArray());
    }

    public function updated(Transfert $transfert): void
    {
        // Si le statut passe à "recu", on enregistre une action spécifique "validation"
        if ($transfert->wasChanged('statut') && $transfert->statut->value === 'recu') {
            static::enregistrerAudit(
                $transfert,
                'validation',
                donneesAvant: ['statut' => $transfert->getOriginal('statut')],
                donneesApres: ['statut' => 'recu', 'recu_par' => $transfert->recu_par],
            );
        } else {
            static::enregistrerAudit(
                $transfert,
                'modification',
                donneesAvant: $transfert->getOriginal(),
                donneesApres: $transfert->getChanges(),
            );
        }
    }

    public function deleted(Transfert $transfert): void
    {
        static::enregistrerAudit($transfert, 'suppression', donneesAvant: $transfert->toArray());
    }
}
