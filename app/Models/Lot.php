<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StatutFacture;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lot extends Model
{
    protected $table = 'lots';

    protected $fillable = [
        'reference',
        'date_reception',
        'fournisseur_id',
        'type_produit',
        'quantite_recue',
        'poids_total_lot',
        'quantite_morts',
        'quantite_refuses',
        'quantite_utilisable',
        'prix_unitaire',
        'cout_transport',
        'autres_couts',
        'cout_total',
        'cout_moyen_unitaire',
        'montant_facture',
        'statut_facture',
        'montant_regle',
        'date_reglement',
        'mode_reglement',
        'oeufs_casses',
        'date_production',
        'observations',
        'piece_jointe',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date_reception' => 'date',
            'statut_facture' => StatutFacture::class,
            'date_reglement' => 'date',
            'date_production' => 'date',
            'poids_total_lot' => 'decimal:3',
            'prix_unitaire' => 'decimal:2',
            'cout_transport' => 'decimal:2',
            'autres_couts' => 'decimal:2',
            'cout_total' => 'decimal:2',
            'cout_moyen_unitaire' => 'decimal:4',
            'montant_facture' => 'decimal:2',
            'montant_regle' => 'decimal:2',
        ];
    }

    public function fournisseur(): BelongsTo
    {
        return $this->belongsTo(Fournisseur::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function productions(): HasMany
    {
        return $this->hasMany(Production::class);
    }

    public function mouvementsStock(): HasMany
    {
        return $this->hasMany(MouvementStock::class);
    }
}
