#!/bin/bash
# ==============================================================
# CHAPCHAP — Script de déploiement production
# ==============================================================
# Usage :
#   ./deploy.sh              → Déploiement standard
#   ./deploy.sh --fresh      → Premier déploiement (migrations fresh)
#   ./deploy.sh --migrate    → Avec migration de la BDD
# ==============================================================

set -e

# Couleurs pour les messages
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

# Répertoire de l'application
APP_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$APP_DIR"

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}  CHAPCHAP — Déploiement en cours...${NC}"
echo -e "${GREEN}========================================${NC}"

# -----------------------------------------------
# 1. Récupérer le code depuis Git
# -----------------------------------------------
echo -e "\n${YELLOW}[1/8] Récupération du code source...${NC}"
git pull origin main

# -----------------------------------------------
# 2. Installer les dépendances PHP (sans dev)
# -----------------------------------------------
echo -e "\n${YELLOW}[2/8] Installation des dépendances Composer...${NC}"
composer install --no-dev --optimize-autoloader --no-interaction

# -----------------------------------------------
# 3. Migrations (si demandé)
# -----------------------------------------------
if [ "$1" == "--fresh" ]; then
    echo -e "\n${RED}[3/8] ATTENTION: Migration fresh (reset complet BDD)...${NC}"
    read -p "Êtes-vous sûr ? Cela effacera toutes les données ! (oui/non) : " confirm
    if [ "$confirm" == "oui" ]; then
        php artisan migrate:fresh --seed --force
    else
        echo "Migration fresh annulée."
        exit 1
    fi
elif [ "$1" == "--migrate" ]; then
    echo -e "\n${YELLOW}[3/8] Exécution des migrations...${NC}"
    php artisan migrate --force
else
    echo -e "\n${YELLOW}[3/8] Pas de migration demandée (passer --migrate si besoin)${NC}"
fi

# -----------------------------------------------
# 4. Caches Laravel (optimisation production)
# -----------------------------------------------
echo -e "\n${YELLOW}[4/8] Optimisation des caches Laravel...${NC}"
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan icons:cache

# -----------------------------------------------
# 5. Assets Filament
# -----------------------------------------------
echo -e "\n${YELLOW}[5/8] Publication des assets Filament...${NC}"
php artisan filament:upgrade

# -----------------------------------------------
# 6. Liens symboliques storage
# -----------------------------------------------
echo -e "\n${YELLOW}[6/8] Création du lien symbolique storage...${NC}"
php artisan storage:link 2>/dev/null || true

# -----------------------------------------------
# 7. Permissions des répertoires
# -----------------------------------------------
echo -e "\n${YELLOW}[7/8] Configuration des permissions...${NC}"
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

# -----------------------------------------------
# 8. Redémarrer les workers (si queue utilisée)
# -----------------------------------------------
echo -e "\n${YELLOW}[8/8] Redémarrage des services...${NC}"
php artisan queue:restart 2>/dev/null || true

# -----------------------------------------------
# Résumé
# -----------------------------------------------
echo -e "\n${GREEN}========================================${NC}"
echo -e "${GREEN}  Déploiement terminé avec succès !${NC}"
echo -e "${GREEN}========================================${NC}"
echo -e "  App   : $(php artisan --version)"
echo -e "  PHP   : $(php -v | head -1)"
echo -e "  Env   : $(grep APP_ENV .env | cut -d= -f2)"
echo -e "  Debug : $(grep APP_DEBUG .env | cut -d= -f2)"
echo -e "${GREEN}========================================${NC}"
