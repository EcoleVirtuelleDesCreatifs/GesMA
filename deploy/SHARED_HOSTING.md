# Déploiement sur hébergement mutualisé (LWS, OVH, etc.)

## Prérequis côté hébergeur

- PHP **8.2 minimum** (Laravel 12 l'exige).
- Extensions PHP : `pdo_mysql`, `mbstring`, `openssl`, `ctype`, `fileinfo`, `dom`, `xml`, `curl`, `zip`, `gd`, `tokenizer`.
- MySQL / MariaDB.
- `mod_rewrite` activé (la plupart des mutualisés l'ont).

## Architecture recommandée : docroot = racine du projet

Le domaine/sous-domaine pointe sur le dossier contenant `index.php` à la racine du projet Laravel. Les assets restent dans `public/` ; le `.htaccess` racine les sert automatiquement par réécriture.

```
~/htdocs/gesma.mobilier-addict.com/
├── index.php        <-- contrôleur frontal
├── .htaccess
├── .env
├── public/
│   ├── css/
│   ├── js/
│   ├── icons/
│   ├── logo/
│   ├── sw.js
│   └── manifest.webmanifest
├── artisan
├── app/
├── bootstrap/
├── config/
├── database/
├── resources/
├── routes/
├── storage/
└── vendor/
```

Dans `.env` :

```env
APP_URL=https://gesma.mobilier-addict.com
```

Avantages :

- Structure Laravel standard conservée.
- Pas besoin de déplacer les assets.
- Mise à jour par `git pull` simple.

## Alternative : docroot = dossier `public/`

Si l'hébergeur permet de choisir le docroot, pointez-le sur `public/` et utilisez le fichier `public/index.php` fourni. Dans ce cas `public/.htaccess` est utilisé.

## Alternative : application dans un sous-dossier `/gesma`

Si le domaine doit servir d'autres fichiers à la racine et que l'app est dans un sous-dossier `gesma/` :

```
~/htdocs/gesma.mobilier-addict.com/
├── gesma/
│   ├── index.php
│   ├── .htaccess
│   ├── .env
│   ├── public/
│   └── ...
└── .htaccess   <-- celui-ci route /gesma/... vers gesma/index.php
```

Dans `.env` :

```env
APP_URL=https://gesma.mobilier-addict.com/gesma
```

Copiez le fichier docroot :

```bash
cd ~/htdocs/gesma.mobilier-addict.com/gesma
cp deploy/lws-docroot.htaccess ../.htaccess
```

## Déploiement pas à pas

1. **Sur le serveur**, assurez-vous d'être sur la branche `main` à jour :

   ```bash
   cd ~/htdocs/gesma.mobilier-addict.com
   git fetch origin
   git checkout main
   git reset --hard origin/main
   ```

   (`.env` n'est pas suivi par Git, il est conservé.)

2. **Installer / mettre à jour les dépendances** (si `vendor/` n'est pas poussé) :

   ```bash
   composer install --no-dev --optimize-autoloader
   ```

3. **Configurer `.env`** :

   Pour un déploiement LWS sans SSH, copiez le modèle fourni :

   ```bash
   cp .env.production.lws .env
   nano .env
   ```

   Valeurs importantes :

   ```env
   APP_NAME=GesMA
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://gesma.mobilier-addict.com
   # ou https://gesma.mobilier-addict.com/gesma en sous-dossier

   DB_CONNECTION=mysql
   DB_HOST=localhost
   DB_PORT=3306
   DB_DATABASE=votre_db
   DB_USERNAME=votre_user
   DB_PASSWORD=votre_mdp

   # Sur mutualisé, fichier plutôt que base de données avant migration
   SESSION_DRIVER=file
   CACHE_STORE=file
   QUEUE_CONNECTION=sync

   GESMA_WEBSITE_API_KEY=votre_cle_api
   GESMA_WEBSITE_DEFAULT_STORE=YAM
   ```

4. **Créer la base de données** :

   Si vous avez accès SSH :

   ```bash
   php artisan migrate --force
   ```

   Sinon, utilisez la page web `installation.php` (voir section ci-dessous).

5. **Permissions** (très important sur mutualisé) :

   ```bash
   chmod -R 775 storage bootstrap/cache
   # Si l'utilisateur web diffère de l'utilisateur SSH :
   chmod -R 777 storage bootstrap/cache
   ```

6. **Vider et recacher la configuration** :

   ```bash
   php artisan config:clear
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

7. **Vérifier les routes** :

   ```bash
   curl -sI https://gesma.mobilier-addict.com/
   curl -sI https://gesma.mobilier-addict.com/login
   curl -sI https://gesma.mobilier-addict.com/css/app.css
   ```

## Installation sans SSH : `installation.php`

Un fichier `installation.php` est fourni à la racine. Il permet d'installer l'application depuis un navigateur.

1. Dans `.env`, définissez un jeton sécurisé :

   ```env
   GESMA_INSTALL_TOKEN=votre_jeton_aleatoire_min_16_caracteres
   ```

2. Envoyez les fichiers de l'application sur le serveur (via FTP ou `git pull`).

3. Ouvrez dans le navigateur :

   ```
   https://gesma.mobilier-addict.com/installation.php?token=votre_jeton_aleatoire_min_16_caracteres
   ```

   La page génère `APP_KEY`, crée les tables et les comptes par défaut.

4. **Supprimez ou videz le jeton** après installation :

   ```env
   GESMA_INSTALL_TOKEN=
   ```

## Diagnostic : `deploy/diagnostic.php`

Pour obtenir la vraie erreur quand LWS affiche sa page 500 générique :

```bash
cd ~/htdocs/gesma.mobilier-addict.com
cp deploy/diagnostic.php diagnostic.php
curl -s https://gesma.mobilier-addict.com/diagnostic.php
```

Puis supprimez le fichier en production :

```bash
rm diagnostic.php
```

## Si une 500 persiste

### 1. Vérifier la version PHP du site web

Créez un fichier temporaire :

```bash
echo '<?php echo "PHP ".phpversion();' > ~/htdocs/gesma.mobilier-addict.com/phpinfo.php
```

Puis ouvrez `https://gesma.mobilier-addict.com/phpinfo.php` dans le navigateur. Si la version est < 8.2, changez-la dans le panneau de l'hébergeur.

Supprimez ensuite le fichier :

```bash
rm ~/htdocs/gesma.mobilier-addict.com/phpinfo.php
```

### 2. Tester sans `.htaccess`

```bash
mv .htaccess .htaccess.bak
curl -sI https://gesma.mobilier-addict.com/index.php
mv .htaccess.bak .htaccess
```

Si `index.php` fonctionne sans `.htaccess`, le fichier `.htaccess` contient une directive incompatible. Remplacez-le par la version minimale :

```bash
cp deploy/htaccess-minimal .htaccess
```

### 3. Vérifier les logs

```bash
tail -60 storage/logs/laravel.log
```

Si le fichier n'est pas mis à jour, c'est généralement un problème de permissions d'écriture sur `storage/`.

### 4. Vider les caches navigateur

Sur Safari, les redirections 301 sont mises en cache de façon permanente. En cas de redirection bizarre (ex. `/public/...`) :

- Safari → Réglages → Confidentialité → Gérer les données de sites web
- Rechercher `mobilier-addict` → Supprimer
- Tester en navigation privée

## Fichiers fournis

- `index.php` (racine) : contrôleur frontal pour docroot = racine du projet.
- `public/index.php` : contrôleur frontal standard pour docroot = `public/`.
- `.htaccess` / `public/.htaccess` : version sécurisée avec réécriture des assets public/.
- `deploy/htaccess-secure` : copie de la version sécurisée.
- `deploy/htaccess-minimal` : version minimale en cas de 500 liée au `.htaccess`.
- `deploy/lws-docroot.htaccess` : à placer un niveau au-dessus si l'app est dans un sous-dossier `/gesma`.
- `deploy/diagnostic.php` : diagnostic des erreurs 500.
- `deploy/SHARED_HOSTING.md` : ce guide.
- `.env.production.lws` : modèle de `.env` pour LWS.
- `installation.php` : installation web sans SSH.

## API du site web

L'API est accessible à `https://gesma.mobilier-addict.com/api/v1/orders` (ou avec `/gesma/api/v1/orders` en sous-dossier). Le site distant doit envoyer l'en-tête :

```http
X-API-KEY: <valeur de GESMA_WEBSITE_API_KEY dans .env>
```
