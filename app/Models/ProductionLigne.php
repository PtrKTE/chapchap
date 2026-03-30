<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionLigne extends Model
{
    protected $table = 'production_lignes';

    protected $fillable = [
        'production_id',
        'produit_id',
        'quantite_unite',
        'quantite',
        'cout_unitaire_calcule',
        'valeur_totale',
    ];

    protected function casts(): array
    {
        return [
            'quantite_unite'        => 'integer',
            'quantite'              => 'decimal:3',
            'cout_unitaire_calcule' => 'decimal:4',
            'valeur_totale'         => 'decimal:2',
        ];
    }

    public function production(): BelongsTo
    {
        return $this->belongsTo(Production::class);
    }

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class);
    }
}
