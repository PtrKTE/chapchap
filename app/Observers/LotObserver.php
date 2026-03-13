<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Lot;
use App\Traits\Auditable;

class LotObserver
{
    use Auditable;

    public function created(Lot $lot): void
    {
        static::enregistrerAudit(
            model: $lot,
            action: 'creation',
            donneesApres: $lot->toArray(),
        );
    }

    public function updated(Lot $lot): void
    {
        static::enregistrerAudit(
            model: $lot,
            action: 'modification',
            donneesAvant: $lot->getOriginal(),
            donneesApres: $lot->getChanges(),
        );
    }

    public function deleted(Lot $lot): void
    {
        static::enregistrerAudit(
            model: $lot,
            action: 'suppression',
            donneesAvant: $lot->toArray(),
        );
    }
}
