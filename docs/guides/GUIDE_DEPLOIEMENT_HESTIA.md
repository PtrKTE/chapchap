# CHAPCHAP — Guide de Deploiement sur VPS Hestia

## Ton serveur actuel

| Composant | Version/Detail |
|-----------|---------------|
| **OS** | Ubuntu 24.04 (x86_64) |
| **Panel** | Hestia Control Panel v1.9.4 |
| **Web** | Nginx (reverse proxy) + Apache2 |
| **PHP** | 8.3-fpm |
| **BDD** | MariaDB |
| **Mail** | exim4 + dovecot |
| **Securite** | fail2ban + iptables |
| **FTP** | vsftpd |

---

## ETAPE 1 : Optimiser la RAM du serveur (recommande)

ClamAV consomme ~1400 Mo de RAM. Si tu n'utilises pas le scan antivirus des emails,
desactive-le pour liberer de la memoire :

```bash
# Se connecter en SSH
ssh root@vps114082.serveur-vps.net

# Verifier la RAM disponible
free -h

# Desactiver ClamAV si non necessaire (libere ~1.4 Go)
sudo systemctl stop clamav-daemon clamav-freshclam
sudo systemctl disable clamav-daemon clamav-freshclam

# Desactiver SpamAssassin si non necessaire (libere ~380 Mo)
sudo systemctl stop spamassassin
sudo systemctl disable spamassassin

# Verifier la RAM apres
free -h
```

> **Resultat attendu** : ~1.8 Go de RAM liberee, ce qui est confortable pour Laravel + MariaDB.

---

## ETAPE 2 : Installer les extensions PHP manquantes

PHP 8.3 est deja installe, mais il faut verifier que toutes les extensions
requises par Laravel 11 et Filament 3 sont presentes.

```bash
# Verifier les extensions actuelles
php8.3 -m

# Installer les extensions manquantes pour Laravel/Filament
sudo apt update
sudo apt install -y \
    php8.3-mbstring \
    php8.3-xml \
    php8.3-curl \
    php8.3-mysql \
    php8.3-zip \
    php8.3-gd \
    php8.3-intl \
    php8.3-bcmath \
    php8.3-readline \
    php8.3-opcache \
    php8.3-fileinfo \
    php8.3-tokenizer

# Redemarrer PHP-FPM pour prendre en compte
sudo systemctl restart php8.3-fpm
```

### Verifier que tout est bon

```bash
php8.3 -m | grep -E "mbstring|xml|curl|mysqlnd|zip|gd|intl|bcmath|fileinfo"
```

Chaque extension doit apparaitre dans la liste.

---

## ETAPE 3 : Installer Composer et Git

```bash
# Verifier si Git est installe (normalement oui sur Ubuntu 24.04)
git --version

# Installer Composer globalement
cd /tmp
curl -sS https://getcomposer.org/installer | php8.3
sudo mv composer.phar /usr/local/bin/composer

# Verifier
composer --version
```

---

## ETAPE 4 : Installer Node.js (pour compiler les assets Filament/Vite)

```bash
# Installer Node.js 20 LTS via NodeSource
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs

# Verifier
node -v    # doit afficher v20.x
npm -v     # doit afficher 10.x
```

---

## ETAPE 5 : Creer le domaine dans Hestia

### 5a. Via l'interface web Hestia

1. Connecte-toi a Hestia : `https://vps114082.serveur-vps.net:8083`
2. Va dans **WEB** > **Add Web Domain**
3. Remplis :
   - **Domain** : `chapchap.tondomaine.ci` (ou ton domaine reel)
   - **IP Address** : l'IP de ton VPS
   - **Proxy Support** : Active (Nginx devant Apache)
   - **SSL** : Active + cocher "Let's Encrypt" (si le domaine pointe deja vers le VPS)
4. Cliquer **Save**

### 5b. Ou via la ligne de commande Hestia (CLI)

```bash
# Creer le domaine web pour l'utilisateur admin
v-add-web-domain admin chapchap.tondomaine.ci

# Activer SSL Let's Encrypt (le domaine doit pointer vers le VPS)
v-add-letsencrypt-domain admin chapchap.tondomaine.ci
```

### Structure creee par Hestia

