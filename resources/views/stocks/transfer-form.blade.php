@extends('layouts.app')
@section('title', 'Nouveau transfert')
@section('content')
<div class="page-head"><h1>Transférer un produit</h1></div>
<div class="card" style="max-width: 720px">
    <form method="POST" action="{{ route('stocks.transfers.store') }}">
        @csrf
        <div class="form-grid">
            <div class="field full">
                <label for="product_id">Produit *</label>
                <select name="product_id" id="product_id" required data-transfer-product>
                    <option value="">— Choisir —</option>
                    @foreach($products as $p)
                        <option value="{{ $p->id }}" data-stocks="{{ json_encode($p->stocks->pluck('quantity', 'store_id')) }}" @selected(old('product_id', $selectedProduct) == $p->id)>{{ $p->name }} ({{ $p->reference }})</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="from_store_id">Magasin de départ *</label>
                <select name="from_store_id" id="from_store_id" required data-transfer-from>
                    @foreach($fromStores as $s)<option value="{{ $s->id }}" @selected(old('from_store_id', auth()->user()->store_id) == $s->id)>{{ $s->name }}</option>@endforeach
                </select>
                <div class="hint" data-transfer-available></div>
            </div>
            <div class="field">
                <label for="to_store_id">Magasin d’arrivée *</label>
                <select name="to_store_id" id="to_store_id" required>
                    <option value="">— Choisir —</option>
                    @foreach($toStores as $s)<option value="{{ $s->id }}" @selected(old('to_store_id') == $s->id)>{{ $s->name }}</option>@endforeach
                </select>
            </div>
            <div class="field">
                <label for="quantity">Quantité *</label>
                <input type="number" name="quantity" id="quantity" min="1" value="{{ old('quantity', 1) }}" required>
            </div>
            <div class="field full">
                <label for="notes">Notes</label>
                <input type="text" name="notes" id="notes" value="{{ old('notes') }}" placeholder="Transporteur, raison du transfert…">
            </div>
        </div>
        <div class="form-actions">
            <a class="btn" href="{{ route('stocks.transfers') }}">Annuler</a>
            <button class="btn btn-primary">Enregistrer le transfert</button>
        </div>
    </form>
</div>
@endsection
