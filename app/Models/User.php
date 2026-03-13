<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Profil;
use BezhanSalleh\FilamentShield\Traits\HasPanelShield;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasName
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasPanelShield, HasRoles, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'telephone',
        'profil',
        'emplacement_id',
        'actif',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'profil' => Profil::class,
            'actif' => 'boolean',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->actif ?? true;
    }

    /**
     * Nom affiché dans le menu profil Filament.
     * Retourne uniquement le nom (les initiales servent pour l'avatar).
     */
    public function getFilamentName(): string
    {
        return $this->name;
    }

    public function emplacement(): BelongsTo
    {
        return $this->belongsTo(Emplacement::class);
    }

    public function ventes(): HasMany
    {
        return $this->hasMany(Vente::class, 'commercial_id');
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class, 'commercial_id');
    }

    // Relations "created_by" — enregistrements créés par cet utilisateur

    public function lotsCreated(): HasMany
    {
        return $this->hasMany(Lot::class, 'created_by');
    }

    public function productionsCreated(): HasMany
    {
        return $this->hasMany(Production::class, 'created_by');
    }

    public function chargesCreated(): HasMany
    {
        return $this->hasMany(Charge::class, 'created_by');
    }

    public function paiementsCreated(): HasMany
    {
        return $this->hasMany(Paiement::class, 'created_by');
    }

    public function inventairesCreated(): HasMany
    {
        return $this->hasMany(Inventaire::class, 'created_by');
    }

    public function transfertsCreated(): HasMany
    {
        return $this->hasMany(Transfert::class, 'created_by');
    }

    // Relations "responsable" — caisses dont l'utilisateur est responsable

    public function caissesResponsable(): HasMany
    {
        return $this->hasMany(Caisse::class, 'responsable_id');
    }
}