Hestia cree automatiquement cette structure :
```
/home/admin/web/chapchap.tondomaine.ci/
├── document_errors/       # Pages d'erreur personnalisees
├── private/               # Fichiers prives (hors acces web)
├── public_html/           # <-- RACINE WEB (c'est ici qu'on deploie)
├── public_shtml/          # Pour les pages d'erreur SSL
└── logs/                  # Logs d'acces et erreurs
```

> **IMPORTANT** : la racine web est `public_html/`. Laravel a un dossier `public/`.
> On va deployer le code Laravel dans le dossier parent et faire pointer `public_html`
> vers le dossier `public/` de Laravel.

---

## ETAPE 6 : Creer la base de donnees dans Hestia

### 6a. Via l'interface web Hestia

1. Va dans **DB** > **Add Database**
2. Remplis :
   - **Database** : `chapchap` (sera prefixe: `admin_chapchap`)
   - **User** : `chapchap` (sera prefixe: `admin_chapchap`)
   - **Password** : un mot de passe fort
   - **Type** : MariaDB
3. Cliquer **Save**

### 6b. Ou via la ligne de commande

```bash
# Creer la BDD (Hestia prefixe avec le nom utilisateur)
v-add-database admin chapchap chapchap 'MotDePasseSecurise123!' mysql
```

### Informations de connexion resultantes

```
Hote     : localhost
Port     : 3306
BDD      : admin_chapchap
User     : admin_chapchap
Password : MotDePasseSecurise123!
```

> **Note** : Hestia prefixe le nom de la base et de l'utilisateur avec le nom du user
> systeme (`admin`). Donc `chapchap` devient `admin_chapchap`.

---

## ETAPE 7 : Deployer le code Laravel

### 7a. Preparer le depot Git

D'abord, depuis ta machine locale, pousse ton code sur un depot Git (GitHub, GitLab, etc.) :

```bash
# Sur ta machine locale (dans /Applications/XAMPP/xamppfiles/htdocs/chapchap)
git remote add origin git@github.com:TON_USER/chapchap.git
git push -u origin main
```

### 7b. Cloner sur le serveur

```bash
# Se connecter en SSH
ssh admin@vps114082.serveur-vps.net

# Aller dans le dossier du domaine
cd /home/admin/web/chapchap.tondomaine.ci

# Sauvegarder le public_html original
mv public_html public_html_backup

# Cloner le projet
git clone git@github.com:TON_USER/chapchap.git project

# Creer un lien symbolique : public_html -> project/public
ln -s /home/admin/web/chapchap.tondomaine.ci/project/public public_html
```

### Structure resultante

```
/home/admin/web/chapchap.tondomaine.ci/
├── project/                # <-- Code Laravel complet
│   ├── app/
│   ├── bootstrap/
│   ├── config/
│   ├── database/
│   ├── public/             # Racine web Laravel
│   │   └── index.php
│   ├── resources/
│   ├── routes/
│   ├── storage/
│   ├── vendor/
│   ├── .env
│   └── ...
├── public_html -> project/public   # <-- LIEN SYMBOLIQUE
├── public_html_backup/              # Ancien dossier (a supprimer plus tard)
└── logs/
```

---

## ETAPE 8 : Configurer l'environnement (.env)

```bash
cd /home/admin/web/chapchap.tondomaine.ci/project

# Copier le fichier d'environnement
cp .env.example .env

# Editer avec nano
nano .env
```

### Contenu du .env de production

```env
APP_NAME=CHAPCHAP
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_TIMEZONE=Africa/Abidjan
APP_URL=https://chapchap.tondomaine.ci

APP_LOCALE=fr
APP_FALLBACK_LOCALE=fr
APP_FAKER_LOCALE=fr_FR

BCRYPT_ROUNDS=12

LOG_CHANNEL=daily
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=admin_chapchap
DB_USERNAME=admin_chapchap
DB_PASSWORD=MotDePasseSecurise123!

SESSION_DRIVER=database
SESSION_LIFETIME=480
SESSION_ENCRYPT=true

CACHE_STORE=file
QUEUE_CONNECTION=database

MAIL_MAILER=smtp
MAIL_HOST=localhost
MAIL_PORT=25
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="noreply@chapchap.tondomaine.ci"
MAIL_FROM_NAME="CHAPCHAP"

FILESYSTEM_DISK=local
```

