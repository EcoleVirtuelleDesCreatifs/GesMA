@extends('layouts.app')
@section('title', $type === 'sale' ? 'Vente directe' : 'Nouvelle commande')
@section('content')
@php
    $user = auth()->user();
    $defaultStore = old('store_id', $user->isDirection() ? $stores->first()?->id : $user->store_id);
    $canPrice = $user->hasPermission('products.price');
    $productOptions = function ($selected = null) use ($products) {
        $html = '<option value="">— Choisir un produit —</option>';
        foreach ($products as $p) {
            $stocks = $p->stocks->pluck('quantity', 'store_id')->toJson();
            $html .= sprintf('<option value="%d" data-price="%d" data-stocks=\'%s\' %s>%s — %s</option>', $p->id, $p->price, e($stocks), (string) $selected === (string) $p->id ? 'selected' : '', e($p->name), e($p->reference));
        }
        return $html;
    };
    $oldItems = old('items', [['product_id' => '', 'quantity' => 1, 'unit_price' => '']]);
@endphp
<div class="page-header">
    <div>
        <h1>{{ $type === 'sale' ? 'Vente directe' : 'Nouvelle commande' }}</h1>
        <p class="subtitle">{{ $type === 'sale' ? 'Vente en magasin avec retrait immédiat : le stock est déduit et la vente est enregistrée comme livrée.' : 'Les produits sont réservés sur le stock du magasin dès l\'enregistrement.' }}</p>
    </div>
    <div class="actions">
        <a class="btn btn-secondary" href="{{ route('orders.create', ['type' => $type === 'sale' ? 'order' : 'sale']) }}">{{ $type === 'sale' ? 'Passer en commande' : 'Passer en vente directe' }}</a>
    </div>
</div>

