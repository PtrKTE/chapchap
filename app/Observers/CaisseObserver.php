<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Caisse;
use App\Traits\Auditable;

/**
 * Observer pour les caisses — audit trail automatique.
 * Trace la création, modification, clôture et validation.
 */
class CaisseObserver
{
    use Auditable;

    public function created(Caisse $caisse): void
    {
        static::enregistrerAudit($caisse, 'creation', donneesApres: $caisse->toArray());
    }

    public function updated(Caisse $caisse): void
    {
        if ($caisse->wasChanged('statut') && $caisse->statut->value === 'validee') {
            static::enregistrerAudit(
                $caisse,
                'validation',
                donneesAvant: ['statut' => $caisse->getOriginal('statut')],
                donneesApres: ['statut' => 'validee', 'valide_par' => $caisse->valide_par],
            );
        } elseif ($caisse->wasChanged('statut') && $caisse->statut->value === 'cloturee') {
            static::enregistrerAudit(
                $caisse,
                'validation',
                donneesAvant: ['statut' => $caisse->getOriginal('statut')],
                donneesApres: ['statut' => 'cloturee'],
            );
        } else {
            static::enregistrerAudit(
                $caisse,
                'modification',
                donneesAvant: $caisse->getOriginal(),
                donneesApres: $caisse->getChanges(),
            );
        }
    }

    public function deleted(Caisse $caisse): void
    {
        static::enregistrerAudit($caisse, 'suppression', donneesAvant: $caisse->toArray());
    }
}
