<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TypeMouvement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MouvementStock extends Model
{
    protected $table = 'mouvements_stock';

    protected $fillable = [
        'reference',
        'date_mouvement',
        'produit_id',
        'emplacement_id',
        'type_mouvement',
        'quantite',
        'cout_unitaire',
        'valeur',
        'lot_id',
        'production_id',
        'vente_id',
        'transfert_id',
        'motif',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date_mouvement' => 'datetime',
            'type_mouvement' => TypeMouvement::class,
            'quantite' => 'decimal:3',
            'cout_unitaire' => 'decimal:4',
            'valeur' => 'decimal:2',
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

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    public function production(): BelongsTo
    {
        return $this->belongsTo(Production::class);
    }

    public function vente(): BelongsTo
    {
        return $this->belongsTo(Vente::class);
    }

    public function transfert(): BelongsTo
    {
        return $this->belongsTo(Transfert::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
