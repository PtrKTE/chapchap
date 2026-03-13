<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\JournalAudit;
use Illuminate\Auth\Events\Logout;

/**
 * Listener qui enregistre chaque déconnexion utilisateur dans le journal d'audit.
 *
 * Attention : si la session expire par timeout (inactivité), l'événement
 * Logout n'est PAS déclenché. Seules les déconnexions explicites (clic
 * sur "Se déconnecter") sont enregistrées.
 */
class LogSuccessfulLogout
{
    public function handle(Logout $event): void
    {
        // $event->user peut être null si la session a déjà expiré
        if (! $event->user) {
            return;
        }

        JournalAudit::create([
            'utilisateur_id'    => $event->user->getKey(),
            'action'            => 'deconnexion',
            'table_concernee'   => 'users',
            'enregistrement_id' => $event->user->getKey(),
            'donnees_avant'     => null,
            'donnees_apres'     => [
                'email' => $event->user->email,
            ],
            'adresse_ip'        => request()->ip() ?? '127.0.0.1',
        ]);
    }
}
