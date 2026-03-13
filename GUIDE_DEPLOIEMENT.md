# CHAPCHAP — Guide de Deploiement Serveur

## Pre-requis serveur

| Composant | Version minimum |
|-----------|----------------|
| PHP | 8.2+ avec extensions : mbstring, xml, curl, mysql, zip, gd, intl, bcmath |
| MySQL | 8.0+ (ou MariaDB 10.6+) |
| Composer | 2.x |
| Git | 2.x |
| Nginx ou Apache | Version recente |
| Node.js | 18+ (pour compiler les assets si besoin) |

### Extensions PHP requises

```bash
sudo apt install php8.2-fpm php8.2-mysql php8.2-mbstring php8.2-xml \
    php8.2-curl php8.2-zip php8.2-gd php8.2-intl php8.2-bcmath \
    php8.2-readline php8.2-opcache
```

---

## Option 1 : Deploiement avec Laravel Forge (recommande)

Laravel Forge automatise la configuration du serveur.

1. Creer un compte sur [forge.laravel.com](https://forge.laravel.com)
2. Connecter votre VPS (DigitalOcean, Hetzner, etc.)
3. Creer un nouveau site avec :
   - **Repository** : votre repo Git
   - **Branch** : main
   - **PHP** : 8.2+
   - **Base de donnees** : MySQL 8
4. Dans les parametres du site :
   - **Environment** : copier le contenu de `.env.production` et remplir les valeurs
   - **Deploy script** : utiliser le contenu de `deploy.sh`
5. Configurer le **Scheduler** :
   - Forge ajoute automatiquement la crontab si vous activez le scheduler
6. Activer **SSL** (Let's Encrypt) via Forge

---

## Option 2 : Deploiement manuel sur VPS

### 1. Preparer le serveur

```bash
# Mettre a jour le systeme
sudo apt update && sudo apt upgrade -y

# Installer les pre-requis
sudo apt install nginx mysql-server git unzip curl -y

# Installer PHP 8.2
sudo add-apt-repository ppa:ondrej/php -y
sudo apt install php8.2-fpm php8.2-mysql php8.2-mbstring php8.2-xml \
    php8.2-curl php8.2-zip php8.2-gd php8.2-intl php8.2-bcmath -y

# Installer Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### 2. Configurer MySQL

```bash
sudo mysql_secure_installation

# Creer la base et l'utilisateur
sudo mysql -e "CREATE DATABASE chapchap CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER 'chapchap_user'@'localhost' IDENTIFIED BY 'VOTRE_MOT_DE_PASSE';"
sudo mysql -e "GRANT ALL PRIVILEGES ON chapchap.* TO 'chapchap_user'@'localhost';"
sudo mysql -e "FLUSH PRIVILEGES;"
```

### 3. Cloner le projet

```bash
cd /var/www
sudo git clone VOTRE_REPO_GIT chapchap
sudo chown -R www-data:www-data chapchap
cd chapchap
```

### 4. Configurer l'environnement

```bash
cp .env.production .env
# Editer .env avec les valeurs reelles
nano .env

# Generer la cle d'application
php artisan key:generate
```

### 5. Installer et deployer

```bash
# Dependances
composer install --no-dev --optimize-autoloader

# Migrations
php artisan migrate --force

# Seeders (premier deploiement uniquement)
php artisan db:seed --force

# Permissions Filament Shield
php artisan shield:generate --all
php artisan shield:super-admin

# Caches
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan icons:cache
php artisan storage:link

# Permissions fichiers
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

### 6. Configurer Nginx

Creer `/etc/nginx/sites-available/chapchap` :

```nginx
server {
    listen 80;
    server_name chapchap.votredomaine.ci;
    root /var/www/chapchap/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header X-XSS-Protection "1; mode=block";

    index index.php;
    charset utf-8;

    # Taille max upload (pour imports Excel, photos factures)
    client_max_body_size 20M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    # Bloquer l'acces aux fichiers sensibles
    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/chapchap /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### 7. SSL avec Let's Encrypt

```bash
sudo apt install certbot python3-certbot-nginx -y
sudo certbot --nginx -d chapchap.votredomaine.ci
```

### 8. Configurer le Scheduler (crontab)

```bash
sudo crontab -u www-data -e
# Ajouter cette ligne :
* * * * * cd /var/www/chapchap && php artisan schedule:run >> /dev/null 2>&1
```

---

## Backups

### Configuration

Les backups sont geres par `spatie/laravel-backup` :
- **Backup BDD quotidien** : tous les jours a 2h00
- **Backup complet** (BDD + fichiers) : dimanche a 4h00
- **Nettoyage** : suppression automatique des anciens backups
- **Stockage** : `storage/app/CHAPCHAP/` par defaut

### Verifier les backups

```bash
# Lister les backups existants
php artisan backup:list

# Lancer un backup manuel
php artisan backup:run --only-db

# Verifier la sante des backups
php artisan backup:monitor
```

### Restaurer un backup

```bash
# Decompresser le backup
cd storage/app/CHAPCHAP
unzip YYYY-MM-DD-HH-II-SS.zip -d /tmp/restore

# Restaurer la BDD
gunzip /tmp/restore/db-dumps/mysql-chapchap.sql.gz
mysql -u chapchap_user -p chapchap < /tmp/restore/db-dumps/mysql-chapchap.sql
```

---

## Mise a jour de l'application

Pour deployer une nouvelle version :

```bash
cd /var/www/chapchap
./deploy.sh --migrate
```

Ou manuellement :

```bash
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan filament:upgrade
```

---

## Monitoring

### Logs

```bash
# Voir les logs en temps reel
tail -f storage/logs/laravel.log

# Logs du jour
cat storage/logs/laravel-$(date +%Y-%m-%d).log
```

### Etat du systeme

```bash
# Verifier que PHP-FPM tourne
sudo systemctl status php8.2-fpm

# Verifier Nginx
sudo systemctl status nginx

# Verifier MySQL
sudo systemctl status mysql

# Espace disque
df -h
```

### En cas de probleme

1. Verifier les logs : `storage/logs/laravel.log`
2. Vider les caches : `php artisan cache:clear && php artisan config:clear`
3. Verifier les permissions : `chmod -R 775 storage bootstrap/cache`
4. Verifier la connexion BDD : `php artisan tinker` puis `DB::connection()->getPdo()`

---

## Securite

- `APP_DEBUG=false` obligatoire en production
- `SESSION_ENCRYPT=true` pour chiffrer les sessions
- SSL/HTTPS active (Let's Encrypt)
- Mots de passe hashes avec bcrypt (12 rounds)
- Audit trail dans `journal_audit` pour toutes les actions
- Permissions RBAC via Filament Shield
- Pas d'acces direct aux fichiers sensibles (.env, .git)
