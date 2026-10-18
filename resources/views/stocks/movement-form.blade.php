@extends('layouts.app')
@section('title', 'Mouvement de stock')
@section('content')
<div class="page-head"><h1>Mouvement de stock</h1></div>
<div class="card" style="max-width: 720px">
    <form method="POST" action="{{ route('stocks.movements.store') }}">
        @csrf
        <div class="field mb">
            <label>Type d’opération</label>
            <div class="radio-cards">
                @can('stock.move')
                    <label><input type="radio" name="mode" value="entree" @checked(old('mode', $mode) === 'entree')><span><strong>Entrée</strong><small class="muted">Réception, achat, réassort</small></span></label>
                    <label><input type="radio" name="mode" value="sortie" @checked(old('mode', $mode) === 'sortie')><span><strong>Sortie</strong><small class="muted">Casse, usage interne…</small></span></label>
                @endcan
                @can('stock.adjust')
                    <label><input type="radio" name="mode" value="correction" @checked(old('mode', $mode) === 'correction')><span><strong>Correction</strong><small class="muted">Quantité réelle après inventaire</small></span></label>
                @endcan
            </div>
        </div>
        <div class="form-grid">
            <div class="field full">
                <label for="product_id">Produit *</label>
                <select name="product_id" id="product_id" required>
                    <option value="">— Choisir —</option>
                    @foreach($products as $p)
                        <option value="{{ $p->id }}" @selected(old('product_id', $selectedProduct) == $p->id)>{{ $p->name }} ({{ $p->reference }})</option>
                    @endforeach
                </select>
            </div>
            @if(auth()->user()->isDirection())
                <div class="field">
                    <label for="store_id">Magasin *</label>
                    <select name="store_id" id="store_id" required>
                        @foreach($stores as $s)<option value="{{ $s->id }}" @selected(old('store_id', $selectedStore) == $s->id)>{{ $s->name }}</option>@endforeach
                    </select>
                </div>
            @endif
            <div class="field">
                <label for="quantity" data-qty-label>Quantité *</label>
                <input type="number" name="quantity" id="quantity" min="0" value="{{ old('quantity') }}" required>
                <div class="hint" data-correction-hint>Pour une correction, saisissez la quantité réellement comptée.</div>
            </div>
            <div class="field full">
                <label for="reason">Motif <span data-reason-required>*</span></label>
                <input type="text" name="reason" id="reason" value="{{ old('reason') }}" maxlength="255" placeholder="Ex. : Livraison fournisseur du 12/10, inventaire mensuel…">
                <div class="hint">Obligatoire pour une sortie ou une correction (enregistré dans l’historique).</div>
            </div>
        </div>
        <div class="form-actions">
            <a class="btn" href="{{ url()->previous() }}">Annuler</a>
            <button class="btn btn-primary">Enregistrer</button>
        </div>
    </form>
</div>
@endsection
