<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrilleRendement extends Model
{
    protected $table = 'grilles_rendement';

    protected $fillable = [
        'poids_poulet_kg',
        'produit_id',
        'poids_produit_kg',
        'prix_vente_kg',
        'valorisation',
    ];

    protected function casts(): array
    {
        return [
            'poids_poulet_kg' => 'decimal:2',
            'poids_produit_kg' => 'decimal:4',
            'prix_vente_kg' => 'decimal:2',
            'valorisation' => 'decimal:2',
        ];
    }

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class);
    }
}