<form method="post" action="{{ route('orders.store') }}" autocomplete="off">
    @csrf
    <input type="hidden" name="type" value="{{ $type }}">
    <div class="grid grid-2-1">
        <div>
            <div class="card">
                <h2>Client</h2>
                <div data-customer-picker data-url="{{ route('customers.search') }}">
                    <input type="hidden" name="customer_id" value="{{ old('customer_id', $customer?->id) }}" data-customer-id>
                    <div class="alert alert-info" data-customer-selected @if(! old('customer_id', $customer?->id)) hidden @endif>
                        <span data-customer-label>{{ $customer ? $customer->name.($customer->phone ? ' · '.$customer->phone : '') : 'Client sélectionné' }}</span>
                        <button class="btn btn-ghost btn-sm" type="button" data-customer-change>Changer</button>
                    </div>
                    <div class="field" @if(old('customer_id', $customer?->id)) hidden @endif>
                        <label for="customer_query">Rechercher un client existant</label>
                        <input type="search" id="customer_query" data-customer-query placeholder="Nom, téléphone ou e-mail">
                        <div data-customer-results hidden style="margin-top:.4rem"></div>
                    </div>
                    <fieldset data-customer-new @if(old('customer_id', $customer?->id)) hidden @endif>
                        <legend>Nouveau client {{ $type === 'sale' ? '(facultatif)' : '' }}</legend>
                        <div class="form-grid">
                            <x-field name="customer[name]" label="Nom complet" />
                            <x-field name="customer[phone]" label="Téléphone" type="tel" />
                            <x-field name="customer[email]" label="E-mail" type="email" />
                            <x-field name="customer[city]" label="Ville" />
                            <x-field name="customer[address]" label="Adresse" class="full" />
                        </div>
                    </fieldset>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2>Produits</h2><button class="btn btn-secondary btn-sm" type="button" data-add-line><x-icon name="plus" /> Ajouter</button></div>
                <div class="table-wrap" data-lines data-template="#line-template" data-store-select="#f_store_id" data-store-id="{{ $defaultStore }}">
                    <table class="table lines-table">
                        <thead><tr><th>Produit</th><th class="col-qty">Qté</th><th class="col-price">Prix unitaire</th><th class="col-total num">Total</th><th class="col-del"></th></tr></thead>
                        <tbody>
                        @foreach ($oldItems as $i => $item)
                            <tr>
                                <td class="col-product"><select name="items[{{ $i }}][product_id]" data-product required>{!! $productOptions($item['product_id'] ?? null) !!}</select><div data-stock-hint class="stock-hint"></div></td>
                                <td class="col-qty"><input type="number" min="1" name="items[{{ $i }}][quantity]" value="{{ $item['quantity'] ?? 1 }}" data-qty required></td>
                                <td class="col-price"><input type="number" min="0" name="items[{{ $i }}][unit_price]" value="{{ $item['unit_price'] ?? '' }}" data-price @readonly(! $canPrice)></td>
                                <td class="col-total num" data-line-total>0 F CFA</td>
                                <td class="col-del"><button class="btn btn-ghost btn-sm" type="button" data-remove-line aria-label="Retirer"><x-icon name="x" /></button></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                @unless ($canPrice)<p class="muted small" style="margin:.5rem 0 0">Les prix du catalogue sont appliqués. Seuls les responsables peuvent modifier un prix.</p>@endunless
                <template id="line-template">
                    <tr>
                        <td class="col-product"><select name="items[__i__][product_id]" data-product required>{!! $productOptions() !!}</select><div data-stock-hint class="stock-hint"></div></td>
                        <td class="col-qty"><input type="number" min="1" name="items[__i__][quantity]" value="1" data-qty required></td>
                        <td class="col-price"><input type="number" min="0" name="items[__i__][unit_price]" data-price @readonly(! $canPrice)></td>
                        <td class="col-total num" data-line-total>0 F CFA</td>
                        <td class="col-del"><button class="btn btn-ghost btn-sm" type="button" data-remove-line aria-label="Retirer"><x-icon name="x" /></button></td>
                    </tr>
                </template>
            </div>

            @if ($type === 'order')
                <div class="card">
                    <label class="checkbox"><input type="checkbox" name="requires_delivery" value="1" data-toggle-target="#delivery-fields" @checked(old('requires_delivery'))> Livraison à domicile</label>
                    <div id="delivery-fields" hidden style="margin-top:1rem">
                        <div class="form-grid">
                            <x-field name="delivery[address]" label="Adresse de livraison" class="full" />
                            <x-field name="delivery[city]" label="Ville" />
                            <x-field name="delivery[scheduled_date]" label="Date prévue" type="date" />
                            <x-field name="delivery[contact_name]" label="Personne à contacter" />
                            <x-field name="delivery[contact_phone]" label="Téléphone du contact" type="tel" />
                            <x-field name="delivery[driver_id]" label="Livreur (équipe logistique)" type="select" :options="$drivers->pluck('name', 'id')" placeholder="— À attribuer —" />
                            <x-field name="delivery[driver_name]" label="ou livreur externe" placeholder="Nom du prestataire" />
                            <x-field name="delivery[fee]" label="Frais de livraison (F CFA)" type="number" min="0" value="0" data-fee />
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <div>
            <div class="card">
                <h2>Récapitulatif</h2>
                @if ($user->isDirection())
                    <x-field name="store_id" label="Magasin" type="select" :options="$stores->pluck('name', 'id')" :value="$defaultStore" required />
                @else
                    <input type="hidden" name="store_id" id="f_store_id" value="{{ $user->store_id }}">
                    <p><span class="muted">Magasin :</span> <strong>{{ $user->store?->name }}</strong></p>
                @endif
                <x-field name="discount" label="Remise (F CFA)" type="number" min="0" value="0" data-discount />
                <x-field name="amount_paid" label="Montant encaissé (F CFA)" type="number" min="0" :value="$type === 'sale' ? '' : 0" :help="$type === 'sale' ? 'Laisser vide si la totalité est payée.' : 'Acompte éventuel.'" />
                <x-field name="payment_method" label="Mode de paiement" type="select" :options="config('gesma.payment_methods')" placeholder="—" />
                <x-field name="notes" label="Notes" type="textarea" rows="3" />
                <div class="totals">
                    <div class="row"><span>Sous-total</span><span data-total-subtotal>0 F CFA</span></div>
                    <div class="row"><span>Remise</span><span data-total-discount>0 F CFA</span></div>
                    @if ($type === 'order')<div class="row"><span>Livraison</span><span data-total-fee>0 F CFA</span></div>@endif
                    <div class="row grand"><span>Total</span><span data-total-grand>0 F CFA</span></div>
                </div>
                <div class="form-actions">
                    <a class="btn btn-secondary" href="{{ route('orders.index') }}">Annuler</a>
                    <button class="btn" type="submit">{{ $type === 'sale' ? 'Enregistrer la vente' : 'Enregistrer la commande' }}</button>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection
