# GesMA — Gestion Mobilier Addict

Application web de gestion de **Mobilier Addict** : commandes, ventes, produits, stocks par magasin,
clients, livraisons, devis, factures, dépenses, tableau de bord et rapports — utilisable sur ordinateur
et téléphone, y compris **sans connexion Internet** pour les opérations essentielles.

Construite avec **Laravel 13 (PHP 8.3)**, Blade, DomPDF et une application hors connexion (PWA :
service worker + IndexedDB chiffrée). Aucune étape de compilation JavaScript n'est nécessaire.

## Modules (correspondance avec le cahier des charges)

| § | Module | Où le trouver |
|---|--------|---------------|
| 3 | Produits et stocks : fiches, prix, stock par magasin, entrées/sorties, ventes, retours, corrections, transferts, alertes | `Produits`, `Stocks`, `Transferts` |
| 4 | Commandes et ventes : création, commandes du site web, statuts (en préparation, expédiée, livrée, non livrée, annulée, retournée), recherche, ventes par jour/semaine/mois/période/ville/magasin, annulations et retours séparés | `Commandes`, `Ventes` |
| 5 | Clients : fiche, historique (commandes, devis, factures, livraisons), recherche rapide, meilleurs clients | `Clients` |
| 6 | Livraisons : préparation, départ, livrée, échec, retour, adresse, contact, date, livreur, frais, motif | `Livraisons` |
| 7 | Devis et factures numérotés, datés, aux couleurs de Mobilier Addict, PDF ; rapports PDF | `Devis`, `Factures`, `Rapports` |
| 8 | Dépenses par date, montant, catégorie, magasin ; totaux et comparaison avec les ventes | `Dépenses` |
| 9 | Tableau de bord : CA, commandes, statuts, dépenses, produits les plus vendus, stock faible, meilleurs clients, résultats par ville/magasin/global, filtres de période, exports | `Tableau de bord` |
| 10 | Plusieurs magasins : création, données séparées, vue consolidée | `Administration → Magasins` |
| 11 | Hors connexion : données locales chiffrées, commandes/ventes/dépenses/livraisons hors ligne, synchronisation automatique sans doublon, conflits signalés, indicateur d'état | `Mode hors connexion`, `Synchronisation` |
| 12 | API site web : commandes sans doublon (référence d'origine), produits, stocks | `docs/API.md`, `Administration → API site web` |
| 13 | Comptes personnels, profils (Direction, Responsable, Vente, Logistique), historique des actions sensibles | `Administration → Utilisateurs`, `Historique sensible` |
| 14 | Sécurité, sauvegardes quotidiennes, restauration | `docs/DEPLOIEMENT.md` |
| 15 | Guide de prise en main | `docs/GUIDE.md` |

## Démarrage rapide (développement)

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed        # données de référence + démonstration
php artisan storage:link
php artisan serve
```

Comptes de démonstration (mot de passe `password`) :

| Profil | Identifiant |
|--------|-------------|
| Direction | `direction@gesma.test` |
| Responsable de magasin | `responsable.yam@gesma.test`, `responsable.bke@gesma.test`, … |
| Équipe de vente | `vente.yam@gesma.test`, … |
| Équipe logistique | `logistique.yam@gesma.test`, … |

Magasins : YAM (Yamoussoukro), BKE (Bouaké), BEO (Béoumi), BFL (Bouaflé).

> Le mode hors connexion utilise un service worker et le chiffrement du navigateur : il fonctionne
> sur `http://localhost` et, en production, uniquement en **HTTPS**.

## Tests

```bash
php artisan test
```

Les tests couvrent la réservation et la remise en stock, les droits par profil, le cloisonnement des
magasins, les transferts, les corrections d'inventaire, la synchronisation hors connexion (doublons,
conflits, résolution), l'API du site web et la génération des PDF.

## Règles de gestion retenues (à valider — § 20 du cahier des charges)

Ces règles sont centralisées et faciles à ajuster :

- **Stock** : réservé dès la création d'une commande ; réintégré lors d'une annulation ou d'un retour
  (`app/Services/OrderService.php`). Une commande est refusée si le stock du magasin est insuffisant.
- **Chiffre d'affaires** : commandes et ventes non annulées et non retournées, à leur date de commande.
  Annulations et retours sont comptés à part (`app/Services/StatsService.php`).
- **Statuts et transitions** : `app/Enums/OrderStatus.php` et `app/Enums/DeliveryStatus.php`.
  Annulation, retour et non-livraison exigent un motif.
- **Droits des profils** : `app/Enums/Role.php` (liste des permissions par profil).
- **Hors connexion** : opérations autorisées = création de commande, vente directe, dépense, suivi de
  livraison. Une commande dont le stock a changé pendant la coupure est mise en **conflit** ; le
  responsable choisit de l'appliquer (stock à régulariser) ou de la rejeter (`app/Services/SyncService.php`).
- **Site web** : les commandes du site sont acceptées même en rupture (signalées dans l'historique),
  réglable avec `GESMA_WEBSITE_ACCEPT_WITHOUT_STOCK` (`config/gesma.php`).
- **Prix** : seuls la direction et les responsables peuvent modifier un prix ; chaque modification est historisée.

## Documentation

- [Guide de prise en main des utilisateurs](docs/GUIDE.md)
- [Installation sur le serveur, sauvegardes et restauration](docs/DEPLOIEMENT.md)
- [API pour le site web Mobilier Addict](docs/API.md)
