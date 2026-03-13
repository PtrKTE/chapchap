<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StatutInventaire;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inventaire extends Model
{
    protected $table = 'inventaires';

    protected $fillable = [
        'reference',
        'date_inventaire',
        'emplacement_id',
        'statut',
        'observations',
        'created_by',
        'valide_par',
    ];

    protected function casts(): array
    {
        return [
            'date_inventaire' => 'date',
            'statut' => StatutInventaire::class,
        ];
    }

    public function emplacement(): BelongsTo
    {
        return $this->belongsTo(Emplacement::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function validePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'valide_par');
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(InventaireLigne::class);
    }
}
