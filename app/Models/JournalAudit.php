<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modèle pour la table journal_audit — enregistrements immuables.
 *
 * Actions possibles : creation, modification, suppression,
 * annulation, validation, connexion, deconnexion.
 */
class JournalAudit extends Model
{
    protected $table = 'journal_audit';

    public $timestamps = false;

    protected $fillable = [
        'utilisateur_id',
        'action',
        'table_concernee',
        'enregistrement_id',
        'donnees_avant',
        'donnees_apres',
        'adresse_ip',
    ];

    protected function casts(): array
    {
        return [
            'donnees_avant' => 'json',
            'donnees_apres' => 'json',
            'created_at' => 'datetime',
        ];
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'utilisateur_id');
    }
}
