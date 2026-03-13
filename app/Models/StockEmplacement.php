<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockEmplacement extends Model
{
    protected $table = 'stock_emplacements';

    protected $fillable = [
        'produit_id',
        'emplacement_id',
        'quantite',
        'cout_moyen_pondere',
        'valeur_stock',
        'derniere_entree',
        'derniere_sortie',
    ];

    protected function casts(): array
    {
        return [
            'quantite' => 'decimal:3',
            'cout_moyen_pondere' => 'decimal:4',
            'valeur_stock' => 'decimal:2',
            'derniere_entree' => 'datetime',
            'derniere_sortie' => 'datetime',
        ];
    }

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class);
    }

    public function emplacement(): BelongsTo
    {
        return $this->belongsTo(Emplacement::class);
    }
}
