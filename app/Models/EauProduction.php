<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EauProduction extends Model
{
    protected $table = 'eau_productions';

    protected $fillable = [
        'date_production',
        'nb_paquets_produits',
        'consommation_sachets',
        'consommation_energie',
        'observations',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date_production' => 'date',
            'consommation_energie' => 'decimal:2',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
