<?php

declare(strict_types=1);

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

/**
 * Page d'édition d'un utilisateur.
 *
 * Si le profil est modifié, le rôle Spatie est re-synchronisé automatiquement.
 * Le mot de passe n'est mis à jour que s'il est rempli (sinon on garde l'ancien).
 */
class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
        ];
    }

    /**
     * Appelé automatiquement APRÈS la sauvegarde des modifications.
     * On re-synchronise le rôle Spatie selon le profil choisi.
     * Exception : si l'utilisateur est super_admin, on ne touche pas à son rôle.
     */
    protected function afterSave(): void
    {
        // Protéger le super_admin : ne jamais écraser son rôle
        if ($this->record->hasRole('super_admin')) {
            return;
        }

        $this->record->syncRoles([$this->record->profil->value]);
    }
}
