<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Commande de réinitialisation des données transactionnelles.
 *
 * Vide toutes les tables métier (lots, productions, ventes, stock, caisse…)
 * en conservant les référentiels (produits, emplacements, fournisseurs, utilisateurs).
 *
 * Usage : php artisan chapchap:reset-donnees --force
 */
class ResetDonneesTransactionnelles extends Command
{
    protected $signature = 'chapchap:reset-donnees
                            {--force : Ignorer la confirmation interactive}';

    protected $description = 'Réinitialise les données transactionnelles (lots, productions, stock, ventes, caisse) en conservant les référentiels';

    /**
     * Tables à vider, dans l'ordre respectant les clés étrangères
     * (enfants d'abord, parents ensuite).
     */
    private array $tables = [
        // Inventaire
        'inventaire_lignes',
        'inventaires',
        // Transferts
        'transfert_lignes',
        'transferts',
        // Stock
        'mouvements_stock',
        'stock_emplacements',
        // Production
        'production_lignes',
        'productions',
        // Achats
        'lots',
        // Trésorerie
        'charges',
        'caisses',
        // Commercial
        'paiements',
        'vente_lignes',
        'ventes',
        // Eau
        'eau_productions',
    ];

    public function handle(): int
    {
        $this->warn('⚠️  Cette commande supprime TOUTES les données transactionnelles.');
        $this->line('   Tables conservées : produits, emplacements, fournisseurs, utilisateurs, catégories, grilles rendement, rôles/permissions.');
        $this->newLine();

        // Confirmation obligatoire sauf si --force
        if (! $this->option('force')) {
            if (! $this->confirm('Confirmer la réinitialisation des données en ' . config('app.env') . ' ?', false)) {
                $this->info('Annulé.');
                return self::SUCCESS;
            }
        }

        $this->info('Début de la réinitialisation…');

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        foreach ($this->tables as $table) {
            try {
                $count = DB::table($table)->count();
                DB::table($table)->truncate();
                $this->line("  ✓ <fg=green>{$table}</> ({$count} enregistrements supprimés)");
            } catch (\Throwable $e) {
                $this->line("  ✗ <fg=red>{$table}</> : " . $e->getMessage());
            }
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->newLine();
        $this->info('✅ Réinitialisation terminée. La base est prête pour la production.');
        $this->line('   Référentiels intacts : produits, emplacements, fournisseurs, utilisateurs, rôles.');

        return self::SUCCESS;
    }
}
