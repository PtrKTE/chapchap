<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TypeEmplacement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Emplacement extends Model
{
    protected $fillable = [
        'nom',
        'type',
        'adresse',
        'telephone',
        'responsable_id',
        'actif',
    ];

    protected function casts(): array
    {
        return [
            'type' => TypeEmplacement::class,
            'actif' => 'boolean',
        ];
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    public function utilisateurs(): HasMany
    {
        return $this->hasMany(User::class, 'emplacement_id');
    }

    public function stockEmplacements(): HasMany
    {
        return $this->hasMany(StockEmplacement::class);
    }

    public function ventes(): HasMany
    {
        return $this->hasMany(Vente::class);
    }

    public function caisses(): HasMany
    {
        return $this->hasMany(Caisse::class);
    }

    public function transfertsSortants(): HasMany
    {
        return $this->hasMany(Transfert::class, 'emplacement_source_id');
    }

    public function transfertsEntrants(): HasMany
    {
        return $this->hasMany(Transfert::class, 'emplacement_dest_id');
    }

    public function mouvementsStock(): HasMany
    {
        return $this->hasMany(MouvementStock::class);
    }

    public function charges(): HasMany
    {
        return $this->hasMany(Charge::class);
    }

    public function inventaires(): HasMany
    {
        return $this->hasMany(Inventaire::class);
    }
}
