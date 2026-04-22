# CHAPCHAP — Handoff Session du 2026-03-29

> Lis ce document au début de ta prochaine session pour reprendre exactement là où on s'est arrêtés.

---

## 1. Environnement de travail

| Élément | Valeur |
|---------|--------|
| **Projet** | `/Applications/MAMP/htdocs/chapchap/` |
| **Serveur dev** | `php artisan serve` → http://localhost:8000/admin/login |
| **Connexion admin** | `patrick.tetchi@cpf-afrique.com` / `password` |
| **MySQL** | MAMP Pro, socket `/Applications/MAMP/tmp/mysql/mysql.sock` |
| **DB locale** | `chapchap` (données de prod importées ce jour) |
| **phpMyAdmin** | http://localhost:8888/phpMyAdmin6/ (root/root) |

Pour lancer le serveur :
```bash
cd /Applications/MAMP/htdocs/chapchap && php artisan serve
```

---

## 2. Ce qui a été fait dans cette session

### 2.1 Migration XAMPP → MAMP Pro ✅
- Projet copié de `/Applications/XAMPP/xamppfiles/htdocs/chapchap/` → `/Applications/MAMP/htdocs/chapchap/`
- BDD exportée depuis XAMPP (MariaDB 10.4) et importée dans MySQL 8.0 MAMP
- `.env` mis à jour avec `DB_SOCKET` (MAMP Pro tourne en `skip_networking=ON`, TCP désactivé)
- XAMPP conservé désactivé comme backup

### 2.2 Amélioration du module Production ✅ (codée, non encore testée ni déployée)

**Contexte** : Dans le formulaire de production, section "Produits obtenus", l'ancien champ unique "Quantité (kg ou unités)" était ambigu. On l'a remplacé par deux champs distincts.

**Fichiers modifiés :**

#### `database/migrations/2026_03_29_211328_add_quantite_unite_to_production_lignes.php`
Nouvelle migration qui ajoute la colonne `quantite_unite` (integer, nullable) à la table `production_lignes`.
- ✅ Migration appliquée en local

#### `app/Models/ProductionLigne.php`
- Ajout de `quantite_unite` dans `$fillable`
- Ajout du cast `'quantite_unite' => 'integer'`

#### `app/Filament/Resources/ProductionResource.php`
Deux changements :

**Dans `form()` — Repeater "Produits obtenus"** :
- Repeater passé de 2 à 3 colonnes
- Champ `quantite` renommé "Quantité (kg)" avec suffix `kg`, step `0.001`
- Nouveau champ `quantite_unite` "Quantité (unités)", entier, nullable

**Dans `infolist()` — Vue détail** :
- RepeatableEntry passé de 4 à 5 colonnes
- Nouveau champ `quantite_unite` "Qté (unités)" ajouté avant "Qté (kg)"

### 2.3 Restauration de la DB de production en local ✅
- Fichier : `/Applications/XAMPP/xamppfiles/htdocs/chapchap/Tetchi_chapchap.sql` (export phpMyAdmin)
- BDD locale droppée et recréée proprement, puis dump importé
- Migration `quantite_unite` appliquée sur les données de prod
- Mot de passe de `patrick.tetchi@cpf-afrique.com` réinitialisé à `password`
- 7 utilisateurs présents (tous les vrais comptes)

---

## 3. Ce qui reste à faire

### 3.1 Tester localement (PRIORITAIRE)
1. Lancer `php artisan serve`
2. Se connecter sur http://localhost:8000/admin/login
3. Aller dans **Production → Nouvelle production**
4. Vérifier que la section "Produits obtenus" affiche bien **3 colonnes** :
   - Produit | Quantité (unités) | Quantité (kg)
5. Créer une production test avec quelques lignes et sauvegarder
6. Vérifier la **vue détail** (infolist) qui doit aussi afficher les 2 colonnes

### 3.2 Déployer en production (après validation locale)

**Serveur prod :**
- VPS : `vps114082.serveur-vps.net` / `192.162.69.224`
- User SSH : `root`
- Dossier projet : à confirmer (probablement `/var/www/chapchap/` ou similaire)
- URL prod : https://app.chapchap-ci.com

**Étapes de déploiement :**
```bash
# 1. Se connecter au VPS
ssh root@vps114082.serveur-vps.net

# 2. Aller dans le dossier projet
cd /var/www/chapchap   # (ou le bon chemin)

# 3. Pousser les fichiers modifiés (git pull ou rsync)
git pull origin main

# 4. Appliquer la migration
php artisan migrate --force

# 5. Vider les caches
php artisan config:clear && php artisan cache:clear && php artisan view:clear

# 6. Redémarrer PHP-FPM si nécessaire
systemctl restart php8.x-fpm
```

> ⚠️ Un backup complet (DB + fichiers) a été fait avant cette session. Les fichiers backup sont sur le VPS dans `/root/backups/`.

---

## 4. Autres améliorations à prévoir (backlog)

Ces améliorations n'ont pas encore été discutées en détail mais sont dans la liste :

- **Module Production** : Calcul automatique du rendement à la validation
- **Module Ventes** : Amélioration de la saisie rapide (Livewire)
- **Dashboard** : KPI temps réel (CA jour, marge, stock)
- **Module Caisse** : Clôture journalière + rapprochement
- Voir le planning complet dans `CLAUDE.md` (phases S5 à S12)

---

## 5. Points d'attention techniques

- **MySQL MAMP** fonctionne en socket uniquement (`skip_networking=ON`). Toujours utiliser `DB_SOCKET` dans `.env`.
- **Ne jamais modifier manuellement** `/Library/Application Support/appsolute/MAMP PRO/conf/httpd.conf` — MAMP Pro l'écrase à chaque redémarrage.
- La **DB locale contient les vraies données de prod** depuis ce jour. Faire attention lors des tests destructifs.
- Le fichier `Tetchi_chapchap.sql` à la racine du projet XAMPP est le dump de prod — le garder comme référence.

---

## 6. Fichiers clés à connaître

| Fichier | Rôle |
|---------|------|
| `app/Filament/Resources/ProductionResource.php` | Interface Filament du module Production (modifié) |
| `app/Models/ProductionLigne.php` | Modèle des lignes de production (modifié) |
| `database/migrations/2026_03_29_211328_add_quantite_unite_to_production_lignes.php` | Migration nouvelle colonne (créée) |
| `app/Services/StockService.php` | Logique de mise à jour du stock |
| `app/Observers/ProductionObserver.php` | Déclenche les mouvements stock à la validation |
| `.env` | Config DB (socket MAMP) |
| `CLAUDE.md` | Cahier des charges complet + conventions du projet |

---

*Généré le 2026-03-29 — Session CHAPCHAP*
