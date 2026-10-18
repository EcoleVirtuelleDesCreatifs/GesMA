@extends('layouts.app')
@section('title', $product->exists ? 'Modifier le produit' : 'Nouveau produit')
@section('content')
<div class="page-head"><h1>{{ $product->exists ? 'Modifier « '.$product->name.' »' : 'Nouveau produit' }}</h1></div>

<div class="card" style="max-width: 860px">
    <form method="POST" action="{{ $product->exists ? route('products.update', $product) : route('products.store') }}">
        @csrf
        @if($product->exists) @method('PUT') @endif
        <div class="form-grid">
            <div class="field">
                <label for="reference">Référence *</label>
                <input type="text" name="reference" id="reference" value="{{ old('reference', $product->reference) }}" required maxlength="50">
            </div>
            <div class="field">
                <label for="name">Nom *</label>
                <input type="text" name="name" id="name" value="{{ old('name', $product->name) }}" required>
            </div>
            <div class="field">
                <label for="category_id">Catégorie</label>
                <select name="category_id" id="category_id">
                    <option value="">—</option>
                    @foreach($categories as $c)
                        <option value="{{ $c->id }}" @selected(old('category_id', $product->category_id) == $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="new_category">… ou nouvelle catégorie</label>
                <input type="text" name="new_category" id="new_category" value="{{ old('new_category') }}" placeholder="Ex. : Literie">
            </div>
            <div class="field">
                <label for="price">Prix de vente ({{ config('gesma.company.currency') }}) *</label>
                @php($canPrice = !$product->exists || auth()->user()->hasPermission('products.price'))
                <input type="number" name="price" id="price" min="0" step="1" value="{{ old('price', $product->price ? (int) $product->price : '') }}" required @readonly(!$canPrice)>
                @unless($canPrice)<div class="hint">Seule la Direction peut modifier un prix.</div>@endunless
            </div>
            <div class="field">
                <label>&nbsp;</label>
                <input type="hidden" name="is_available" value="0">
                <label class="checkbox"><input type="checkbox" name="is_available" value="1" @checked(old('is_available', $product->is_available))> Disponible à la vente</label>
                <input type="hidden" name="sync_to_website" value="0">
                <label class="checkbox mt-sm"><input type="checkbox" name="sync_to_website" value="1" @checked(old('sync_to_website', $product->sync_to_website))> Publié sur le site web</label>
            </div>
            <div class="field full">
                <label for="description">Description</label>
                <textarea name="description" id="description" rows="4">{{ old('description', $product->description) }}</textarea>
            </div>
        </div>
        <div class="form-actions">
            <a class="btn" href="{{ $product->exists ? route('products.show', $product) : route('products.index') }}">Annuler</a>
            <button class="btn btn-primary" type="submit">Enregistrer</button>
        </div>
    </form>
</div>
@endsection
