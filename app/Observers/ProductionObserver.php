<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Production;
use App\Traits\Auditable;

class ProductionObserver
{
    use Auditable;

    public function created(Production $production): void
    {
        static::enregistrerAudit(
            model: $production,
            action: 'creation',
            donneesApres: $production->toArray(),
        );
    }

    public function updated(Production $production): void
    {
        // Logger spécifiquement la validation
        $action = $production->wasChanged('statut') && $production->statut->value === 'validee'
            ? 'validation'
            : 'modification';

        static::enregistrerAudit(
            model: $production,
            action: $action,
            donneesAvant: $production->getOriginal(),
            donneesApres: $production->getChanges(),
        );
    }

    public function deleted(Production $production): void
    {
        static::enregistrerAudit(
            model: $production,
            action: 'suppression',
            donneesAvant: $production->toArray(),
        );
    }
}
