@extends('layouts.app')
@section('title', 'Saisie hors connexion')
@section('content')
<div class="page-head">
    <div>
        <h1>Saisie hors connexion</h1>
        <small class="muted">Fonctionne même sans Internet : les opérations sont gardées sur cet appareil puis envoyées automatiquement au retour de la connexion.</small>
    </div>
    <div class="actions">
        <button type="button" class="btn" data-action="refresh">Mettre à jour les données</button>
        <button type="button" class="btn btn-primary" data-action="sync">Synchroniser maintenant</button>
    </div>
</div>

<div class="grid grid-4 mb">
    <div class="kpi"><div class="label">Connexion</div><div class="value" data-out="online" style="font-size:1.1rem">—</div></div>
    <div class="kpi"><div class="label">Dernière synchronisation</div><div class="value" data-out="last-sync" style="font-size:1.05rem">—</div></div>
    <div class="kpi accent"><div class="label">En attente</div><div class="value" data-out="pending">0</div><div class="sub" data-out="failed-sub"></div></div>
    <div class="kpi"><div class="label">Données locales du</div><div class="value" data-out="data-date" style="font-size:1.05rem">—</div></div>
</div>

<div class="alert alert-warning hidden" data-out="no-data">
    Aucune donnée locale sur cet appareil. Connectez-vous à Internet puis cliquez sur « Mettre à jour les données » pour préparer le mode hors connexion.
</div>
<div class="alert alert-warning hidden" data-out="session">
    Votre session a expiré : <a href="{{ route('login') }}">reconnectez-vous</a> pour envoyer les opérations en attente (elles sont conservées sur l’appareil).
</div>
<div class="alert alert-info hidden" data-out="not-encrypted">Ce navigateur ne permet pas de chiffrer les données locales (connexion non sécurisée). Utilisez l’adresse en https.</div>

