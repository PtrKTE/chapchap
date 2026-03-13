<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\EauProduction;
use App\Traits\Auditable;

/**
 * Observer pour la production d'eau — audit trail automatique.
 */
class EauProductionObserver
{
    use Auditable;

    public function created(EauProduction $eauProduction): void
    {
        static::enregistrerAudit($eauProduction, 'creation', donneesApres: $eauProduction->toArray());
    }

    public function updated(EauProduction $eauProduction): void
    {
        static::enregistrerAudit(
            $eauProduction,
            'modification',
            donneesAvant: $eauProduction->getOriginal(),
            donneesApres: $eauProduction->getChanges(),
        );
    }

    public function deleted(EauProduction $eauProduction): void
    {
        static::enregistrerAudit($eauProduction, 'suppression', donneesAvant: $eauProduction->toArray());
    }
}
