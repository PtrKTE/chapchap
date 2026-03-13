<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\StatutInventaire;
use App\Models\Inventaire;
use App\Traits\Auditable;

/**
 * Observer pour les inventaires — audit trail automatique.
 */
class InventaireObserver
{
    use Auditable;

    public function created(Inventaire $inventaire): void
    {
        static::enregistrerAudit($inventaire, 'creation', donneesApres: $inventaire->toArray());
    }

    public function updated(Inventaire $inventaire): void
    {
        // Si le statut passe à "valide", on enregistre une "validation"
        if ($inventaire->wasChanged('statut') && $inventaire->statut === StatutInventaire::VALIDE) {
            static::enregistrerAudit(
                $inventaire,
                'validation',
                donneesAvant: ['statut' => $inventaire->getOriginal('statut')],
                donneesApres: ['statut' => 'valide', 'valide_par' => $inventaire->valide_par],
            );
        } else {
            static::enregistrerAudit(
                $inventaire,
                'modification',
                donneesAvant: $inventaire->getOriginal(),
                donneesApres: $inventaire->getChanges(),
            );
        }
    }

    public function deleted(Inventaire $inventaire): void
    {
        static::enregistrerAudit($inventaire, 'suppression', donneesAvant: $inventaire->toArray());
    }
}
