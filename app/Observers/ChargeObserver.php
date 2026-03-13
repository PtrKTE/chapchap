<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Charge;
use App\Traits\Auditable;

/**
 * Observer pour les charges — audit trail automatique.
 */
class ChargeObserver
{
    use Auditable;

    public function created(Charge $charge): void
    {
        static::enregistrerAudit($charge, 'creation', donneesApres: $charge->toArray());
    }

    public function updated(Charge $charge): void
    {
        static::enregistrerAudit(
            $charge,
            'modification',
            donneesAvant: $charge->getOriginal(),
            donneesApres: $charge->getChanges(),
        );
    }

    public function deleted(Charge $charge): void
    {
        static::enregistrerAudit($charge, 'suppression', donneesAvant: $charge->toArray());
    }
}
