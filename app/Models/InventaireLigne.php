<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventaireLigne extends Model
{
    protected $table = 'inventaire_lignes';

    protected $fillable = [
        'inventaire_id',
        'produit_id',
        'stock_theorique',
        'stock_physique',
        'ecart',
        'justification',
    ];

    protected function casts(): array
    {
        return [
            'stock_theorique' => 'decimal:3',
            'stock_physique' => 'decimal:3',
            'ecart' => 'decimal:3',
        ];
    }

    public function inventaire(): BelongsTo
    {
        return $this->belongsTo(Inventaire::class);
    }

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class);
    }
}
