@extends('layouts.app')
@php($label = \App\Models\Document::TYPES[$type])
@section('title', 'Nouveau '.strtolower($label))
@section('content')
<div class="page-head"><h1>{{ $type === 'devis' ? 'Nouveau devis' : 'Nouvelle facture' }}</h1></div>
@php($canPrice = auth()->user()->hasPermission('products.price'))
<form method="POST" action="{{ route('documents.store') }}" id="doc-form">
    @csrf
    <input type="hidden" name="type" value="{{ $type }}">
    <div class="grid grid-main">
        <div>
            <div class="card">
                <div class="card-head"><h2>Lignes</h2><button type="button" class="btn btn-sm" data-add-line>+ Ajouter une ligne</button></div>
                <div class="table-wrap">
                    <table class="table line-items">
                        <thead><tr><th style="min-width:200px">Produit</th><th>Désignation (si hors catalogue)</th><th style="width:80px">Qté</th><th style="width:130px">Prix unitaire</th><th class="right">Total</th><th></th></tr></thead>
                        <tbody data-lines></tbody>
                    </table>
                </div>
                <template id="doc-line">
                    <tr>
                        <td><select data-product><option value="">— Hors catalogue —</option>@foreach($products as $p)<option value="{{ $p->id }}" data-price="{{ (float) $p->price }}">{{ $p->name }} ({{ $p->reference }})</option>@endforeach</select></td>
                        <td><input type="text" data-description placeholder="Ex. : Montage, sur-mesure…"></td>
                        <td><input type="number" min="1" value="1" data-qty required></td>
                        <td><input type="number" min="0" step="1" data-unit-price></td>
                        <td class="right num nowrap" data-line-total>0</td>
                        <td><button type="button" class="btn btn-sm" data-remove-line>✕</button></td>
                    </tr>
                </template>
                @unless($canPrice)<p class="muted" style="font-size:.82rem">Le prix des produits du catalogue est appliqué automatiquement. Le prix n’est saisi que pour les lignes hors catalogue.</p>@endunless
            </div>
        </div>
        <div>
            <div class="card">
                <h2>Client</h2>
                <input type="hidden" name="customer_id" id="customer_id" value="{{ old('customer_id', $customer?->id) }}" required>
                <div class="alert alert-info {{ $customer ? '' : 'hidden' }}" data-customer-selected><span data-customer-label>{{ $customer?->name }}</span></div>
                <div class="field">
                    <label for="customer_q">Rechercher le client</label>
                    <input type="search" id="customer_q" placeholder="Nom ou téléphone" autocomplete="off" data-url="{{ route('customers.search') }}">
                </div>
                <ul class="list" data-customer-results></ul>
                <p class="muted" style="font-size:.8rem">Client absent ? <a href="{{ route('customers.create') }}">Créer la fiche client</a></p>
            </div>
            <div class="card">
                <h2>Informations</h2>
                @if(auth()->user()->isDirection())
                    <div class="field mb"><label for="store_id">Magasin *</label>
                        <select name="store_id" id="store_id" required>@foreach($stores as $s)<option value="{{ $s->id }}" @selected(old('store_id', $customer?->store_id) == $s->id)>{{ $s->name }}</option>@endforeach</select>
                    </div>
                @endif
                <div class="field mb"><label for="issued_at">Date *</label><input type="date" name="issued_at" id="issued_at" value="{{ old('issued_at', today()->toDateString()) }}" required></div>
                @if($type === 'devis')
                    <div class="field mb"><label for="valid_until">Valable jusqu’au</label><input type="date" name="valid_until" id="valid_until" value="{{ old('valid_until', today()->addDays(30)->toDateString()) }}"></div>
                @else
                    <div class="field mb"><label for="amount_paid">Montant déjà réglé</label><input type="number" min="0" name="amount_paid" id="amount_paid" value="{{ old('amount_paid', 0) }}"></div>
                @endif
                <div class="field mb"><label for="discount">Remise</label><input type="number" min="0" name="discount" id="discount" value="{{ old('discount', 0) }}" data-discount></div>
                <div class="field mb"><label for="delivery_fee">Frais de livraison</label><input type="number" min="0" name="delivery_fee" id="delivery_fee" value="{{ old('delivery_fee', 0) }}" data-fee></div>
                <div class="field mb"><label for="notes">Conditions / notes</label><textarea name="notes" id="notes" rows="3">{{ old('notes') }}</textarea></div>
                <div class="totals"><div class="grand"><span>Total</span><span class="num" data-total>0</span></div></div>
                <div class="form-actions"><button class="btn btn-primary btn-block">Créer {{ $type === 'devis' ? 'le devis' : 'la facture' }}</button></div>
            </div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