<div class="card" data-offline-app>
    <div class="tabs" data-tabs>
        <button type="button" class="active" data-tab="order" data-perm="orders">Vente / commande</button>
        <button type="button" data-tab="expense" data-perm="expenses">Dépense</button>
        <button type="button" data-tab="deliveries" data-perm="deliveries">Livraisons</button>
        <button type="button" data-tab="stock">Stocks</button>
        <button type="button" data-tab="customers" data-perm="orders">Clients</button>
        <button type="button" data-tab="queue" id="file">File d’attente</button>
    </div>

    {{-- Vente / commande --}}
    <div data-tab-panel="order">
        <form data-form="order" autocomplete="off">
            <div class="radio-cards mb">
                <label><input type="radio" name="mode" value="direct" checked><span><strong>Vente directe</strong><small class="muted">Le client repart avec ses produits</small></span></label>
                <label><input type="radio" name="mode" value="delivery"><span><strong>À livrer</strong><small class="muted">Crée une livraison</small></span></label>
                <label><input type="radio" name="mode" value="pickup"><span><strong>À retirer</strong><small class="muted">Préparation puis retrait</small></span></label>
            </div>
            <div class="form-grid">
                <div class="field" data-store-field><label>Magasin</label><select name="store_id" data-fill="stores" required></select></div>
                <div class="field">
                    <label>Client existant</label>
                    <input type="search" data-customer-search placeholder="Nom ou téléphone">
                    <input type="hidden" name="customer_id">
                    <ul class="list" data-customer-results></ul>
                    <div class="alert alert-info hidden" data-customer-chosen style="margin:.4rem 0 0;display:flex;justify-content:space-between"><span></span><button type="button" class="btn btn-sm" data-customer-clear>Changer</button></div>
                </div>
            </div>
            <details class="collapse mt-sm" data-new-customer open>
                <summary>Nouveau client</summary>
                <div class="form-grid mt-sm">
                    <div class="field"><label>Nom</label><input type="text" name="c_name" placeholder="Client comptoir si vide"></div>
                    <div class="field"><label>Téléphone</label><input type="tel" name="c_phone"></div>
                    <div class="field"><label>Adresse</label><input type="text" name="c_address"></div>
                    <div class="field"><label>Ville</label><input type="text" name="c_city"></div>
                </div>
            </details>

            <h3 class="mt">Produits</h3>
            <div class="table-wrap">
                <table class="table line-items">
                    <thead><tr><th style="min-width:200px">Produit</th><th style="width:90px">Qté</th><th class="right">Prix</th><th class="right">Total</th><th></th></tr></thead>
                    <tbody data-lines></tbody>
                </table>
            </div>
            <button type="button" class="btn btn-sm mt-sm" data-add-line>+ Ajouter un produit</button>

            <div class="form-grid mt hidden" data-delivery-fields>
                <div class="field full"><label>Adresse de livraison *</label><input type="text" name="d_address"></div>
                <div class="field"><label>Ville</label><input type="text" name="d_city"></div>
                <div class="field"><label>Téléphone à contacter</label><input type="tel" name="d_phone"></div>
                <div class="field"><label>Date prévue</label><input type="date" name="d_date"></div>
                <div class="field"><label>Frais de livraison</label><input type="number" min="0" name="d_fee" value="0"></div>
            </div>

            <div class="form-grid mt">
                <div class="field"><label>Remise</label><input type="number" min="0" name="discount" value="0"></div>
                <div class="field"><label>Mode de paiement</label><select name="payment_method" data-fill="payment_methods"></select></div>
                <div class="field"><label>Montant encaissé</label><input type="number" min="0" name="amount_paid" placeholder="Total si vide (vente directe)"></div>
                <div class="field"><label>Notes</label><input type="text" name="notes"></div>
            </div>
            <div class="totals mt"><div class="grand"><span>Total</span><span class="num" data-order-total>0</span></div></div>
            <div class="form-actions"><button class="btn btn-primary">Enregistrer sur l’appareil</button></div>
        </form>
    </div>

    {{-- Dépense --}}
    <div data-tab-panel="expense" class="hidden">
        <form data-form="expense" style="max-width:640px">
            <div class="form-grid">
                <div class="field" data-store-field><label>Magasin</label><select name="store_id" data-fill="stores" required></select></div>
                <div class="field"><label>Date *</label><input type="date" name="spent_at" required></div>
                <div class="field"><label>Montant *</label><input type="number" min="1" name="amount" required></div>
                <div class="field"><label>Catégorie *</label><select name="category" data-fill="expense_categories" required></select></div>
                <div class="field full"><label>Description</label><input type="text" name="description" maxlength="255"></div>
            </div>
            <div class="form-actions"><button class="btn btn-primary">Enregistrer sur l’appareil</button></div>
        </form>
    </div>

    {{-- Livraisons --}}
    <div data-tab-panel="deliveries" class="hidden">
        <p class="muted" style="font-size:.88rem">Livraisons en cours au moment de la dernière mise à jour des données.</p>
        <div data-deliveries></div>
    </div>

    {{-- Stocks --}}
    <div data-tab-panel="stock" class="hidden">
        <div class="filters"><div class="field"><label>Recherche</label><input type="search" data-stock-search placeholder="Produit ou référence"></div></div>
        <p class="muted" style="font-size:.85rem">Quantités connues à la dernière mise à jour, diminuées des ventes saisies sur cet appareil. Elles seront vérifiées lors de la synchronisation.</p>
        <div class="table-wrap"><table class="table responsive"><thead><tr><th>Produit</th><th class="right">Prix</th><th class="right">Stock</th></tr></thead><tbody data-stock-rows></tbody></table></div>
    </div>

    {{-- Clients --}}
    <div data-tab-panel="customers" class="hidden">
        <div class="filters"><div class="field"><label>Recherche</label><input type="search" data-customers-search placeholder="Nom ou téléphone"></div></div>
        <div class="table-wrap"><table class="table responsive"><thead><tr><th>Client</th><th>Téléphone</th><th>Adresse</th></tr></thead><tbody data-customer-rows></tbody></table></div>
    </div>

    {{-- File d'attente --}}
    <div data-tab-panel="queue" class="hidden">
        <div class="table-wrap">
            <table class="table responsive">
                <thead><tr><th>Saisie le</th><th>Opération</th><th>État</th><th>Message du serveur</th><th></th></tr></thead>
                <tbody data-queue-rows></tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/offline-page.js') }}?v={{ config('gesma.asset_version', '1') }}"></script>
@endpush
