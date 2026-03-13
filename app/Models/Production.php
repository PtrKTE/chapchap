<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StatutProduction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Production extends Model
{
    protected $table = 'productions';

    protected $fillable = [
        'reference',
        'date_production',
        'lot_id',
        'nb_poulets_traites',
        'poids_moyen_entrant',
        'poids_total_entrant',
        'pertes_casse',
        'rendement',
        'statut',
        'observations',
        'created_by',
        'valide_par',
        'date_validation',
    ];

    protected function casts(): array
    {
        return [
            'date_production' => 'date',
            'statut' => StatutProduction::class,
            'date_validation' => 'datetime',
            'poids_moyen_entrant' => 'decimal:3',
            'poids_total_entrant' => 'decimal:3',
            'pertes_casse' => 'decimal:3',
            'rendement' => 'decimal:4',
        ];
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
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
        return $this->hasMany(ProductionLigne::class);
    }

    public function mouvementsStock(): HasMany
    {
        return $this->hasMany(MouvementStock::class);
    }
}
