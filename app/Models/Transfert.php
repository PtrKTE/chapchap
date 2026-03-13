<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StatutTransfert;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transfert extends Model
{
    protected $table = 'transferts';

    protected $fillable = [
        'reference',
        'date_transfert',
        'emplacement_source_id',
        'emplacement_dest_id',
        'statut',
        'observations',
        'created_by',
        'recu_par',
        'date_reception',
    ];

    protected function casts(): array
    {
        return [
            'date_transfert' => 'datetime',
            'statut' => StatutTransfert::class,
            'date_reception' => 'datetime',
        ];
    }

    public function emplacementSource(): BelongsTo
    {
        return $this->belongsTo(Emplacement::class, 'emplacement_source_id');
    }

    public function emplacementDest(): BelongsTo
    {
        return $this->belongsTo(Emplacement::class, 'emplacement_dest_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function recuPar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recu_par');
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(TransfertLigne::class);
    }

    public function mouvementsStock(): HasMany
    {
        return $this->hasMany(MouvementStock::class);
    }
}
