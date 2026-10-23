@extends('layouts.app')
@section('title', 'Déclarer un retour fournisseur')
@section('content')
<div class="page-head"><h1>Déclarer un retour fournisseur</h1></div>

<form method="POST" action="{{ route('supplier-returns.store') }}" class="card">
    @csrf
    <div class="grid grid-3">
        <div class="field"><label>Fournisseur *</label>
            <select name="supplier_id" required><option value="">—</option>
                @foreach($suppliers as $s)<option value="{{ $s->id }}" @selected((int) old('supplier_id') === $s->id)>{{ $s->name }}</option>@endforeach
            </select>
        </div>
        <div class="field"><label>Date du retour *</label><input type="date" name="returned_at" value="{{ old('returned_at', today()->toDateString()) }}" required></div>
        <div class="field"><label>Décision *</label>
            <select name="decision">@foreach($decisions as $k => $l)<option value="{{ $k }}" @selected(old('decision') === $k)>{{ $l }}</option>@endforeach</select>
        </div>
        @if(auth()->user()->isDirection())
        <div class="field"><label>Magasin *</label>
            <select name="store_id" required>@foreach($stores as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</select>
        </div>
        @endif
    </div>
    <div class="field"><label>Notes</label><textarea name="notes" rows="2">{{ old('notes') }}</textarea></div>

    <h2>Produits retournés</h2>
    <div class="table-wrap"><table class="table" id="ret-table">
        <thead><tr><th style="width:34%">Produit</th><th class="right">Quantité</th><th>Déjà entré en stock ?</th><th>Motif</th><th></th></tr></thead>
        <tbody>
            <tr class="ret-row">
                <td><select name="items[0][product_id]" required><option value="">—</option>
                    @foreach($products as $p)<option value="{{ $p->id }}">{{ $p->reference }} — {{ $p->name }}</option>@endforeach
                </select></td>
                <td><input type="number" min="1" name="items[0][quantity]" class="right" value="1" required style="width:80px"></td>
                <td><label class="muted"><input type="checkbox" name="items[0][from_stock]" value="1"> Oui (sortie de stock)</label></td>
                <td><input name="items[0][reason]" placeholder="Motif" style="width:100%"></td>
                <td><button type="button" class="btn btn-sm btn-danger ret-remove">×</button></td>
            </tr>
        </tbody>
    </table></div>
    <button type="button" class="btn btn-sm" id="ret-add">+ Ajouter une ligne</button>
    <p class="muted" style="font-size:.85rem">Un produit déjà entré en stock génère une sortie de stock. Les produits refusés avant réception ne touchent pas le stock.</p>

    <div class="actions mt">
        <button class="btn btn-primary">Déclarer le retour</button>
        <a class="btn" href="{{ route('supplier-returns.index') }}">Annuler</a>
    </div>
</form>
@endsection

@push('scripts')
<script>
(function () {
    const tbody = document.querySelector('#ret-table tbody');
    const first = tbody.querySelector('.ret-row');
    let i = 1;
    document.getElementById('ret-add').addEventListener('click', function () {
        const row = first.cloneNode(true);
        row.querySelectorAll('input,select').forEach(function (el) {
            el.name = el.name.replace(/items\[\d+\]/, 'items[' + i + ']');
            if (el.type === 'number') el.value = 1;
            if (el.type === 'checkbox') el.checked = false;
            if (el.type === 'text' || el.tagName === 'INPUT' && el.type !== 'checkbox' && el.type !== 'number') el.value = '';
        });
        tbody.appendChild(row); i++;
    });
    tbody.addEventListener('click', function (e) {
        if (e.target.classList.contains('ret-remove') && tbody.querySelectorAll('.ret-row').length > 1) {
            e.target.closest('tr').remove();
        }
    });
})();
</script>
@endpush
