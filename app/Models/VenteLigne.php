<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VenteLigne extends Model
{
    protected $table = 'vente_lignes';

    protected $fillable = [
        'vente_id',
        'produit_id',
        'quantite',
        'kilos',
        'prix_unitaire',
        'montant_ligne',
        'cout_revient',
        'marge_ligne',
    ];

    protected function casts(): array
    {
        return [
            'quantite' => 'decimal:3',
            'kilos' => 'decimal:3',
            'prix_unitaire' => 'decimal:2',
            'montant_ligne' => 'decimal:2',
            'cout_revient' => 'decimal:4',
            'marge_ligne' => 'decimal:2',
        ];
    }

    public function vente(): BelongsTo
    {
        return $this->belongsTo(Vente::class);
    }

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class);
    }
}
