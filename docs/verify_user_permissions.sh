#!/usr/bin/env bash
# =============================================================================
# CHAPCHAP — Vérification des droits "Gérer les utilisateurs" en production
# Usage : bash docs/verify_user_permissions.sh
#         (à exécuter depuis la racine du projet sur le VPS)
# =============================================================================

APP_DIR="$(cd "$(dirname "$0")/.." && pwd)"
PHP_BIN="${PHP_BIN:-php}"
ARTISAN="$PHP_BIN $APP_DIR/artisan"

echo ""
echo "======================================================"
echo "  CHAPCHAP — Vérification droits gestion utilisateurs"
echo "======================================================"
echo ""

# ── 1. Liste tous les utilisateurs avec leur(s) rôle(s) ──────────────────────
echo "── Utilisateurs & rôles ──────────────────────────────"
$ARTISAN tinker --execute="
\App\Models\User::with('roles')->get()->each(function(\$u) {
    \$roles = \$u->roles->pluck('name')->join(', ') ?: '(aucun rôle)';
    \$profil = \$u->profil?->value ?? '?';
    echo str_pad(\$u->name, 30) . ' | profil: ' . str_pad(\$profil, 22) . ' | rôles: ' . \$roles . PHP_EOL;
});
"

echo ""

# ── 2. Vérifie qui a le rôle super_admin ─────────────────────────────────────
echo "── Utilisateurs super_admin ──────────────────────────"
$ARTISAN tinker --execute="
\$admins = \App\Models\User::role('super_admin')->get();
if (\$admins->isEmpty()) {
    echo 'ATTENTION : aucun super_admin trouvé !' . PHP_EOL;
} else {
    \$admins->each(fn(\$u) => print(\$u->name . ' <' . \$u->email . '>' . PHP_EOL));
}
"

echo ""

# ── 3. Teste la policy pour chaque utilisateur ───────────────────────────────
echo "── Test policy viewAny(User) par utilisateur ─────────"
$ARTISAN tinker --execute="
\App\Models\User::with('roles')->get()->each(function(\$u) {
    \$peut = \$u->can('viewAny', \App\Models\User::class) ? '✅ AUTORISÉ' : '❌ REFUSÉ ';
    echo str_pad(\$u->name, 30) . ' → ' . \$peut . PHP_EOL;
});
"

echo ""

# ── 4. Vérifie les permissions Spatie liées aux users (info) ─────────────────
echo "── Permissions 'user' dans Spatie par rôle ───────────"
$ARTISAN tinker --execute="
\Spatie\Permission\Models\Role::all()->each(function(\$role) {
    \$perms = \$role->permissions->filter(fn(\$p) => str_contains(\$p->name, 'user'))->pluck('name');
    if (\$perms->isNotEmpty()) {
        echo \$role->name . ':' . PHP_EOL;
        \$perms->each(fn(\$p) => print('   - ' . \$p . PHP_EOL));
    }
});
"

echo ""
echo "======================================================"
echo "  Vérification terminée"
echo "======================================================"
echo ""
