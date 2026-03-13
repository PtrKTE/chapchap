<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\JournalAudit;
use Illuminate\Auth\Events\Login;

/**
 * Listener qui enregistre chaque connexion utilisateur dans le journal d'audit.
 *
 * Laravel déclenche automatiquement l'événement Login quand un utilisateur
 * s'authentifie avec succès. Ce listener intercepte cet événement et crée
 * une entrée dans journal_audit avec l'action 'connexion'.
 */
class LogSuccessfulLogin
{
    public function handle(Login $event): void
    {
        JournalAudit::create([
            'utilisateur_id'    => $event->user->getKey(),
            'action'            => 'connexion',
            'table_concernee'   => 'users',
            'enregistrement_id' => $event->user->getKey(),
            'donnees_avant'     => null,
            'donnees_apres'     => [
                'email'      => $event->user->email,
                'user_agent' => request()->userAgent(),
            ],
            'adresse_ip'        => request()->ip() ?? '127.0.0.1',
        ]);
    }
}