(function () {
    const canPrice = {{ $canPrice ? 'true' : 'false' }};
    const form = document.getElementById('doc-form');
    const lines = form.querySelector('[data-lines]');
    const tpl = document.getElementById('doc-line');
    function renumber() {
        [...lines.children].forEach((row, i) => {
            row.querySelector('[data-product]').name = `items[${i}][product_id]`;
            row.querySelector('[data-description]').name = `items[${i}][description]`;
            row.querySelector('[data-qty]').name = `items[${i}][quantity]`;
            row.querySelector('[data-unit-price]').name = `items[${i}][unit_price]`;
        });
    }
    function recompute() {
        let total = 0;
        [...lines.children].forEach((row) => {
            const sel = row.querySelector('[data-product]');
            const price = row.querySelector('[data-unit-price]');
            const opt = sel.selectedOptions[0];
            if (sel.value) {
                if (!canPrice || price.value === '') price.value = opt.dataset.price;
                price.readOnly = !canPrice;
            } else { price.readOnly = false; }
            const line = (parseFloat(price.value) || 0) * (parseInt(row.querySelector('[data-qty]').value) || 0);
            row.querySelector('[data-line-total]').textContent = GesMAUI.money(line);
            total += line;
        });
        total = total - Math.min(parseFloat(form.querySelector('[data-discount]').value) || 0, total) + (parseFloat(form.querySelector('[data-fee]').value) || 0);
        form.querySelector('[data-total]').textContent = GesMAUI.money(total);
    }
    function addLine() { lines.appendChild(tpl.content.firstElementChild.cloneNode(true)); renumber(); recompute(); }
    form.querySelector('[data-add-line]').addEventListener('click', addLine);
    lines.addEventListener('change', (e) => { if (e.target.matches('[data-product]')) { e.target.closest('tr').querySelector('[data-unit-price]').value = ''; } recompute(); });
    form.addEventListener('input', recompute);
    lines.addEventListener('click', (e) => { if (e.target.matches('[data-remove-line]')) { e.target.closest('tr').remove(); renumber(); recompute(); } });
    addLine();

    const search = document.getElementById('customer_q');
    const results = form.querySelector('[data-customer-results]');
    let timer;
    search.addEventListener('input', () => {
        clearTimeout(timer);
        const q = search.value.trim();
        if (q.length < 2) { results.innerHTML = ''; return; }
        timer = setTimeout(async () => {
            const res = await fetch(search.dataset.url + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } });
            const list = await res.json();
            results.innerHTML = '';
            list.forEach((c) => {
                const li = document.createElement('li');
                li.innerHTML = '<span></span><button type="button" class="btn btn-sm">Choisir</button>';
                li.querySelector('span').textContent = c.name + (c.phone ? ' · ' + c.phone : '');
                li.querySelector('button').addEventListener('click', () => {
                    document.getElementById('customer_id').value = c.id;
                    form.querySelector('[data-customer-label]').textContent = c.name;
                    form.querySelector('[data-customer-selected]').classList.remove('hidden');
                    results.innerHTML = ''; search.value = '';
                });
                results.appendChild(li);
            });
            if (!list.length) results.innerHTML = '<li class="muted">Aucun client trouvé.</li>';
        }, 250);
    });
})();
</script>
@endpush
