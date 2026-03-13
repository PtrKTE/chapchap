<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Canal;
use App\Enums\ModePaiement;
use App\Enums\StatutPaiement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vente extends Model
{
    protected $table = 'ventes';

    protected $fillable = [
        'numero_recu',
        'date_vente',
        'client_id',
        'commercial_id',
        'emplacement_id',
        'canal',
        'montant_total',
        'remise',
        'montant_net',
        'montant_recu',
        'montant_restant',
        'statut_paiement',
        'mode_paiement',
        'date_reglement_complet',
        'observations',
        'created_by',
        'annulee',
        'date_annulation',
        'motif_annulation',
    ];

    protected function casts(): array
    {
        return [
            'date_vente' => 'datetime',
            'canal' => Canal::class,
            'statut_paiement' => StatutPaiement::class,
            'mode_paiement' => ModePaiement::class,
            'date_reglement_complet' => 'date',
            'montant_total' => 'decimal:2',
            'remise' => 'decimal:2',
            'montant_net' => 'decimal:2',
            'montant_recu' => 'decimal:2',
            'montant_restant' => 'decimal:2',
            'annulee' => 'boolean',
            'date_annulation' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function commercial(): BelongsTo
    {
        return $this->belongsTo(User::class, 'commercial_id');
    }

    public function emplacement(): BelongsTo
    {
        return $this->belongsTo(Emplacement::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(VenteLigne::class);
    }

    public function paiements(): HasMany
    {
        return $this->hasMany(Paiement::class);
    }

    public function mouvementsStock(): HasMany
    {
        return $this->hasMany(MouvementStock::class);
    }
}
