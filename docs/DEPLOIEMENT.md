# Mise en service de GesMA sur le serveur dédié

Configuration de référence : Ubuntu 22.04/24.04, Nginx, PHP 8.3-FPM, MySQL 8 (ou MariaDB 10.6+), HTTPS (Let's Encrypt).

> L'application a été testée avec SQLite. MySQL/MariaDB est recommandé en production ;
> exécutez `php artisan test` puis un parcours complet sur le serveur avant l'ouverture aux utilisateurs.

> **Hébergement mutualisé LWS ?** Voir la section [10. Hébergement mutualisé (LWS)](#10-hébergement-mutualisé-lws--erreur-500).

## 1. Paquets

```bash
sudo apt update
sudo apt install -y nginx mysql-server certbot python3-certbot-nginx unzip git \
  php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-gd php8.3-intl php8.3-bcmath
curl -sS https://getcomposer.org/installer | php && sudo mv composer.phar /usr/local/bin/composer
```

## 2. Base de données

```sql
CREATE DATABASE gesma CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'gesma'@'localhost' IDENTIFIED BY 'mot-de-passe-solide';
GRANT ALL PRIVILEGES ON gesma.* TO 'gesma'@'localhost';
```

## 3. Application

```bash
sudo mkdir -p /var/www/gesma && sudo chown $USER:www-data /var/www/gesma
git clone <dépôt> /var/www/gesma && cd /var/www/gesma
composer install --no-dev --optimize-autoloader
cp .env.example .env && php artisan key:generate
```

Dans `.env` :

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://gesma.mobilieraddict.ci
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=gesma
DB_USERNAME=gesma
DB_PASSWORD=mot-de-passe-solide
SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=database
GESMA_ADMIN_EMAIL=direction@mobilieraddict.ci
GESMA_ADMIN_PASSWORD=un-mot-de-passe-provisoire
```

Puis :

```bash
php artisan migrate --force
php artisan db:seed --class=ReferenceDataSeeder --force   # magasins, catégories, compte direction
php artisan storage:link
php artisan config:cache && php artisan route:cache && php artisan view:cache
sudo chown -R www-data:www-data storage bootstrap/cache
```

## 4. Nginx et HTTPS

Copier `deploy/nginx.conf` dans `/etc/nginx/sites-available/gesma`, adapter le nom de domaine, puis :

```bash
sudo ln -s /etc/nginx/sites-available/gesma /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
sudo certbot --nginx -d gesma.mobilieraddict.ci
```

HTTPS est **obligatoire** pour le mode hors connexion (service worker et chiffrement local).

## 5. Tâches planifiées et file d'attente

```bash
# crontab -e -u www-data
* * * * * cd /var/www/gesma && php artisan schedule:run >> /dev/null 2>&1
```

Le planificateur lance la sauvegarde quotidienne (02 h 30) et l'expiration des devis.
Pour les notifications vers le site web, installer `deploy/gesma-worker.service` (systemd) :

```bash
sudo cp deploy/gesma-worker.service /etc/systemd/system/
sudo systemctl enable --now gesma-worker
```

## 6. Sauvegardes

- `php artisan gesma:backup` crée `storage/app/backups/gesma-db-AAAA-MM-JJ_HHMMSS.sql.gz`
  et une archive des photos produits ; les sauvegardes de plus de 30 jours sont supprimées.
- **Copier les sauvegardes hors du serveur** chaque jour (exemple avec rclone vers un stockage externe) :

```bash
30 3 * * * rclone copy /var/www/gesma/storage/app/backups distant:gesma-backups --max-age 48h
```

### Restauration

```bash
cd /var/www/gesma
php artisan down
gunzip -c storage/app/backups/gesma-db-AAAA-MM-JJ_HHMMSS.sql.gz | mysql -u gesma -p gesma
tar -xzf storage/app/backups/gesma-files-AAAA-MM-JJ_HHMMSS.tar.gz -C storage/app/public
php artisan up
```

Tester la restauration au moins une fois par trimestre sur une base de test.

## 7. Surveillance

- Point de contrôle : `https://gesma.mobilieraddict.ci/up` (répond 200 si l'application fonctionne) —
  à brancher sur un service de surveillance (UptimeRobot, etc.) avec alerte SMS/e-mail.
- Journaux : `storage/logs/laravel.log`, `/var/log/nginx/error.log`.
- Espace disque et sauvegardes : vérifier chaque semaine la présence des fichiers du jour.

## 8. Mise à jour de l'application

```bash
cd /var/www/gesma && php artisan down
git pull && composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan queue:restart && php artisan up
```

## 9. Sécurité

- Comptes personnels, mots de passe de 8 caractères minimum (lettres et chiffres), connexion limitée à
  5 tentatives par minute, comptes désactivables par la direction.
- Données hors connexion chiffrées sur l'appareil (AES-GCM, clé non exportable) et effacées à la déconnexion.
- Jetons de l'API du site stockés sous forme d'empreinte (SHA-256), désactivables à tout moment.
- Historique des actions sensibles : `Administration → Historique sensible`.

## 10. Hébergement mutualisé (LWS) — erreur 500

La page orange « ERROR 500 - INTERNAL SERVER ERROR » de LWS s'affiche dès que PHP ou Laravel échoue
au démarrage. Causes rencontrées, dans l'ordre où les vérifier :

| Cause | Correction |
|-------|-----------|
| Version de PHP inférieure à 8.3 (Laravel 13 refuse de démarrer) | Panneau LWS → *Configuration PHP* → choisir **PHP 8.3** pour le domaine |
| Dossier `vendor/` absent (il n'est pas dans Git) | `composer install --no-dev --optimize-autoloader` en SSH, ou envoyer l'archive `gesma-deploy.zip` (onglet *Actions* de GitHub → *Archive de déploiement*) |
| Fichier `.env` absent ou `APP_KEY` vide | Copier `.env.production.lws` en `.env`, le compléter, puis ouvrir `installation.php?token=...` |
| Base SQLite par défaut / sessions en base avant les migrations | Utiliser MySQL et `SESSION_DRIVER=file`, `CACHE_STORE=file` (déjà dans `.env.production.lws`) |
| Migrations non exécutées | Ouvrir `installation.php?token=...` (voir ci-dessous) |
| `storage/` ou `bootstrap/cache/` non inscriptibles | Droits 775 (ou 755) via FTP sur ces dossiers et leurs sous-dossiers |
| `bootstrap/cache/config.php` envoyé depuis un autre ordinateur | Supprimer les fichiers `bootstrap/cache/*.php` (garder `.gitignore`) |

### Installation (sans SSH)

1. Dans le panneau LWS : choisir **PHP 8.3**, créer la base **MySQL**, activer le **certificat SSL**.
2. Télécharger `gesma-deploy.zip` (GitHub → *Actions* → *Archive de déploiement* → *Artifacts*) : il
   contient déjà `vendor/`. L'envoyer par FTP ou le gestionnaire de fichiers LWS et le décompresser
   dans le dossier du domaine (`htdocs`/`www`).
3. **Racine du site** : si le panneau permet de la faire pointer sur `public/`, le faire. Sinon, rien
   à changer : le `.htaccess` placé à la racine renvoie tout vers `public/` et bloque l'accès à `.env`,
   `vendor/`, `storage/`…
4. Copier `.env.production.lws` en `.env` et le compléter : `APP_URL` (avec `https://`), `DB_DATABASE`,
   `DB_USERNAME`, `DB_PASSWORD` (indiqués par LWS ; `DB_HOST` est parfois différent de `localhost`),
   et un `GESMA_INSTALL_TOKEN` d'au moins 16 caractères.
5. Ouvrir `https://votre-domaine/installation.php?token=<GESMA_INSTALL_TOKEN>`. La page génère
   `APP_KEY` ; la **recharger** une fois pour créer les tables, les magasins, les catégories et le
   compte direction (le mot de passe provisoire s'affiche si `GESMA_ADMIN_PASSWORD` est vide).
6. **Vider `GESMA_INSTALL_TOKEN`** dans `.env` : la page d'installation répond alors « Introuvable ».
7. Tâche planifiée (panneau LWS → *Tâches Cron*), toutes les minutes :
   `cd /chemin/du/projet && php artisan schedule:run`.

Spécificités du mutualisé, déjà prises en charge :

- **Photos des produits** : si les liens symboliques sont interdits (`storage:link` échoue),
  l'application sert elle-même les fichiers de `storage/app/public` sous `/storage/...`.
- **Sauvegardes** : sans `mysqldump` ni `tar`, `gesma:backup` exporte la base en PHP (`.sql.gz`) et
  les photos en `.zip`. Télécharger régulièrement `storage/app/backups` par FTP.
- **File d'attente** : `QUEUE_CONNECTION=sync`, aucun worker permanent n'est nécessaire.
- **HTTPS** : forcé seulement si `APP_URL` commence par `https://`.

**Mise à jour** : envoyer la nouvelle archive (sans écraser `.env` ni `storage/`), remettre un
`GESMA_INSTALL_TOKEN`, rouvrir `installation.php?token=...` (migrations et vidage des caches), puis
vider le jeton.

### Trouver la cause exacte

Copier `deploy/diagnostic.php` dans `public/` sous un nom difficile à deviner (ex. `public/diag-7f3k.php`),
l'ouvrir dans le navigateur : il vérifie PHP, les extensions, `vendor/`, `.env`, les droits, la
connexion MySQL et affiche la dernière erreur de `storage/logs`. **Supprimer le fichier ensuite.**
