<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ModePaiement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Charge extends Model
{
    protected $table = 'charges';

    protected $fillable = [
        'date_charge',
        'categorie_id',
        'libelle',
        'montant',
        'mode_paiement',
        'fournisseur_id',
        'emplacement_id',
        'personne',
        'observations',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date_charge' => 'date',
            'montant' => 'decimal:2',
            'mode_paiement' => ModePaiement::class,
        ];
    }

    public function categorie(): BelongsTo
    {
        return $this->belongsTo(CategorieCharge::class, 'categorie_id');
    }

    public function fournisseur(): BelongsTo
    {
        return $this->belongsTo(Fournisseur::class);
    }

    public function emplacement(): BelongsTo
    {
        return $this->belongsTo(Emplacement::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
