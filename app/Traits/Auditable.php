<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\JournalAudit;
use Illuminate\Database\Eloquent\Model;

trait Auditable
{
    /**
     * Enregistre une action dans le journal d'audit.
     */
    protected static function enregistrerAudit(
        Model $model,
        string $action,
        ?array $donneesAvant = null,
        ?array $donneesApres = null,
    ): void {
        JournalAudit::create([
            'utilisateur_id' => auth()->id() ?? 1,
            'action' => $action,
            'table_concernee' => $model->getTable(),
            'enregistrement_id' => $model->getKey(),
            'donnees_avant' => $donneesAvant,
            'donnees_apres' => $donneesApres,
            'adresse_ip' => request()->ip() ?? '127.0.0.1',
        ]);
    }
}
