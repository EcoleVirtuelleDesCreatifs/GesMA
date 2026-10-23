@extends('layouts.app')
@section('title', "Nouvelle commande d'achat")
@section('content')
<div class="page-head"><h1>Nouvelle commande d'achat</h1></div>

<form method="POST" action="{{ route('purchases.store') }}" class="card" id="po-form">
    @csrf
    <div class="grid grid-3">
        <div class="field"><label>Fournisseur *</label>
            <select name="supplier_id" required>
                <option value="">—</option>
                @foreach($suppliers as $s)<option value="{{ $s->id }}" @selected((int) old('supplier_id', request('supplier_id')) === $s->id)>{{ $s->name }} ({{ $s->code }})</option>@endforeach
            </select>
        </div>
        @if(auth()->user()->isDirection())
        <div class="field"><label>Magasin destinataire *</label>
            <select name="store_id" required>
                @foreach($stores as $s)<option value="{{ $s->id }}" @selected((int) old('store_id') === $s->id)>{{ $s->name }}</option>@endforeach
            </select>
        </div>
        @endif
        <div class="field"><label>Date de commande *</label><input type="date" name="ordered_at" value="{{ old('ordered_at', today()->toDateString()) }}" required></div>
        <div class="field"><label>Livraison prévue</label><input type="date" name="expected_at" value="{{ old('expected_at') }}"></div>
        <div class="field"><label>Remise globale (F)</label><input type="number" min="0" step="1" name="discount" value="{{ old('discount', 0) }}"></div>
        <div class="field"><label>Taxes (%)</label><input type="number" min="0" max="100" step="0.01" name="tax_rate" value="{{ old('tax_rate', 0) }}"></div>
        <div class="field"><label>Frais complémentaires (F)</label><input type="number" min="0" step="1" name="extra_fees" value="{{ old('extra_fees', 0) }}"></div>
    </div>
    <div class="field"><label>Observations</label><textarea name="notes" rows="2">{{ old('notes') }}</textarea></div>

    <h2>Lignes de la commande</h2>
    <div class="table-wrap"><table class="table" id="items-table">
        <thead><tr><th style="width:38%">Produit</th><th class="right">Quantité</th><th class="right">Prix d'achat (F)</th><th class="right">Remise (F)</th><th class="right">Total</th><th></th></tr></thead>
        <tbody>
            <tr class="item-row">
                <td><select name="items[0][product_id]" class="po-product" required>
                    <option value="">—</option>
                    @foreach($products as $p)<option value="{{ $p->id }}">{{ $p->reference }} — {{ $p->name }}</option>@endforeach
                </select></td>
                <td><input type="number" min="1" name="items[0][quantity]" class="po-qty right" value="1" required></td>
                <td><input type="number" min="0" step="1" name="items[0][unit_price]" class="po-price right" value="0" required></td>
                <td><input type="number" min="0" step="1" name="items[0][discount]" class="po-disc right" value="0"></td>
                <td class="right num po-total">0</td>
                <td><button type="button" class="btn btn-sm btn-danger po-remove">×</button></td>
            </tr>
        </tbody>
        <tfoot><tr><td colspan="4" class="right"><strong>Total estimé</strong></td><td class="right num" id="po-grand">0</td><td></td></tr></tfoot>
    </table></div>
    <button type="button" class="btn btn-sm" id="po-add">+ Ajouter une ligne</button>

    <div class="actions mt">
        <button class="btn btn-primary">Enregistrer le brouillon</button>
        <a class="btn" href="{{ route('purchases.index') }}">Annuler</a>
    </div>
</form>
@endsection

@push('scripts')
<script>
(function () {
    const tbody = document.querySelector('#items-table tbody');
    const first = tbody.querySelector('.item-row');
    let i = 1;

    function totals() {
        let grand = 0;
        tbody.querySelectorAll('.item-row').forEach(function (row) {
            const q = +row.querySelector('.po-qty').value || 0;
            const p = +row.querySelector('.po-price').value || 0;
            const d = +row.querySelector('.po-disc').value || 0;
            const t = Math.max(0, q * p - d);
            row.querySelector('.po-total').textContent = t.toLocaleString('fr-FR');
            grand += t;
        });
        document.getElementById('po-grand').textContent = grand.toLocaleString('fr-FR');
    }

    document.getElementById('po-add').addEventListener('click', function () {
        const row = first.cloneNode(true);
        row.querySelectorAll('input,select').forEach(function (el) {
            el.name = el.name.replace(/items\[\d+\]/, 'items[' + i + ']');
            if (el.type === 'number') el.value = el.classList.contains('po-qty') ? 1 : 0;
        });
        tbody.appendChild(row); i++; totals();
    });
    tbody.addEventListener('input', totals);
    tbody.addEventListener('click', function (e) {
        if (e.target.classList.contains('po-remove') && tbody.querySelectorAll('.item-row').length > 1) {
            e.target.closest('tr').remove(); totals();
        }
    });
    totals();
})();
</script>
@endpush
