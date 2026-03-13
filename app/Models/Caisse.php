<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StatutCaisse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Caisse extends Model
{
    protected $table = 'caisses';

    protected $fillable = [
        'date_caisse',
        'emplacement_id',
        'total_encaisse',
        'total_credits_encaisses',
        'total_decaisse',
        'solde_caisse',
        'montant_verse',
        'depot_wave_mtn',
        'depot_cheque',
        'montant_especes',
        'ecart',
        'statut',
        'responsable_id',
        'valide_par',
        'observations',
    ];

    protected function casts(): array
    {
        return [
            'date_caisse' => 'date',
            'statut' => StatutCaisse::class,
            'total_encaisse' => 'decimal:2',
            'total_credits_encaisses' => 'decimal:2',
            'total_decaisse' => 'decimal:2',
            'solde_caisse' => 'decimal:2',
            'montant_verse' => 'decimal:2',
            'depot_wave_mtn' => 'decimal:2',
            'depot_cheque' => 'decimal:2',
            'montant_especes' => 'decimal:2',
            'ecart' => 'decimal:2',
        ];
    }

    public function emplacement(): BelongsTo
    {
        return $this->belongsTo(Emplacement::class);
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    public function validePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'valide_par');
    }
}
