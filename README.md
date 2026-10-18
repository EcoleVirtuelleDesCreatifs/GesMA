# GesMA — Application web de gestion Mobilier Addict

Application Laravel 12 de gestion des commandes, ventes, produits, stocks, clients, livraisons, devis/factures, dépenses et rapports, pour plusieurs magasins, utilisable sur ordinateur et téléphone, **avec mode hors connexion**.

| Section du cahier des charges | Où c'est dans l'application |
|---|---|
| 3. Produits et stocks | Produits, Stocks, Mouvements, Transferts (alerte stock faible, stock par magasin) |
| 4. Commandes et ventes | Commandes & ventes (vente directe, à livrer, à retirer ; statuts ; recherche ; filtres jour/semaine/mois/période/magasin) |
| 5. Clients | Clients (fiche, historique commandes/devis/factures/livraisons, meilleurs clients) |
| 6. Livraisons | Livraisons (préparation → départ → livrée / échec / retour, motif, livreur, frais) |
| 7. Devis, factures, PDF | Devis & factures (numérotation DEV-/FAC-, PDF aux couleurs Mobilier Addict, devis → facture) |
| 8. Dépenses | Dépenses (par période, catégorie, magasin) |
| 9. Tableau de bord | Tableau de bord (CA, commandes, dépenses, résultat, top produits/clients, stocks faibles, par ville/magasin) |
| 10. Plusieurs magasins | Magasins (Yamoussoukro, Bouaké, Béoumi, Bouaflé + ajout), données séparées, vue consolidée Direction |
| 11. Hors connexion | Saisie hors connexion + indicateur de synchronisation dans la barre du haut |
| 12. Site web | API `/api/v1` (commandes, produits, stocks, anti-doublon) |
| 13. Utilisateurs et droits | Utilisateurs, profils, Historique des actions sensibles |
| 14. Sauvegardes | Commande `gesma:backup` planifiée chaque nuit |

---

## 1. Installation (poste de développement)

Prérequis : PHP 8.2+ (extensions `pdo_sqlite` ou `pdo_mysql`, `mbstring`, `gd`, `dom`, `zip`), Composer 2.

```bash
cd gesma
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed            # magasins, comptes, catalogue de départ
php artisan db:seed --class=DemoSeeder   # facultatif : clients, commandes et dépenses de démonstration
php artisan serve
```

Ouvrir http://localhost:8000.

### Comptes créés

Mot de passe initial de tous les comptes : **`GesMA2026!`** (à changer dès la première connexion, menu *Mot de passe*).

| Profil | E-mail |
|---|---|
| Direction | direction@mobilieraddict.ci |
| Responsable de magasin | responsable.yam@mobilieraddict.ci (aussi `.bke`, `.beo`, `.bfl`) |
| Équipe de vente | vente.yam@mobilieraddict.ci (…) |
| Équipe logistique | logistique.yam@mobilieraddict.ci (…) |

Codes magasins : `YAM` Yamoussoukro, `BKE` Bouaké, `BEO` Béoumi, `BFL` Bouaflé.

### Tests automatiques

```bash
php artisan test
```

Les tests couvrent : ventes et mouvements de stock, annulations/retours, livraisons, transferts, droits par profil, séparation des magasins, synchronisation hors connexion (doublons, conflits), API du site web, statistiques du tableau de bord, devis/factures et PDF.

---

## 2. Mise en service sur le serveur dédié

Exemple pour Ubuntu 22.04/24.04 avec Nginx, PHP-FPM 8.3 et MySQL/MariaDB.

```bash
# Paquets
sudo apt install nginx mysql-server php8.3-fpm php8.3-mysql php8.3-mbstring php8.3-xml php8.3-gd php8.3-zip php8.3-curl unzip
# Base de données
sudo mysql -e "CREATE DATABASE gesma CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'gesma'@'localhost' IDENTIFIED BY 'MOT_DE_PASSE_FORT';
GRANT ALL ON gesma.* TO 'gesma'@'localhost';"

# Application
cd /var/www && sudo git clone <dépôt> gesma   # ou copier le dossier
cd gesma
composer install --no-dev --optimize-autoloader
cp .env.example .env && php artisan key:generate
```

