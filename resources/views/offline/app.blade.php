<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="{{ config('gesma.colors.primary') }}">
    <title>Mode hors connexion — GesMA</title>
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/images/icon-192.png">
    <link rel="stylesheet" href="/css/app.css">
    <style>
        .offline-shell { max-width: 960px; margin: 0 auto; padding: 1rem; }
        .offline-head { display: flex; align-items: center; gap: .75rem; flex-wrap: wrap; margin-bottom: 1rem; }
        .offline-head img { width: 40px; height: 40px; }
        .offline-head .spacer { flex: 1; }
        .op { border: 1px solid var(--line); border-radius: 8px; padding: .7rem .9rem; margin-bottom: .6rem; background: #fff; }
        .op .op-head { display: flex; justify-content: space-between; gap: .5rem; flex-wrap: wrap; align-items: center; }
        .delivery-item { border: 1px solid var(--line); border-radius: 8px; padding: .8rem .9rem; margin-bottom: .7rem; background: #fff; }
        .delivery-item .actions { display: flex; gap: .4rem; flex-wrap: wrap; margin-top: .5rem; }
        .picker-results { margin-top: .4rem; max-height: 240px; overflow: auto; }
    </style>
</head>
<body>
<div class="offline-shell" id="offline-app">
    <div class="offline-head">
        <img src="/images/logo.svg" alt="">
        <div>
            <h1 style="margin:0">GesMA · Mode hors connexion</h1>
            <div class="muted small" data-user-line>Chargement des données de l'appareil…</div>
        </div>
        <div class="spacer"></div>
        <span class="sync-indicator" data-sync-indicator>
            <span class="dot"></span><span data-sync-label>…</span>
            <span class="pill hidden" data-sync-pending></span>
            <span class="pill failed hidden" data-sync-failed></span>
        </span>
    </div>

    <div class="alert alert-warning hidden" data-not-configured>
        <strong>Cet appareil n'est pas encore configuré pour le mode hors connexion.</strong><br>
        Connectez-vous une première fois à GesMA avec Internet : les données nécessaires seront alors enregistrées sur l'appareil.
        <div style="margin-top:.6rem"><a class="btn" href="/login">Se connecter</a></div>
    </div>

    <div data-app hidden>
        <div class="alert alert-info" data-online-hint hidden>
            La connexion est disponible. Les opérations en attente sont envoyées automatiquement.
            <a href="/" class="strong">Retourner à GesMA en ligne</a>
        </div>

        <nav class="tabs" data-tabs>
            <button type="button" data-tab="status" class="active">État</button>
            <button type="button" data-tab="order" data-perm="orders">Commande / vente</button>
            <button type="button" data-tab="expense" data-perm="expenses">Dépense</button>
            <button type="button" data-tab="deliveries" data-perm="deliveries">Livraisons</button>
            <button type="button" data-tab="browse">Consulter</button>
            <button type="button" data-tab="outbox">En attente <span class="count" data-outbox-count>0</span></button>
        </nav>

        <!-- État -->
        <section data-panel="status">
            <div class="stats" data-sync-panel>
                <div class="stat"><div class="label">Connexion</div><div class="value" data-st-connection>—</div></div>
                <div class="stat"><div class="label">Dernière synchronisation</div><div class="value" style="font-size:1rem" data-st-last>—</div></div>
                <div class="stat warning"><div class="label">En attente</div><div class="value" data-st-pending>0</div></div>
                <div class="stat danger"><div class="label">À vérifier</div><div class="value" data-st-failed>0</div></div>
            </div>
            <div class="card">
                <p class="muted" style="margin:0 0 .5rem">Données de l'appareil mises à jour : <strong data-bootstrap-at>—</strong></p>
                <p class="small text-danger" data-error-line hidden></p>
                <button class="btn" type="button" data-sync-now>Synchroniser maintenant</button>
                <a class="btn btn-secondary" href="/login" data-login-link hidden>Se reconnecter</a>
            </div>
            <div class="card">
                <h2>Dernières opérations synchronisées</h2>
                <ul class="timeline" data-history><li class="muted">Aucune pour le moment.</li></ul>
            </div>
        </section>

        <!-- Commande / vente -->
        <section data-panel="order" hidden>
            <form class="card" data-order-form autocomplete="off">
                <div class="tabs" style="margin-top:-.25rem">
                    <label class="checkbox" style="margin-right:1rem"><input type="radio" name="type" value="order" checked> Commande</label>
                    <label class="checkbox"><input type="radio" name="type" value="sale"> Vente directe (retrait immédiat)</label>
                </div>
                <div class="form-grid">
                    <div class="field" data-store-field><label>Magasin</label><select name="store_id" data-store></select></div>
                    <div class="field"><label>Client</label>
                        <input type="search" placeholder="Rechercher par nom ou téléphone" data-cust-search>
                        <div class="picker-results" data-cust-results></div>
                        <div class="alert alert-info hidden" data-cust-chosen style="margin:.4rem 0 0"></div>
                    </div>
                </div>
                <fieldset data-cust-new>
                    <legend>Nouveau client (si introuvable)</legend>
                    <div class="form-grid">
                        <div class="field"><label>Nom</label><input type="text" name="c_name"></div>
                        <div class="field"><label>Téléphone</label><input type="tel" name="c_phone"></div>
                        <div class="field"><label>Ville</label><input type="text" name="c_city"></div>
                        <div class="field"><label>Adresse</label><input type="text" name="c_address"></div>
                    </div>
                </fieldset>
                <div class="table-wrap">
                    <table class="table lines-table">
                        <thead><tr><th>Produit</th><th class="col-qty">Qté</th><th class="col-price">Prix unit.</th><th class="col-total num">Total</th><th class="col-del"></th></tr></thead>
                        <tbody data-lines-body></tbody>
                    </table>
                </div>
                <p><button class="btn btn-secondary btn-sm" type="button" data-add-line>+ Ajouter un produit</button></p>
                <div class="form-grid-3">
                    <div class="field"><label>Remise (F CFA)</label><input type="number" min="0" name="discount" value="0"></div>
                    <div class="field"><label>Montant payé</label><input type="number" min="0" name="amount_paid" value="0"></div>
                    <div class="field"><label>Mode de paiement</label><select name="payment_method" data-payment></select></div>
                </div>
                <label class="checkbox" data-delivery-toggle-wrap><input type="checkbox" name="requires_delivery" value="1" data-delivery-toggle> Livraison à domicile</label>
                <fieldset data-delivery-fields hidden style="margin-top:.6rem">
                    <legend>Livraison</legend>
                    <div class="form-grid">
                        <div class="field"><label>Adresse</label><input type="text" name="d_address"></div>
                        <div class="field"><label>Ville</label><input type="text" name="d_city"></div>
                        <div class="field"><label>Téléphone du contact</label><input type="tel" name="d_phone"></div>
                        <div class="field"><label>Date prévue</label><input type="date" name="d_date"></div>
                        <div class="field"><label>Frais de livraison</label><input type="number" min="0" name="d_fee" value="0"></div>
                        <div class="field"><label>Livreur</label><select name="d_driver" data-drivers></select></div>
                    </div>
                </fieldset>
                <div class="field" style="margin-top:.8rem"><label>Notes</label><textarea name="notes" rows="2"></textarea></div>
                <div class="totals">
                    <div class="row grand"><span>Total</span><span data-order-total>0 F CFA</span></div>
                </div>
                <div class="alert alert-warning hidden" data-stock-warning></div>
                <div class="form-actions"><button class="btn" type="submit">Enregistrer sur l'appareil</button></div>
            </form>
        </section>

        <!-- Dépense -->
        <section data-panel="expense" hidden>
            <form class="card" data-expense-form autocomplete="off">
                <div class="form-grid">
                    <div class="field"><label>Date</label><input type="date" name="spent_at" required></div>
                    <div class="field"><label>Montant (F CFA)</label><input type="number" min="1" name="amount" required></div>
                    <div class="field"><label>Catégorie</label><select name="expense_category_id" data-exp-categories required></select></div>
                    <div class="field" data-store-field><label>Magasin</label><select name="store_id" data-store></select></div>
                    <div class="field full"><label>Description</label><input type="text" name="description" required maxlength="255"></div>
                    <div class="field"><label>Mode de paiement</label><select name="payment_method" data-payment></select></div>
                </div>
                <div class="form-actions"><button class="btn" type="submit">Enregistrer sur l'appareil</button></div>
            </form>
        </section>

        <!-- Livraisons -->
        <section data-panel="deliveries" hidden>
            <div class="filters"><div class="field grow"><input type="search" placeholder="Rechercher (commande, client, adresse)" data-deliv-search></div></div>
            <div data-deliveries></div>
        </section>

        <!-- Consulter -->
        <section data-panel="browse" hidden>
            <div class="tabs" data-subtabs>
                <button type="button" data-sub="products" class="active">Produits et stocks</button>
                <button type="button" data-sub="customers">Clients</button>
                <button type="button" data-sub="orders">Commandes récentes</button>
            </div>
            <div class="filters"><div class="field grow"><input type="search" placeholder="Rechercher…" data-browse-search></div></div>
            <div class="card flush"><div class="table-wrap" data-browse></div></div>
        </section>

        <!-- En attente -->
        <section data-panel="outbox" hidden>
            <p class="muted">Opérations enregistrées sur cet appareil et pas encore confirmées par le serveur.</p>
            <div data-outbox></div>
        </section>
    </div>
</div>
<script src="/js/offline-store.js"></script>
<script src="/js/app.js"></script>
<script src="/js/offline-app.js"></script>
</body>
</html>
