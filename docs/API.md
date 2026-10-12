# API GesMA pour le site web Mobilier Addict

Base : `https://<serveur-gesma>/api/v1` — réponses JSON, montants en **F CFA entiers**.

## Authentification

Créer un jeton dans **Administration → API site web** (affiché une seule fois), puis l'envoyer à chaque appel :

```
Authorization: Bearer gma_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
Accept: application/json
```

Limite : 120 requêtes par minute. Un jeton désactivé renvoie `401`.

## Produits et stocks

### `GET /products`
Paramètres : `updated_since` (date ISO, facultatif), `per_page` (≤ 200), `page`.
Seuls les produits marqués « Publier sur le site web » sont renvoyés.

```json
{
  "data": [{
    "reference": "MA-0001", "name": "Canapé 3 places Abidjan", "category": "Salons",
    "description": "…", "price": 385000, "status": "available", "available": true,
    "stock_total": 12, "stock_by_store": {"YAM": 5, "BKE": 7},
    "image_url": "https://…/storage/products/…jpg", "updated_at": "2026-10-12T10:00:00+00:00"
  }],
  "meta": {"current_page": 1, "last_page": 1, "total": 21}
}
```

### `GET /products/{reference}`
Un produit.

### `GET /stock?store=YAM`
Disponibilités par produit (paramètre `store` facultatif) :
`{"data":[{"reference":"MA-0001","total":12,"stores":{"YAM":5,"BKE":7}}], "generated_at": "…"}`

### `GET /stores`
Liste des magasins actifs (`code`, `name`, `city`…).

## Commandes

### `POST /orders` — envoyer une commande du site

```json
{
  "external_reference": "WEB-2026-001234",
  "store_code": "YAM",
  "customer": {"name": "Aya Koné", "phone": "+2250700000000", "email": "aya@exemple.ci",
               "address": "Cocody", "city": "Abidjan", "website_customer_id": "558"},
  "items": [{"reference": "MA-0001", "quantity": 1, "unit_price": 385000}],
  "discount": 0,
  "amount_paid": 385000,
  "payment_method": "mobile_money",
  "delivery": {"address": "Cocody Riviera 3", "city": "Abidjan", "contact_phone": "+2250700000000",
               "scheduled_date": "2026-10-15", "fee": 10000},
  "notes": "Livrer l'après-midi",
  "ordered_at": "2026-10-12T09:15:00Z"
}
```

- `external_reference` (obligatoire) identifie la commande du site : **un même envoi répété ne crée jamais
  de doublon**. La première réponse est `201` avec `"duplicate": false`, les suivantes `200` avec `"duplicate": true`.
- `store_code` facultatif : sinon magasin par défaut du jeton, puis `GESMA_WEBSITE_DEFAULT_STORE`.
- Le client est retrouvé par `website_customer_id` puis par téléphone ; sinon il est créé.
- Si le stock est insuffisant, la commande est acceptée et `stock_warning` est renseigné (le magasin est
  alerté). Avec `GESMA_WEBSITE_ACCEPT_WITHOUT_STOCK=false`, l'API répond `409` avec le détail `shortages`.
- Une adresse dans `delivery` crée automatiquement la livraison.

Réponse :

```json
{"duplicate": false, "stock_warning": null,
 "data": {"number": "CMD-2026-00057", "external_reference": "WEB-2026-001234", "status": "preparing",
          "status_label": "En préparation", "store": "YAM", "total": 395000, "amount_paid": 385000,
          "delivery_status": "pending", "items": [...], "ordered_at": "…", "updated_at": "…"}}
```

### `GET /orders/{external_reference}`
Suivi : `status` ∈ `preparing`, `shipped`, `delivered`, `not_delivered`, `cancelled`, `returned` ;
`delivery_status` ∈ `pending`, `preparing`, `in_transit`, `delivered`, `failed`, `returned`.

### `POST /orders/{external_reference}/cancel`
Corps : `{"reason": "Annulée par le client"}`. Les produits sont remis en stock.

## Notification des changements de statut (facultatif)

Si `GESMA_WEBSITE_WEBHOOK_URL` est défini, GesMA envoie un `POST` JSON à chaque changement de statut
d'une commande du site :

```json
{"event": "order.status_changed", "external_reference": "WEB-2026-001234", "number": "CMD-2026-00057",
 "status": "shipped", "delivery_status": "in_transit", "reason": null, "updated_at": "…"}
```

En-tête `X-Gesma-Signature` = HMAC-SHA256 du corps avec `GESMA_WEBSITE_WEBHOOK_SECRET`. En cas d'échec,
GesMA réessaie jusqu'à 5 fois (1 min, 5 min, 15 min, 1 h).

## Codes d'erreur

| Code | Signification |
|------|---------------|
| 401 | Jeton absent, invalide ou désactivé |
| 404 | Produit ou commande introuvable |
| 409 | Stock insuffisant (si l'acceptation sans stock est désactivée) |
| 422 | Données invalides (`errors` détaille chaque champ) |
| 429 | Trop de requêtes |
