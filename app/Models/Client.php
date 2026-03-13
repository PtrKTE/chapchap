<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ModePaiement;
use App\Enums\TypeClient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    protected $table = 'clients';

    protected $fillable = [
        'code',
        'nom',
        'telephone',
        'email',
        'adresse',
        'quartier_zone',
        'type_client',
        'secteur_activite',
        'contact_principal',
        'fonction_contact',
        'commercial_id',
        'mode_paiement_habituel',
        'conditions_paiement',
        'date_enregistrement',
        'actif',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'type_client' => TypeClient::class,
            'mode_paiement_habituel' => ModePaiement::class,
            'date_enregistrement' => 'date',
            'actif' => 'boolean',
        ];
    }

    public function commercial(): BelongsTo
    {
        return $this->belongsTo(User::class, 'commercial_id');
    }

    public function ventes(): HasMany
    {
        return $this->hasMany(Vente::class);
    }
}
