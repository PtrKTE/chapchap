<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CategorieProduit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Produit extends Model
{
    protected $table = 'produits';

    protected $fillable = [
        'code',
        'nom',
        'categorie',
        'unite_stock',
        'unite_vente',
        'prix_vente_defaut',
        'seuil_alerte_stock',
        'actif',
    ];

    protected function casts(): array
    {
        return [
            'categorie' => CategorieProduit::class,
            'prix_vente_defaut' => 'decimal:2',
            'seuil_alerte_stock' => 'decimal:2',
            'actif' => 'boolean',
        ];
    }

    public function stockEmplacements(): HasMany
    {
        return $this->hasMany(StockEmplacement::class);
    }

    public function mouvementsStock(): HasMany
    {
        return $this->hasMany(MouvementStock::class);
    }

    public function productionLignes(): HasMany
    {
        return $this->hasMany(ProductionLigne::class);
    }

    public function venteLignes(): HasMany
    {
        return $this->hasMany(VenteLigne::class);
    }

    public function grillesRendement(): HasMany
    {
        return $this->hasMany(GrilleRendement::class);
    }
}
