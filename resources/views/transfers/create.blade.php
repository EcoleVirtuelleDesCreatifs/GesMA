@extends('layouts.app')
@section('title', 'Nouveau transfert')
@section('content')
@php
    $from = old('from_store_id', $fromStores->first()?->id);
    $options = function ($selected = null) use ($products) {
        $html = '<option value="">— Produit —</option>';
        foreach ($products as $p) {
            $html .= sprintf('<option value="%d" data-stocks=\'%s\' %s>%s — %s</option>', $p->id, e($p->stocks->pluck('quantity', 'store_id')->toJson()), (string) $selected === (string) $p->id ? 'selected' : '', e($p->name), e($p->reference));
        }
        return $html;
    };
@endphp
<div class="page-header"><div><h1>Transfert de stock</h1><p class="subtitle">Les quantités des deux magasins sont mises à jour et le transfert est conservé dans l'historique.</p></div></div>
<form method="post" action="{{ route('transfers.store') }}" class="card" style="max-width:900px">
    @csrf
    <div class="form-grid">
        <x-field name="from_store_id" label="Magasin de départ" type="select" :options="$fromStores->pluck('name', 'id')" :value="$from" required />
        <x-field name="to_store_id" label="Magasin de destination" type="select" :options="$toStores->pluck('name', 'id')" placeholder="— Choisir —" required />
        <x-field name="notes" label="Notes" class="full" placeholder="Transporteur, véhicule, observations…" />
    </div>
    <div class="table-wrap" data-lines data-template="#tr-template" data-store-select="#f_from_store_id">
        <table class="table lines-table">
            <thead><tr><th>Produit</th><th class="col-qty">Quantité</th><th class="col-del"></th></tr></thead>
            <tbody>
            @foreach (old('items', [['product_id' => '', 'quantity' => 1]]) as $i => $item)
                <tr>
                    <td class="col-product"><select name="items[{{ $i }}][product_id]" data-product required>{!! $options($item['product_id'] ?? null) !!}</select><div data-stock-hint class="stock-hint"></div></td>
                    <td class="col-qty"><input type="number" min="1" name="items[{{ $i }}][quantity]" value="{{ $item['quantity'] ?? 1 }}" data-qty required></td>
                    <td class="col-del"><button class="btn btn-ghost btn-sm" type="button" data-remove-line><x-icon name="x" /></button></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <template id="tr-template"><tr><td class="col-product"><select name="items[__i__][product_id]" data-product required>{!! $options() !!}</select><div data-stock-hint class="stock-hint"></div></td><td class="col-qty"><input type="number" min="1" name="items[__i__][quantity]" value="1" data-qty required></td><td class="col-del"><button class="btn btn-ghost btn-sm" type="button" data-remove-line><x-icon name="x" /></button></td></tr></template>
    <p><button class="btn btn-secondary btn-sm" type="button" data-add-line><x-icon name="plus" /> Ajouter un produit</button></p>
    <div class="form-actions"><a class="btn btn-secondary" href="{{ route('transfers.index') }}">Annuler</a><button class="btn" type="submit">Effectuer le transfert</button></div>
</form>
@endsection
