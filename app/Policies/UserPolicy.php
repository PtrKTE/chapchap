<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Policy de gestion des utilisateurs.
 *
 * Toutes les actions sont réservées exclusivement au super_admin.
 * Aucun autre rôle — même le gérant — ne peut créer, modifier ou
 * consulter la liste des utilisateurs.
 */
class UserPolicy
{
    use HandlesAuthorization;

    // Vérifie si l'utilisateur connecté est super_admin
    private function isSuperAdmin(User $user): bool
    {
        return $user->hasRole('super_admin');
    }

    public function viewAny(User $user): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function view(User $user): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function update(User $user): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function delete(User $user): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function deleteAny(User $user): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function forceDelete(User $user): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function restore(User $user): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function restoreAny(User $user): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function replicate(User $user): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function reorder(User $user): bool
    {
        return $this->isSuperAdmin($user);
    }
}