Dans `.env` :

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://gesma.mobilieraddict.ci
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=gesma
DB_USERNAME=gesma
DB_PASSWORD=MOT_DE_PASSE_FORT
SESSION_SECURE_COOKIE=true
GESMA_WEBSITE_API_KEY=<longue clé aléatoire>
```

```bash
php artisan migrate --seed --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
sudo chown -R www-data:www-data storage bootstrap/cache
```

Configuration Nginx (extrait) :

```nginx
server {
    server_name gesma.mobilieraddict.ci;
    root /var/www/gesma/public;
    index index.php;
    location / { try_files $uri $uri/ /index.php?$query_string; }
    location = /sw.js { add_header Cache-Control "no-cache"; add_header Service-Worker-Allowed "/"; }
    location ~ \.php$ { include snippets/fastcgi-php.conf; fastcgi_pass unix:/run/php/php8.3-fpm.sock; }
}
```

**HTTPS obligatoire** (`sudo certbot --nginx`) : le mode hors connexion et le chiffrement des données sur les appareils ne fonctionnent que sur une adresse sécurisée.

Tâches planifiées (sauvegardes) — ajouter au crontab de `www-data` :

```
* * * * * cd /var/www/gesma && php artisan schedule:run >> /dev/null 2>&1
```

---

## 3. Sauvegardes et restauration (section 14)

- `php artisan gesma:backup` crée une copie compressée de la base dans `storage/app/backups/` ; elle est lancée **chaque nuit à 2 h** et les copies de plus de 14 jours sont supprimées (`GESMA_BACKUP_KEEP_DAYS`).
- Copier régulièrement ce dossier hors du serveur (ex. `rclone` vers un stockage externe) pour se protéger d'une panne du serveur.

Restauration MySQL :

```bash
php artisan down
gunzip -c storage/app/backups/gesma_AAAA-MM-JJ_HHMMSS.sql.gz | mysql -u gesma -p gesma
php artisan up
```

Restauration SQLite : `gunzip -c …sqlite.gz > database/database.sqlite`.

Surveillance : la route `/up` répond 200 si l'application fonctionne ; la brancher sur un service de surveillance (UptimeRobot, etc.).

---

## 4. Mode hors connexion (section 11)

- Au premier passage en ligne, chaque appareil enregistre le catalogue, les stocks, les clients et les livraisons en cours. Ces données sont **chiffrées** (AES-256) avec une clé créée sur l'appareil et non exportable, et effacées à la déconnexion.
- Sans Internet, toute page ouvre **Saisie hors connexion** : vente directe, commande à livrer ou à retirer, dépense, suivi des livraisons, consultation des stocks et clients.
- La barre du haut indique : connecté / hors connexion, nombre d'opérations **en attente** (orange) et **refusées** (rouge), date de la dernière synchronisation.
- Au retour de la connexion, la synchronisation est **automatique** (et toutes les minutes). Chaque opération porte un identifiant unique : renvoyée deux fois, elle n'est enregistrée qu'une fois.
- Si le stock a changé pendant la coupure, la vente est enregistrée (elle a eu lieu) et un **conflit à vérifier** apparaît dans *Conflits de synchro*. Idem si une livraison a été modifiée par quelqu'un d'autre entre-temps.
- *Historique de synchro* liste tout ce qui a été reçu par le serveur.

---

## 5. API pour le site web Mobilier Addict (section 12)

Toutes les requêtes portent l'en-tête `X-API-KEY: <GESMA_WEBSITE_API_KEY>`. Limite : 120 requêtes/minute.

| Méthode | URL | Rôle |
|---|---|---|
| GET | `/api/v1/products?updated_since=2026-10-01T00:00:00Z` | Produits publiés (prix, disponibilité, stock total), paginés |
| GET | `/api/v1/stocks` | Stock par produit et par magasin |
| POST | `/api/v1/orders` | Envoi d'une commande passée sur le site |
| GET | `/api/v1/orders/{reference}` | Statut d'une commande du site |

Exemple d'envoi de commande :

```json
POST /api/v1/orders
{
  "reference": "WEB-1001",
  "store_code": "YAM",
  "customer": { "name": "Awa Ouattara", "phone": "0711111111", "email": "awa@example.ci" },
  "items": [ { "product_reference": "SAL-001", "quantity": 1, "unit_price": 285000 } ],
  "delivery": { "address": "Quartier Habitat", "city": "Yamoussoukro", "fee": 10000 },
  "payment_method": "mobile_money",
  "amount_paid": 295000
}
```

- Réponse `201` à la création, `200` avec `"duplicate": true` si la même `reference` a déjà été reçue : **aucune commande en double**.
- La commande apparaît dans GesMA avec l'origine « Site web » et la référence du site.
- Si le stock est insuffisant, la commande est enregistrée et signalée dans *Conflits de synchro* (`"stock_warning": true`).

---

## 6. Droits par profil (section 13)

Définis dans `config/gesma.php` (`permissions`) et affichés dans *Utilisateurs*. Résumé par défaut :

- **Direction** : tout, tous les magasins, gestion des magasins et utilisateurs, historique des actions, modification des prix.
- **Responsable de magasin** : opérations et résultats de son magasin (stocks, corrections, transferts, annulations, dépenses, rapports).
- **Équipe de vente** : clients, commandes, ventes, devis et factures de son magasin.
- **Équipe logistique** : commandes à préparer/livrer et suivi des livraisons de son magasin.

Actions enregistrées dans l'**historique** : correction de stock, sortie de stock, transfert, modification de prix, suppression de produit, annulation et retour de commande, modification/suppression de dépense, création/modification de comptes et de magasins, résolution de conflits, connexions.

---

## 7. Guide de prise en main (utilisateurs)

**Vendre en magasin** — *Commandes & ventes → Nouvelle commande / vente* → « Vente directe » → choisir le client (ou saisir son nom) → ajouter les produits (le stock disponible s'affiche) → mode de paiement → *Enregistrer*. Le stock est déduit immédiatement.

**Commande à livrer** — même écran, « Commande à livrer », renseigner l'adresse, la date, les frais et le livreur. La commande apparaît dans *Livraisons*.

**Livreur** — *Livraisons* → *Suivre* → « Partie en livraison », puis « Livraison effectuée » ou « Échec » / « Retour » avec le motif. Le statut de la commande suit automatiquement ; un retour remet les produits en stock.

**Réception de marchandise** — *Stocks → Entrée / sortie* → Entrée, produit, quantité, motif.

**Inventaire** — même écran → Correction → quantité réellement comptée et motif (enregistré dans l'historique).

**Transfert entre magasins** — *Transferts → Nouveau transfert*. Les deux stocks sont mis à jour.

**Devis / facture** — *Devis & factures* ou depuis la fiche client ; *Transformer en facture* sur un devis accepté ; *Générer la facture* sur une commande ; bouton *PDF*.

**Dépense** — *Dépenses → Nouvelle dépense* (date, montant, catégorie, description).

**Coupure Internet** — continuer dans *Saisie hors connexion*. Le compteur orange indique ce qui reste à envoyer ; ne pas se déconnecter tant qu'il n'est pas revenu à zéro (sinon les opérations attendent la prochaine connexion du même utilisateur sur l'appareil).

**Direction** — *Tableau de bord* : choisir la période et le magasin (ou tous), *Exporter en PDF* ; *Rapports PDF* pour ventes, commandes, stocks et dépenses.

**Installer sur téléphone** — ouvrir l'adresse de GesMA dans Chrome → menu ⋮ → « Ajouter à l'écran d'accueil ».

---

## 8. Points à valider avant la mise en production (section 20)

Les choix ci-dessous sont pré-réglés et modifiables sans développement lourd :

- opérations autorisées hors connexion : ventes, commandes, dépenses, suivi de livraison (`SyncService::TYPES`) ;
- écarts de stock : la vente hors connexion est conservée et signalée (stock pouvant devenir négatif) ;
- statuts de commande et transitions autorisées : `config/gesma.php` (`order_statuses`, `order_transitions`) ;
- droits par profil : `config/gesma.php` (`permissions`) ;
- informations échangées avec le site : `app/Http/Controllers/Api/WebsiteController.php` ;
- coordonnées et couleurs de Mobilier Addict sur les PDF : `config/gesma.php` (`company`) ;
- catégories de dépenses et modes de paiement : `config/gesma.php`.

## Structure du code

```
app/
  Http/Controllers/        écrans (Produits, Stocks, Commandes, Livraisons, Documents, Dépenses, Rapports…)
  Http/Controllers/Api/    API du site web
  Services/                règles métier : StockService, OrderService, DocumentService, StatsService, SyncService
  Models/                  Store, Product, Stock, StockMovement, Order, Delivery, Document, Expense, ActivityLog, SyncConflict…
  Console/Commands/        gesma:backup
config/gesma.php           profils, droits, statuts, catégories, couleurs
public/js/                 offline-store.js (IndexedDB chiffré), app.js (synchronisation), offline-page.js
public/sw.js               service worker (fonctionnement hors connexion)
resources/views/           écrans Blade et modèles PDF
tests/Feature/             tests automatiques
```
