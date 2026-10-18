@extends('layouts.app')
@section('title', 'Nouvelle commande')
@section('content')
<div class="page-head"><h1>Nouvelle commande / vente</h1></div>

<form method="POST" action="{{ route('orders.store') }}" id="order-form">
    @csrf
    <div class="grid grid-main">
        <div>
            <div class="card">
                <div class="card-head"><h2>Type</h2></div>
                <div class="radio-cards">
                    <label><input type="radio" name="mode" value="direct" @checked(old('mode', 'direct') === 'direct')><span><strong>Vente directe</strong><small class="muted">Le client repart avec ses produits</small></span></label>
                    <label><input type="radio" name="mode" value="delivery" @checked(old('mode') === 'delivery')><span><strong>Commande à livrer</strong><small class="muted">Suivi de livraison</small></span></label>
                    <label><input type="radio" name="mode" value="pickup" @checked(old('mode') === 'pickup')><span><strong>Commande à retirer</strong><small class="muted">Préparation puis retrait</small></span></label>
                </div>
                @if(auth()->user()->isDirection())
                    <div class="field mt" style="max-width:320px">
                        <label for="store_id">Magasin *</label>
                        <select name="store_id" id="store_id" required>
                            @foreach($stores as $s)<option value="{{ $s->id }}" @selected(old('store_id', $defaultStore) == $s->id)>{{ $s->name }}</option>@endforeach
                        </select>
                    </div>
                @else
                    <input type="hidden" id="store_id" value="{{ $defaultStore }}">
                @endif
            </div>

            <div class="card">
                <div class="card-head"><h2>Produits</h2><button type="button" class="btn btn-sm" data-add-line>+ Ajouter une ligne</button></div>
                <div class="table-wrap">
                    <table class="table line-items">
                        <thead><tr><th style="min-width:220px">Produit</th><th style="width:90px">Qté</th><th class="right">Prix unitaire</th><th class="right">Total</th><th></th></tr></thead>
                        <tbody data-lines></tbody>
                    </table>
                </div>
                <template id="line-template">
                    <tr>
                        <td>
                            <select data-product required>
                                <option value="">— Choisir un produit —</option>
                                @foreach($products as $p)<option value="{{ $p->id }}">{{ $p->name }} — {{ $p->reference }}</option>@endforeach
                            </select>
                            <small class="muted" data-available></small>
                        </td>
                        <td><input type="number" min="1" value="1" data-qty required></td>
                        <td class="right num nowrap" data-price>—</td>
                        <td class="right num nowrap" data-line-total>—</td>
                        <td class="right"><button type="button" class="btn btn-sm" data-remove-line title="Retirer">✕</button></td>
                    </tr>
                </template>
            </div>

            <div class="card" data-delivery-block>
                <div class="card-head"><h2>Livraison</h2></div>
                <div class="form-grid">
                    <div class="field full"><label for="d_address">Adresse de livraison *</label><input type="text" id="d_address" name="delivery[address]" value="{{ old('delivery.address', $customer?->address) }}"></div>
                    <div class="field"><label for="d_city">Ville</label><input type="text" id="d_city" name="delivery[city]" value="{{ old('delivery.city', $customer?->city) }}"></div>
                    <div class="field"><label for="d_phone">Téléphone à contacter</label><input type="tel" id="d_phone" name="delivery[contact_phone]" value="{{ old('delivery.contact_phone', $customer?->phone) }}"></div>
                    <div class="field"><label for="d_date">Date prévue</label><input type="date" id="d_date" name="delivery[scheduled_date]" value="{{ old('delivery.scheduled_date', now()->addDay()->toDateString()) }}"></div>
                    <div class="field"><label for="d_fee">Frais de livraison</label><input type="number" min="0" id="d_fee" name="delivery[fee]" value="{{ old('delivery.fee', 0) }}" data-fee></div>
                    <div class="field">
                        <label for="d_driver">Livreur / responsable</label>
                        <select id="d_driver" name="delivery[driver_id]">
                            <option value="">— À désigner —</option>
                            @foreach($drivers as $d)<option value="{{ $d->id }}" @selected(old('delivery.driver_id') == $d->id)>{{ $d->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="field"><label for="d_driver_name">… ou livreur externe</label><input type="text" id="d_driver_name" name="delivery[driver_name]" value="{{ old('delivery.driver_name') }}" placeholder="Nom du transporteur"></div>
                </div>
            </div>
        </div>

        <div>
            <div class="card">
                <div class="card-head"><h2>Client</h2></div>
                <input type="hidden" name="customer_id" id="customer_id" value="{{ old('customer_id', $customer?->id) }}">
                <div data-customer-selected class="{{ old('customer_id', $customer?->id) ? '' : 'hidden' }}">
                    <div class="alert alert-info" style="display:flex;justify-content:space-between;gap:.5rem;align-items:center">
                        <span data-customer-label>{{ $customer ? $customer->name.($customer->phone ? ' · '.$customer->phone : '') : '' }}</span>
                        <button type="button" class="btn btn-sm" data-customer-clear>Changer</button>
                    </div>
                </div>
                <div data-customer-search class="{{ old('customer_id', $customer?->id) ? 'hidden' : '' }}">
                    <div class="field">
                        <label for="customer_q">Rechercher un client existant</label>
                        <input type="search" id="customer_q" placeholder="Nom ou téléphone" autocomplete="off" data-url="{{ route('customers.search') }}">
                    </div>
                    <ul class="list" data-customer-results></ul>
                    <p class="muted mt" style="font-size:.85rem">… ou nouveau client :</p>
                    <div class="field mb"><label for="c_name">Nom</label><input type="text" id="c_name" name="customer[name]" value="{{ old('customer.name') }}"></div>
                    <div class="field mb"><label for="c_phone">Téléphone</label><input type="tel" id="c_phone" name="customer[phone]" value="{{ old('customer.phone') }}"></div>
                    <div class="field mb"><label for="c_address">Adresse</label><input type="text" id="c_address" name="customer[address]" value="{{ old('customer.address') }}"></div>
                    <div class="field"><label for="c_city">Ville</label><input type="text" id="c_city" name="customer[city]" value="{{ old('customer.city') }}"></div>
                    <p class="hint muted" style="font-size:.78rem">Pour une vente directe sans fiche client, saisissez simplement « Client comptoir ».</p>
                </div>
            </div>

            <div class="card">
                <div class="card-head"><h2>Paiement</h2></div>
                <div class="field mb"><label for="discount">Remise</label><input type="number" min="0" id="discount" name="discount" value="{{ old('discount', 0) }}" data-discount></div>
                <div class="field mb">
                    <label for="payment_method">Mode de paiement</label>
                    <select name="payment_method" id="payment_method">
                        <option value="">—</option>
                        @foreach(config('gesma.payment_methods') as $k => $l)<option value="{{ $k }}" @selected(old('payment_method', 'especes') === $k)>{{ $l }}</option>@endforeach
                    </select>
                </div>
                <div class="field mb">
                    <label for="amount_paid">Montant encaissé</label>
                    <input type="number" min="0" id="amount_paid" name="amount_paid" value="{{ old('amount_paid') }}" data-paid placeholder="Total si vide (vente directe)">
                </div>
                <div class="totals">
                    <div><span>Sous-total</span><span class="num" data-subtotal>0</span></div>
                    <div><span>Remise</span><span class="num" data-discount-view>0</span></div>
                    <div data-fee-row><span>Livraison</span><span class="num" data-fee-view>0</span></div>
                    <div class="grand"><span>Total</span><span class="num" data-total>0</span></div>
                </div>
                <div class="field mt"><label for="notes">Notes</label><textarea id="notes" name="notes" rows="2">{{ old('notes') }}</textarea></div>
                <div class="form-actions"><button class="btn btn-primary btn-block" type="submit">Enregistrer</button></div>
            </div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
(function () {
    const products = {!! json_encode($products->mapWithKeys(fn ($p) => [$p->id => ['price' => (float) $p->price, 'stocks' => $p->stocks->pluck('quantity', 'store_id')]]), JSON_HEX_TAG | JSON_HEX_AMP) !!};
    const oldItems = {!! json_encode(array_values(old('items', [])), JSON_HEX_TAG | JSON_HEX_AMP) !!};
    const form = document.getElementById('order-form');
    const lines = form.querySelector('[data-lines]');
    const tpl = document.getElementById('line-template');
    const storeSelect = document.getElementById('store_id');
    const fmt = (n) => GesMAUI.money(n);

    function addLine(productId, qty) {
        const row = tpl.content.firstElementChild.cloneNode(true);
        lines.appendChild(row);
        if (productId) row.querySelector('[data-product]').value = productId;
        if (qty) row.querySelector('[data-qty]').value = qty;
        renumber(); recompute();
    }
    function renumber() {
        [...lines.children].forEach((row, i) => {
            row.querySelector('[data-product]').name = `items[${i}][product_id]`;
            row.querySelector('[data-qty]').name = `items[${i}][quantity]`;
        });
    }
    function recompute() {
        const storeId = storeSelect.value;
        let subtotal = 0;
        [...lines.children].forEach((row) => {
            const p = products[row.querySelector('[data-product]').value];
            const qty = parseInt(row.querySelector('[data-qty]').value || '0', 10);
            const avail = row.querySelector('[data-available]');
            if (!p) { row.querySelector('[data-price]').textContent = '—'; row.querySelector('[data-line-total]').textContent = '—'; avail.textContent = ''; return; }
            const stock = parseInt(p.stocks[storeId] ?? 0, 10);
            avail.textContent = `Stock magasin : ${stock}`;
            avail.style.color = qty > stock ? 'var(--danger)' : '';
            if (qty > stock) avail.textContent += ' — insuffisant';
            row.querySelector('[data-price]').textContent = fmt(p.price);
            row.querySelector('[data-line-total]').textContent = fmt(p.price * qty);
            subtotal += p.price * qty;
        });
        const mode = form.querySelector('input[name=mode]:checked').value;
        const discount = Math.min(parseFloat(form.querySelector('[data-discount]').value || 0), subtotal);
        const fee = mode === 'delivery' ? parseFloat(form.querySelector('[data-fee]').value || 0) : 0;
        form.querySelector('[data-subtotal]').textContent = fmt(subtotal);
        form.querySelector('[data-discount-view]').textContent = '− ' + fmt(discount);
        form.querySelector('[data-fee-view]').textContent = fmt(fee);
        form.querySelector('[data-fee-row]').classList.toggle('hidden', mode !== 'delivery');
        form.querySelector('[data-total]').textContent = fmt(subtotal - discount + fee);
        form.querySelector('[data-delivery-block]').classList.toggle('hidden', mode !== 'delivery');
        form.querySelector('#d_address').required = mode === 'delivery';
    }

    form.addEventListener('input', recompute);
    form.addEventListener('change', recompute);
    form.querySelector('[data-add-line]').addEventListener('click', () => addLine());
    lines.addEventListener('click', (e) => {
        if (e.target.matches('[data-remove-line]')) { e.target.closest('tr').remove(); renumber(); recompute(); }
    });

    // Recherche client
    const search = document.getElementById('customer_q');
    const results = form.querySelector('[data-customer-results]');
    let timer;
    search?.addEventListener('input', () => {
        clearTimeout(timer);
        const q = search.value.trim();
        if (q.length < 2) { results.innerHTML = ''; return; }
        timer = setTimeout(async () => {
            try {
                const res = await fetch(search.dataset.url + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } });
                const list = await res.json();
                results.innerHTML = '';
                list.forEach((c) => {
                    const li = document.createElement('li');
                    li.innerHTML = `<span><strong></strong><br><small class="muted"></small></span><button type="button" class="btn btn-sm">Choisir</button>`;
                    li.querySelector('strong').textContent = c.name;
                    li.querySelector('small').textContent = [c.phone, c.city].filter(Boolean).join(' · ');
                    li.querySelector('button').addEventListener('click', () => selectCustomer(c));
                    results.appendChild(li);
                });
                if (!list.length) results.innerHTML = '<li class="muted">Aucun client trouvé.</li>';
            } catch (e) { results.innerHTML = '<li class="muted">Recherche indisponible.</li>'; }
        }, 250);
    });
    function selectCustomer(c) {
        document.getElementById('customer_id').value = c.id;
        form.querySelector('[data-customer-label]').textContent = c.name + (c.phone ? ' · ' + c.phone : '');
        form.querySelector('[data-customer-selected]').classList.remove('hidden');
        form.querySelector('[data-customer-search]').classList.add('hidden');
        if (!form.querySelector('#d_address').value) form.querySelector('#d_address').value = c.address || '';
        if (!form.querySelector('#d_city').value) form.querySelector('#d_city').value = c.city || '';
        if (!form.querySelector('#d_phone').value) form.querySelector('#d_phone').value = c.phone || '';
    }
    form.querySelector('[data-customer-clear]').addEventListener('click', () => {
        document.getElementById('customer_id').value = '';
        form.querySelector('[data-customer-selected]').classList.add('hidden');
        form.querySelector('[data-customer-search]').classList.remove('hidden');
    });

    if (oldItems.length) oldItems.forEach((i) => addLine(i.product_id, i.quantity)); else addLine();
})();
</script>
@endpush
