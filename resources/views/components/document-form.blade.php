@props(['document', 'customer' => null, 'products', 'stores'])
{{-- Formulaire commun aux devis et factures : client, magasin, lignes, totaux. --}}
@php
    $productOptions = function ($selected = null) use ($products) {
        $html = '<option value="">— Ligne libre —</option>';
        foreach ($products as $p) {
            $html .= sprintf('<option value="%d" data-price="%d" %s>%s — %s</option>', $p->id, $p->price, (string) $selected === (string) $p->id ? 'selected' : '', e($p->name), e($p->reference));
        }
        return $html;
    };
    $lines = old('lines', $document->exists ? $document->lines->map->only(['product_id', 'designation', 'quantity', 'unit_price'])->all() : [['product_id' => '', 'designation' => '', 'quantity' => 1, 'unit_price' => '']]);
    $customerId = old('customer_id', $document->customer_id ?? $customer?->id);
@endphp
<div class="grid grid-2-1">
    <div>
        <div class="card">
            <h2>Client</h2>
            <div data-customer-picker data-url="{{ route('customers.search') }}">
                <input type="hidden" name="customer_id" value="{{ $customerId }}" data-customer-id>
                <div class="alert alert-info" data-customer-selected @if(! $customerId) hidden @endif>
                    <span data-customer-label>{{ $customer ? $customer->name.($customer->phone ? ' · '.$customer->phone : '') : 'Client sélectionné' }}</span>
                    <button class="btn btn-ghost btn-sm" type="button" data-customer-change>Changer</button>
                </div>
                <div class="field @error('customer_id') has-error @enderror" @if($customerId) hidden @endif>
                    <label for="cq">Rechercher le client</label>
                    <input type="search" id="cq" data-customer-query placeholder="Nom, téléphone ou e-mail">
                    <div data-customer-results hidden style="margin-top:.4rem"></div>
                    <div class="help">Client introuvable ? <a href="{{ route('customers.create') }}" target="_blank">Créer la fiche client</a> puis recherchez-le.</div>
                    @error('customer_id')<div class="error">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h2>Lignes</h2><button class="btn btn-secondary btn-sm" type="button" data-add-line><x-icon name="plus" /> Ajouter</button></div>
            <div class="table-wrap" data-lines data-template="#doc-line">
                <table class="table lines-table">
                    <thead><tr><th>Produit / désignation</th><th class="col-qty">Qté</th><th class="col-price">Prix unitaire</th><th class="col-total num">Total</th><th class="col-del"></th></tr></thead>
                    <tbody>
                    @foreach ($lines as $i => $line)
                        <tr>
                            <td class="col-product">
                                <select name="lines[{{ $i }}][product_id]" data-product>{!! $productOptions($line['product_id'] ?? null) !!}</select>
                                <input type="text" name="lines[{{ $i }}][designation]" value="{{ $line['designation'] ?? '' }}" placeholder="Désignation" data-designation style="margin-top:.3rem">
                            </td>
                            <td class="col-qty"><input type="number" min="1" name="lines[{{ $i }}][quantity]" value="{{ $line['quantity'] ?? 1 }}" data-qty required></td>
                            <td class="col-price"><input type="number" min="0" name="lines[{{ $i }}][unit_price]" value="{{ $line['unit_price'] ?? '' }}" data-price required></td>
                            <td class="col-total num" data-line-total>0 F CFA</td>
                            <td class="col-del"><button class="btn btn-ghost btn-sm" type="button" data-remove-line><x-icon name="x" /></button></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <template id="doc-line"><tr><td class="col-product"><select name="lines[__i__][product_id]" data-product>{!! $productOptions() !!}</select><input type="text" name="lines[__i__][designation]" placeholder="Désignation" data-designation style="margin-top:.3rem"></td><td class="col-qty"><input type="number" min="1" name="lines[__i__][quantity]" value="1" data-qty required></td><td class="col-price"><input type="number" min="0" name="lines[__i__][unit_price]" data-price required></td><td class="col-total num" data-line-total>0 F CFA</td><td class="col-del"><button class="btn btn-ghost btn-sm" type="button" data-remove-line><x-icon name="x" /></button></td></tr></template>
        </div>
        <div class="card"><x-field name="notes" label="Notes / conditions particulières" type="textarea" :value="$document->notes" rows="3" /></div>
    </div>
    <div>
        <div class="card">
            @include('partials.store-select', ['value' => $document->store_id, 'stores' => $stores])
            {{ $dates }}
            <x-field name="discount" label="Remise (F CFA)" type="number" min="0" :value="$document->discount ?? 0" data-discount />
            <x-field name="delivery_fee" label="Frais de livraison (F CFA)" type="number" min="0" :value="$document->delivery_fee ?? 0" data-fee />
            {{ $extra ?? '' }}
            <div class="totals">
                <div class="row"><span>Sous-total</span><span data-total-subtotal>0 F CFA</span></div>
                <div class="row"><span>Remise</span><span data-total-discount>0 F CFA</span></div>
                <div class="row"><span>Livraison</span><span data-total-fee>0 F CFA</span></div>
                <div class="row grand"><span>Total</span><span data-total-grand>0 F CFA</span></div>
            </div>
            <div class="form-actions">{{ $actions }}</div>
        </div>
    </div>
</div>
