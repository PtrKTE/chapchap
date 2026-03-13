<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

// ============================================================
// TÂCHES PLANIFIÉES CHAPCHAP
// ============================================================
// Sur le serveur, ajouter au crontab :
//   * * * * * cd /chemin/chapchap && php artisan schedule:run >> /dev/null 2>&1
// ============================================================

// Backup BDD quotidien à 2h du matin (heure Abidjan = UTC)
Schedule::command('backup:run --only-db')
    ->dailyAt('02:00')
    ->timezone('Africa/Abidjan')
    ->withoutOverlapping()
    ->onFailure(function () {
        logger()->error('CHAPCHAP — Backup BDD quotidien échoué');
    });

// Nettoyage des anciens backups (selon la politique dans config/backup.php)
Schedule::command('backup:clean')
    ->dailyAt('03:00')
    ->timezone('Africa/Abidjan');

// Backup complet hebdomadaire (BDD + fichiers) le dimanche à 4h
Schedule::command('backup:run')
    ->weeklyOn(0, '04:00')
    ->timezone('Africa/Abidjan')
    ->withoutOverlapping();

// Vérification santé des backups (alerte si backup trop ancien)
Schedule::command('backup:monitor')
    ->dailyAt('08:00')
    ->timezone('Africa/Abidjan');

// Purge des sessions expirées (table sessions)
Schedule::command('session:gc')
    ->daily()
    ->timezone('Africa/Abidjan');
