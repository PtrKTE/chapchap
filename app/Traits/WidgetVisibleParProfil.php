<?php

declare(strict_types=1);

namespace App\Traits;

use App\Enums\Profil;

/**
 * Trait partagé par tous les widgets Filament pour gérer la visibilité par profil.
 *
 * Règle : le super_admin voit TOUS les widgets (utile en développement / gestion).
 * Les autres utilisateurs voient uniquement les widgets correspondant à leur profil.
 *
 * Usage dans un widget :
 *   use App\Traits\WidgetVisibleParProfil;
 *   public static function canView(): bool {
 *       return static::visiblePour([Profil::GERANT, Profil::RESP_OPERATIONS]);
 *   }
 */
trait WidgetVisibleParProfil
{
    /**
     * Retourne true si l'utilisateur connecté a l'un des profils autorisés,
     * OU s'il a le rôle super_admin (bypass total).
     *
     * @param Profil[] $profils Liste des profils autorisés à voir ce widget
     */
    protected static function visiblePour(array $profils): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        // super_admin voit tout (contourne le filtre par profil)
        if ($user->hasRole('super_admin')) {
            return true;
        }

        return in_array($user->profil, $profils, strict: true);
    }
}