---

## ETAPE 9 : Installer les dependances et deployer

```bash
cd /home/admin/web/chapchap.tondomaine.ci/project

# Installer les dependances PHP (sans les packages de dev)
composer install --no-dev --optimize-autoloader

# Generer la cle d'application
php8.3 artisan key:generate

# Installer les dependances Node et compiler les assets
npm ci
npm run build

# Lancer les migrations
php8.3 artisan migrate --force

# Lancer les seeders (PREMIER DEPLOIEMENT UNIQUEMENT)
php8.3 artisan db:seed --force

# Generer les permissions Filament Shield
php8.3 artisan shield:generate --all
php8.3 artisan shield:super-admin

# Creer le lien symbolique storage -> public
php8.3 artisan storage:link

# Mettre en cache pour la performance
php8.3 artisan config:cache
php8.3 artisan route:cache
php8.3 artisan view:cache
php8.3 artisan event:cache
php8.3 artisan icons:cache
```

---

## ETAPE 10 : Configurer les permissions fichiers

```bash
cd /home/admin/web/chapchap.tondomaine.ci/project

# Le proprietaire doit etre l'utilisateur admin (pas root)
sudo chown -R admin:admin .

# Permissions standard Laravel
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;

# Permissions d'ecriture pour storage et cache
chmod -R 775 storage bootstrap/cache

# S'assurer que le serveur web peut ecrire dans storage
sudo chown -R admin:www-data storage bootstrap/cache
```

---

## ETAPE 11 : Configurer Apache pour Laravel (template Hestia)

Hestia utilise des templates pour configurer Apache. Il faut en creer un pour Laravel.

### Creer le template Apache

```bash
# Copier le template par defaut
sudo cp /usr/local/hestia/data/templates/web/apache2/default.tpl \
        /usr/local/hestia/data/templates/web/apache2/laravel.tpl

sudo cp /usr/local/hestia/data/templates/web/apache2/default.stpl \
        /usr/local/hestia/data/templates/web/apache2/laravel.stpl
```

### Editer le template HTTP (laravel.tpl)

```bash
sudo nano /usr/local/hestia/data/templates/web/apache2/laravel.tpl
```

Remplacer le contenu par :

```apache
<VirtualHost %ip%:%web_port%>

    ServerName %domain_idn%
    %alias_string%
    ServerAdmin %email%
    DocumentRoot %sdocroot%

    # Redirection vers HTTPS
    RewriteEngine On
    RewriteCond %{HTTPS} off
    RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

    <Directory %sdocroot%>
        AllowOverride All
        Options -Indexes +FollowSymLinks
        Require all granted

        # Rewrite pour Laravel
        RewriteEngine On
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteRule ^ index.php [L]
    </Directory>

    # Taille max upload (imports Excel, photos factures)
    LimitRequestBody 20971520

    # Logs
    CustomLog /var/log/%web_system%/domains/%domain%.bytes bytes
    CustomLog /var/log/%web_system%/domains/%domain%.log combined
    ErrorLog /var/log/%web_system%/domains/%domain%.error.log

    # PHP-FPM
    IncludeOptional /etc/apache2/conf.d/php-fpm-apache.conf
    SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1

    IncludeOptional /home/%user%/conf/web/%domain%/apache2.conf_*

</VirtualHost>
```

### Editer le template HTTPS (laravel.stpl)

```bash
sudo nano /usr/local/hestia/data/templates/web/apache2/laravel.stpl
```

Remplacer le contenu par :

