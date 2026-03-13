<?php

declare(strict_types=1);

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;

/**
 * Page de création d'un utilisateur.
 *
 * Après la sauvegarde en BDD, on synchronise automatiquement le rôle Spatie
 * correspondant au profil choisi. Ainsi, l'utilisateur reçoit immédiatement
 * les permissions associées à son rôle.
 */
class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * Appelé automatiquement APRÈS la création de l'utilisateur en BDD.
     * On en profite pour synchroniser le rôle Spatie.
     */
    protected function afterCreate(): void
    {
        // syncRoles() remplace tous les rôles existants par celui du profil
        // Ex: profil = "commercial" → rôle Spatie = "commercial"
        $this->record->syncRoles([$this->record->profil->value]);
    }
}
