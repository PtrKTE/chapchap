<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TypeCharge;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategorieCharge extends Model
{
    protected $table = 'categories_charges';

    protected $fillable = [
        'nom',
        'type',
        'montant_reference',
        'actif',
    ];

    protected function casts(): array
    {
        return [
            'type' => TypeCharge::class,
            'montant_reference' => 'decimal:2',
            'actif' => 'boolean',
        ];
    }

    public function charges(): HasMany
    {
        return $this->hasMany(Charge::class, 'categorie_id');
    }
}
