<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransfertLigne extends Model
{
    protected $table = 'transfert_lignes';

    protected $fillable = [
        'transfert_id',
        'produit_id',
        'quantite_envoyee',
        'quantite_recue',
        'ecart',
    ];

    protected function casts(): array
    {
        return [
            'quantite_envoyee' => 'decimal:3',
            'quantite_recue' => 'decimal:3',
            'ecart' => 'decimal:3',
        ];
    }

    public function transfert(): BelongsTo
    {
        return $this->belongsTo(Transfert::class);
    }

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class);
    }
}