```apache
<VirtualHost %ip%:%web_ssl_port%>

    ServerName %domain_idn%
    %alias_string%
    ServerAdmin %email%
    DocumentRoot %sdocroot%

    SSLEngine on
    SSLCertificateFile %ssl_crt%
    SSLCertificateKeyFile %ssl_key%
    %ssl_ca_str% SSLCertificateChainFile %ssl_ca%

    <Directory %sdocroot%>
        AllowOverride All
        Options -Indexes +FollowSymLinks
        Require all granted

        # Rewrite pour Laravel
        RewriteEngine On
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteRule ^ index.php [L]
    </Directory>

    # Taille max upload
    LimitRequestBody 20971520

    # Headers de securite
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"

    # Logs
    CustomLog /var/log/%web_system%/domains/%domain%.bytes bytes
    CustomLog /var/log/%web_system%/domains/%domain%.log combined
    ErrorLog /var/log/%web_system%/domains/%domain%.error.log

    # PHP-FPM
    IncludeOptional /etc/apache2/conf.d/php-fpm-apache.conf
    SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1

    IncludeOptional /home/%user%/conf/web/%domain%/apache2.ssl.conf_*

</VirtualHost>
```

### Appliquer le template au domaine

```bash
# Via Hestia CLI
v-change-web-domain-tpl admin chapchap.tondomaine.ci laravel

# Redemarrer Apache
sudo systemctl restart apache2
```

> **Ou via l'interface Hestia** : WEB > Editer le domaine > Web Template > Choisir "laravel"

---

## ETAPE 12 : Configurer le template Nginx (proxy)

Il faut aussi creer un template Nginx adapte.

```bash
# Copier le template par defaut
sudo cp /usr/local/hestia/data/templates/web/nginx/default.tpl \
        /usr/local/hestia/data/templates/web/nginx/laravel.tpl

sudo cp /usr/local/hestia/data/templates/web/nginx/default.stpl \
        /usr/local/hestia/data/templates/web/nginx/laravel.stpl
```

### Editer le template Nginx HTTP (laravel.tpl)

```bash
sudo nano /usr/local/hestia/data/templates/web/nginx/laravel.tpl
```

```nginx
server {
    listen      %ip%:%proxy_port%;
    server_name %domain_idn% %alias_idn%;

    # Redirection HTTPS
    return 301 https://$host$request_uri;
}
```

### Editer le template Nginx HTTPS (laravel.stpl)

```bash
sudo nano /usr/local/hestia/data/templates/web/nginx/laravel.stpl
```

```nginx
server {
    listen      %ip%:%proxy_ssl_port% ssl;
    server_name %domain_idn% %alias_idn%;

    ssl_certificate     %ssl_pem%;
    ssl_certificate_key %ssl_key%;

    # SSL settings
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_prefer_server_ciphers on;

    # Taille max upload (20 Mo pour imports Excel, photos factures)
    client_max_body_size 20M;

    # Gzip compression
    gzip on;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml text/javascript image/svg+xml;
    gzip_min_length 1000;

    # Headers securite
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    # Racine = dossier public de Laravel (via le lien symbolique)
    root %sdocroot%;
    index index.php;
    charset utf-8;

    # Assets statiques directement servis par Nginx (rapide)
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 30d;
        access_log off;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
    }

    # Favicon et robots
    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    # Bloquer les fichiers sensibles
    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Toutes les requetes passent a Apache (qui gere PHP-FPM)
    location / {
        proxy_pass https://%ip%:%web_ssl_port%;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # Logs
    error_log /var/log/%web_system%/domains/%domain%.error.log error;

    include /home/%user%/conf/web/%domain%/nginx.ssl.conf_*;
}
```

### Appliquer le template Nginx

```bash
v-change-web-domain-proxy-tpl admin chapchap.tondomaine.ci laravel

# Redemarrer Nginx
sudo systemctl restart nginx
```

---

## ETAPE 13 : Configurer PHP-FPM pour la performance

```bash
# Editer la config du pool PHP
sudo nano /etc/php/8.3/fpm/pool.d/www.conf
```

Modifier ces valeurs :

```ini
; Augmenter la memoire et les timeouts pour Filament/Excel
pm = dynamic
pm.max_children = 10
pm.start_servers = 3
pm.min_spare_servers = 2
pm.max_spare_servers = 5
pm.max_requests = 500
```

```bash
# Editer php.ini
sudo nano /etc/php/8.3/fpm/php.ini
```

Modifier ces valeurs :

```ini
; Memoire (Filament + imports Excel)
memory_limit = 256M

; Upload (photos factures, imports Excel)
upload_max_filesize = 20M
post_max_size = 25M

; Timezone
date.timezone = Africa/Abidjan

; OPcache (performance)
opcache.enable = 1
opcache.memory_consumption = 128
opcache.max_accelerated_files = 10000
opcache.revalidate_freq = 60
```

