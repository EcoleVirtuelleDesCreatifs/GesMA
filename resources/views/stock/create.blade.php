@extends('layouts.app')
@section('title', 'Entrée / sortie de stock')
@section('content')
@php
    $options = function ($selected = null) use ($products) {
        $html = '<option value="">— Produit —</option>';
        foreach ($products as $p) $html .= sprintf('<option value="%d" %s>%s — %s</option>', $p->id, (string) $selected === (string) $p->id ? 'selected' : '', e($p->name), e($p->reference));
        return $html;
    };
    $items = old('items', [['product_id' => $selectedProduct, 'quantity' => 1]]);
@endphp
<div class="page-header"><div><h1>Entrée / sortie de stock</h1><p class="subtitle">Réception de marchandises, retour fournisseur, casse, usage interne…</p></div></div>
<form method="post" action="{{ route('stock.store') }}" class="card" style="max-width:900px">
    @csrf
    <div class="form-grid">
        @include('partials.store-select', ['value' => request('store_id')])
        <x-field name="type" label="Type de mouvement" type="select" :options="['in' => 'Entrée (réception, retour…)', 'out' => 'Sortie (casse, perte, usage interne…)']" value="in" required />
        <x-field name="reason" label="Motif" required class="full" placeholder="Ex. : réception fournisseur BL n° 1234" />
    </div>
    <div class="table-wrap" data-lines data-template="#mv-template">
        <table class="table lines-table">
            <thead><tr><th>Produit</th><th class="col-qty">Quantité</th><th class="col-del"></th></tr></thead>
            <tbody>
            @foreach ($items as $i => $item)
                <tr>
                    <td class="col-product"><select name="items[{{ $i }}][product_id]" required>{!! $options($item['product_id'] ?? null) !!}</select></td>
                    <td class="col-qty"><input type="number" min="1" name="items[{{ $i }}][quantity]" value="{{ $item['quantity'] ?? 1 }}" data-qty required></td>
                    <td class="col-del"><button class="btn btn-ghost btn-sm" type="button" data-remove-line><x-icon name="x" /></button></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <template id="mv-template"><tr><td class="col-product"><select name="items[__i__][product_id]" required>{!! $options() !!}</select></td><td class="col-qty"><input type="number" min="1" name="items[__i__][quantity]" value="1" data-qty required></td><td class="col-del"><button class="btn btn-ghost btn-sm" type="button" data-remove-line><x-icon name="x" /></button></td></tr></template>
    <p><button class="btn btn-secondary btn-sm" type="button" data-add-line><x-icon name="plus" /> Ajouter un produit</button></p>
    <div class="form-actions"><a class="btn btn-secondary" href="{{ route('stock.index') }}">Annuler</a><button class="btn" type="submit">Enregistrer</button></div>
</form>
@endsection
