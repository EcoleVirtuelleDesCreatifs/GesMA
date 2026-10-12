# Guide de prise en main — GesMA

## 1. Se connecter

Ouvrez l'adresse de GesMA dans le navigateur (ordinateur ou téléphone) et saisissez votre e-mail et votre
mot de passe. Chaque membre de l'équipe a **son propre compte** : ne partagez pas vos identifiants.
Changez votre mot de passe dans **Mon profil** (cliquez sur votre nom en bas du menu).

Sur téléphone, le menu s'ouvre avec le bouton ☰ en haut à gauche. Vous pouvez **installer GesMA** comme une
application : menu du navigateur → « Ajouter à l'écran d'accueil ».

Ce que vous voyez dépend de votre profil :

| Profil | Accès |
|--------|-------|
| Direction | Tous les magasins, résultats globaux, utilisateurs, magasins, historique |
| Responsable de magasin | Toutes les opérations et résultats de son magasin |
| Équipe de vente | Clients, commandes, ventes, devis, factures |
| Équipe logistique | Commandes à préparer et livraisons |

## 2. Enregistrer une commande ou une vente

- **Commandes → Nouvelle commande** : recherchez le client (nom ou téléphone) ou saisissez un nouveau client,
  ajoutez les produits (le stock disponible du magasin s'affiche sous chaque produit), la remise, l'acompte,
  puis cochez **Livraison à domicile** si besoin (adresse, date, livreur, frais).
- **Vente directe** : vente au comptoir avec retrait immédiat ; le client est facultatif.
- Si le stock est insuffisant, GesMA refuse la commande et indique les quantités disponibles.

Sur la fiche d'une commande : changer le statut (en préparation → expédiée → livrée / non livrée → retournée),
encaisser un paiement, programmer une livraison, créer la facture, imprimer.
Une **annulation** ou un **retour** demande un motif et remet automatiquement les produits en stock.

La barre de recherche en haut retrouve une commande par numéro, référence du site, nom ou téléphone du client.

## 3. Livraisons

**Livraisons** affiche les commandes à traiter, celles prévues aujourd'hui et « Mes livraisons » (livreur).
Sur une livraison : modifier l'adresse, la date, le livreur et les frais ; puis **Départ (en route)**,
**Livrée**, ou **Signaler un échec ou un retour** avec le motif. Le statut de la commande suit automatiquement.

## 4. Produits et stocks

- **Produits** : fiche (nom, référence, catégorie, description, prix, disponibilité, seuil d'alerte, photo).
  Toute modification de prix est enregistrée dans l'historique.
- **Stocks** : quantités par magasin. Les chiffres en orange sont sous le seuil, en rouge en rupture.
- **Entrée / sortie** : réception de marchandises, casse, usage interne (avec motif).
- **Inventaire** : saisissez les quantités comptées ; seuls les écarts sont corrigés et historisés.
- **Transferts** : envoi de produits d'un magasin à un autre ; les deux stocks sont mis à jour.
- **Alertes** : liste des produits au seuil d'alerte ou en rupture.

## 5. Clients, devis et factures

- **Clients** : recherche par nom, téléphone, e-mail ou ville ; la fiche regroupe commandes, devis, factures
  et livraisons. **Meilleurs clients** classe les clients par montant et par fréquence d'achat.
- **Devis** : créez un devis (produits du catalogue ou lignes libres), téléchargez le PDF, puis
  **Transformer en commande** ou **Générer la facture** quand le client accepte.
- **Factures** : depuis une commande (bouton « Créer la facture »), un devis, ou directement. Enregistrez les
  paiements ; le PDF indique le reste à payer ou « Facture acquittée ».

## 6. Dépenses

**Dépenses → Nouvelle dépense** : date, montant, catégorie (transport, livraisons, loyer…), description, magasin.
La page affiche le total de la période, la répartition par catégorie et la comparaison avec les ventes.

## 7. Tableau de bord, ventes et rapports

- **Tableau de bord** : chiffre d'affaires, commandes du jour, livrées / non livrées / retournées / annulées,
  dépenses, résultat, produits les plus vendus, stock faible, meilleurs clients, résultats par magasin et
  par ville. Filtrez par période, ville ou magasin.
- **Ventes** : ventes du jour, de la semaine, du mois ou d'une période personnalisée ; les annulations et
  retours sont présentés à part et ne sont pas comptés dans le chiffre d'affaires.
- **Rapports** : ventes, commandes, stocks et dépenses en **PDF** ou **Excel (CSV)**.

## 8. Travailler sans connexion Internet

L'indicateur en haut à droite montre l'état : **En ligne** (avec l'heure de la dernière synchronisation),
**Hors connexion**, le nombre d'opérations **en attente** et celles **à vérifier**.

1. Connectez-vous au moins une fois avec Internet sur l'appareil : les produits, stocks, clients et livraisons
   en cours sont enregistrés (de façon chiffrée) sur l'appareil.
2. En cas de coupure, ouvrez **Mode hors connexion** (lien dans le menu ou dans le bandeau rouge). Vous pouvez :
   créer une commande ou une vente directe, enregistrer une dépense, mettre à jour une livraison, consulter
   produits, stocks, clients et commandes récentes.
3. L'onglet **En attente** liste ce qui n'est pas encore envoyé.
4. Au retour d'Internet, tout est envoyé **automatiquement** (ou bouton « Synchroniser maintenant »).
   Une opération n'est jamais enregistrée deux fois.
5. Si un stock a changé pendant la coupure, l'opération passe **« à vérifier »** : le responsable du magasin
   la retrouve dans **Synchronisation** et choisit **Appliquer** ou **Rejeter**.

⚠️ Ne vous déconnectez pas tant que des opérations sont en attente : la déconnexion efface les données de
l'appareil (GesMA vous prévient).

## 9. Administration (direction)

- **Magasins** : ajouter un nouveau magasin (ex. Bouaflé) — il dispose aussitôt de ses propres stocks,
  commandes, livraisons et dépenses.
- **Utilisateurs** : créer les comptes, choisir le profil et le magasin, désactiver un compte.
- **Catégories de dépenses**, **API site web** (jetons de connexion du site), **Historique sensible**
  (corrections de stock, modifications de prix, annulations, conflits, comptes).