```bash
# Redemarrer PHP-FPM
sudo systemctl restart php8.3-fpm
```

---

## ETAPE 14 : Configurer le Cron (Scheduler Laravel)

```bash
# Ajouter le cron pour le scheduler Laravel
# Methode Hestia CLI
v-add-cron-job admin '*/1' '*' '*' '*' '*' 'cd /home/admin/web/chapchap.tondomaine.ci/project && php8.3 artisan schedule:run >> /dev/null 2>&1'
```

Ou manuellement :

```bash
crontab -e -u admin
```

Ajouter :

```
* * * * * cd /home/admin/web/chapchap.tondomaine.ci/project && php8.3 artisan schedule:run >> /dev/null 2>&1
```

---

## ETAPE 15 : Configurer le DNS

Chez ton registrar de domaine (ou dans Hestia si tu geres le DNS) :

| Type | Nom | Valeur | TTL |
|------|-----|--------|-----|
| A | chapchap | IP_DE_TON_VPS | 3600 |
| CNAME | www.chapchap | chapchap.tondomaine.ci | 3600 |

> **Note** : Le DNS peut prendre jusqu'a 24h pour se propager, mais c'est souvent
> quelques minutes.

### Verifier la propagation DNS

```bash
# Depuis ta machine locale
dig chapchap.tondomaine.ci +short
# Doit afficher l'IP de ton VPS

# Ou en ligne : https://dnschecker.org
```

---

## ETAPE 16 : Activer SSL (Let's Encrypt)

Une fois le DNS propage :

```bash
# Via Hestia CLI
v-add-letsencrypt-domain admin chapchap.tondomaine.ci

# Forcer le renouvellement automatique (Hestia le fait deja via cron)
v-list-letsencrypt-domain admin chapchap.tondomaine.ci
```

Ou via l'interface Hestia : WEB > Editer le domaine > SSL > Let's Encrypt > Save

---

## ETAPE 17 : Verifier le deploiement

### Tests a effectuer

```bash
# 1. Verifier que le site repond
curl -I https://chapchap.tondomaine.ci

# 2. Verifier la connexion BDD
cd /home/admin/web/chapchap.tondomaine.ci/project
php8.3 artisan tinker --execute="echo DB::connection()->getDatabaseName();"

# 3. Verifier les routes
php8.3 artisan route:list | head -20

# 4. Verifier le statut des migrations
php8.3 artisan migrate:status

# 5. Verifier Filament
# Ouvrir dans le navigateur : https://chapchap.tondomaine.ci/admin
```

### Checklist de verification

- [ ] La page d'accueil s'affiche sans erreur
- [ ] La page /admin affiche le login Filament
- [ ] Connexion avec le compte super-admin fonctionne
- [ ] Le dashboard Filament s'affiche correctement
- [ ] Les listes (Lots, Produits, Clients) affichent les donnees seedees
- [ ] Upload d'un fichier (photo facture) fonctionne
- [ ] Le SSL est actif (cadenas vert dans le navigateur)

---

## ETAPE 18 : Configurer les backups automatiques

```bash
cd /home/admin/web/chapchap.tondomaine.ci/project

# Tester le backup manuel
php8.3 artisan backup:run --only-db

# Verifier
php8.3 artisan backup:list
```

