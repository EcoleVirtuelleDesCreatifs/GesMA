# Mise en service de GesMA sur le serveur dédié

Configuration de référence : Ubuntu 22.04/24.04, Nginx, PHP 8.3-FPM, MySQL 8 (ou MariaDB 10.6+), HTTPS (Let's Encrypt).

> L'application a été testée avec SQLite. MySQL/MariaDB est recommandé en production ;
> exécutez `php artisan test` puis un parcours complet sur le serveur avant l'ouverture aux utilisateurs.

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
