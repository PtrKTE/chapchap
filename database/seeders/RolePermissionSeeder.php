<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * Seeder des permissions par rôle.
 *
 * Matrice de droits basée sur le cahier des charges CHAPCHAP :
 * - Gérant : supervision globale, tout voir, créer/modifier, valider
 * - Resp. Opérations : pilotage quotidien, paramétrage, contrôle
 * - Gestionnaire Stock : mouvements stock, inventaires, lots, caisse
 * - Agent Production : saisie production, consultation stock
 * - Commercial : ventes, clients, paiements, consultation stock
 * - Point de vente : ventes boutique, consultation
 *
 * Usage : php artisan db:seed --class=RolePermissionSeeder
 * Ce seeder est rejouable : il remplace les permissions existantes (syncPermissions).
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Helpers pour générer les noms de permissions Shield
        // Ex: crud('lot') → ['view_lot', 'view_any_lot', 'create_lot', 'update_lot']
        $view = fn (string $resource) => ["view_{$resource}", "view_any_{$resource}"];
        $crud = fn (string $resource) => [...$view($resource), "create_{$resource}", "update_{$resource}"];
        $full = fn (string $resource) => [...$crud($resource), "delete_{$resource}", "delete_any_{$resource}"];

        // ──────────────────────────────────────────────
        // GÉRANT — Supervision globale métier (PAS de gestion utilisateurs/rôles)
        // La gestion des utilisateurs et rôles est réservée au super_admin
        // ──────────────────────────────────────────────
        $gerant = [
            ...$full('lot'),
            ...$full('production'),
            ...$full('produit'),
            ...$full('fournisseur'),
            ...$full('emplacement'),
            ...$full('vente'),
            ...$full('client'),
            ...$full('paiement'),
            ...$full('caisse'),
            ...$full('charge'),
            ...$full('categorie::charge'),
            ...$full('transfert'),
            ...$full('inventaire'),
            ...$full('eau::production'),
            ...$crud('stock::emplacement'),
            ...$view('mouvement::stock'),
        ];

        // ──────────────────────────────────────────────
        // RESP. OPÉRATIONS — Pilotage quotidien, paramétrage
        // ──────────────────────────────────────────────
        $resp_operations = [
            ...$crud('lot'),
            ...$crud('production'),
            ...$crud('produit'),
            ...$crud('fournisseur'),
            ...$crud('emplacement'),
            ...$crud('vente'),
            ...$crud('client'),
            ...$crud('paiement'),
            ...$crud('caisse'),
            ...$crud('charge'),
            ...$crud('categorie::charge'),
            ...$crud('transfert'),
            ...$crud('inventaire'),
            ...$crud('eau::production'),
            ...$crud('stock::emplacement'),
            ...$view('mouvement::stock'),
        ];

        // ──────────────────────────────────────────────
        // GESTIONNAIRE STOCK — Stock, lots, inventaires, caisse
        // ──────────────────────────────────────────────
        $gestionnaire_stock = [
            ...$crud('lot'),
            ...$view('production'),
            ...$crud('transfert'),
            ...$crud('inventaire'),
            ...$crud('stock::emplacement'),
            ...$view('mouvement::stock'),
            ...$view('produit'),
            ...$view('fournisseur'),
            ...$view('emplacement'),
            ...$crud('caisse'),
            ...$view('vente'),
            ...$view('charge'),
        ];

        // ──────────────────────────────────────────────
        // AGENT PRODUCTION — Saisie production
        // ──────────────────────────────────────────────
        $agent_production = [
            ...$crud('production'),
            ...$view('lot'),
            ...$view('produit'),
            ...$view('stock::emplacement'),
            ...$view('mouvement::stock'),
            ...$view('emplacement'),
            ...$crud('eau::production'),
        ];

        // ──────────────────────────────────────────────
        // COMMERCIAL — Ventes, clients, encaissements
        // ──────────────────────────────────────────────
        $commercial = [
            ...$crud('vente'),
            ...$crud('client'),
            ...$crud('paiement'),
            ...$view('produit'),
            ...$view('stock::emplacement'),
            ...$view('emplacement'),
            ...$view('mouvement::stock'),
            ...$view('caisse'),
        ];

        // ──────────────────────────────────────────────
        // POINT DE VENTE — Ventes boutique
        // ──────────────────────────────────────────────
        $point_de_vente = [
            ...$crud('vente'),
            ...$crud('client'),
            ...$crud('paiement'),
            ...$view('produit'),
            ...$view('stock::emplacement'),
            ...$view('emplacement'),
            ...$view('caisse'),
        ];

        // ──────────────────────────────────────────────
        // Assignation des permissions à chaque rôle
        // syncPermissions remplace toutes les permissions du rôle
        // ──────────────────────────────────────────────
        $roles = [
            'gerant'             => $gerant,
            'resp_operations'    => $resp_operations,
            'gestionnaire_stock' => $gestionnaire_stock,
            'agent_production'   => $agent_production,
            'commercial'         => $commercial,
            'point_de_vente'     => $point_de_vente,
        ];

        foreach ($roles as $roleName => $permissions) {
            $role = Role::findByName($roleName);
            $role->syncPermissions($permissions);
            $this->command->info("✅ Rôle '{$roleName}' → " . count($permissions) . ' permissions assignées');
        }

        // panel_user reçoit un accès minimal (consultation seulement)
        $panelUser = Role::findByName('panel_user');
        $panelUser->syncPermissions($view('produit'));
        $this->command->info("✅ Rôle 'panel_user' → 2 permissions assignées");
    }
}