Le scheduler Laravel (configure a l'etape 14) lancera les backups automatiquement
selon la configuration de `spatie/laravel-backup`.

### Backup supplementaire via Hestia

Hestia a aussi son propre systeme de backup :

```bash
# Backup complet du compte admin (web + BDD + mail)
v-backup-user admin

# Lister les backups
v-list-user-backups admin
```

---

## Script de deploiement rapide (pour les mises a jour)

Creer un script `deploy.sh` a la racine du projet :

```bash
#!/bin/bash
# deploy.sh - Script de mise a jour CHAPCHAP
# Usage : ./deploy.sh [--migrate]

set -e

PROJECT_DIR="/home/admin/web/chapchap.tondomaine.ci/project"
PHP="/usr/bin/php8.3"

echo "=== CHAPCHAP - Deploiement ==="
echo "$(date)"

cd $PROJECT_DIR

# Passer en mode maintenance
$PHP artisan down --retry=60

# Recuperer les derniers changements
git pull origin main

# Installer les dependances
composer install --no-dev --optimize-autoloader --no-interaction

# Compiler les assets
npm ci
npm run build

# Migrations (si demande)
if [ "$1" = "--migrate" ]; then
    echo ">>> Execution des migrations..."
    $PHP artisan migrate --force
fi

# Vider et regenerer les caches
$PHP artisan config:cache
$PHP artisan route:cache
$PHP artisan view:cache
$PHP artisan event:cache
$PHP artisan icons:cache
$PHP artisan filament:upgrade

# Permissions
chmod -R 775 storage bootstrap/cache

# Sortir du mode maintenance
$PHP artisan up

echo "=== Deploiement termine avec succes ==="
echo "$(date)"
```

```bash
# Rendre executable
chmod +x /home/admin/web/chapchap.tondomaine.ci/project/deploy.sh

# Utilisation :
./deploy.sh              # Mise a jour sans migration
./deploy.sh --migrate    # Mise a jour avec migrations
```

---

## Commandes utiles au quotidien

### Monitoring

```bash
# Logs Laravel en temps reel
tail -f /home/admin/web/chapchap.tondomaine.ci/project/storage/logs/laravel.log

# Logs Nginx
tail -f /var/log/nginx/domains/chapchap.tondomaine.ci.error.log

# Logs Apache
tail -f /var/log/apache2/domains/chapchap.tondomaine.ci.error.log

# Etat des services
sudo systemctl status php8.3-fpm nginx apache2 mariadb

# Utilisation RAM
free -h

# Espace disque
df -h
```

### Maintenance

```bash
cd /home/admin/web/chapchap.tondomaine.ci/project

# Vider tous les caches
php8.3 artisan cache:clear
php8.3 artisan config:clear
php8.3 artisan route:clear
php8.3 artisan view:clear

# Mode maintenance (affiche une page "en cours de maintenance")
php8.3 artisan down
php8.3 artisan up

# Ouvrir Tinker pour debugger
php8.3 artisan tinker
```

### En cas de probleme

```bash
# 1. Verifier les logs
tail -50 /home/admin/web/chapchap.tondomaine.ci/project/storage/logs/laravel.log

# 2. Verifier les permissions
ls -la storage/
ls -la bootstrap/cache/

# 3. Vider les caches
php8.3 artisan config:clear && php8.3 artisan cache:clear

# 4. Verifier la connexion BDD
php8.3 artisan tinker --execute="DB::connection()->getPdo(); echo 'OK';"

# 5. Redemarrer les services
sudo systemctl restart php8.3-fpm apache2 nginx

# 6. Verifier l'espace disque
df -h
```

---

## Architecture finale sur le serveur

```
VPS (Ubuntu 24.04 + Hestia CP)
│
├── Nginx (:443) ─── Reverse proxy + SSL + assets statiques
│     │
│     └── Apache (:8443) ─── PHP-FPM 8.3
│           │
│           └── /home/admin/web/chapchap.tondomaine.ci/
│                 ├── project/              ← Code Laravel
│                 │   ├── app/
│                 │   ├── public/           ← Racine web
│                 │   ├── storage/
│                 │   └── ...
│                 ├── public_html → project/public  ← Lien symbolique
│                 └── logs/
│
├── MariaDB (:3306) ─── Base admin_chapchap
│
├── fail2ban ─── Protection brute-force
│
└── Hestia CP (:8083) ─── Interface d'administration
```

---

## Securite en production

### Checklist obligatoire

- [ ] `APP_DEBUG=false` dans `.env`
- [ ] `APP_ENV=production` dans `.env`
- [ ] `SESSION_ENCRYPT=true` dans `.env`
- [ ] SSL actif (Let's Encrypt)
- [ ] fail2ban actif
- [ ] Firewall iptables actif
- [ ] Mot de passe BDD fort (pas "root" ou "password")
- [ ] Acces SSH par cle uniquement (desactiver mot de passe SSH)
- [ ] Panel Hestia sur port non-standard ou restreint par IP
- [ ] Backups automatiques configures et testes
