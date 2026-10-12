@extends('layouts.app')
@section('title', $product->exists ? 'Modifier le produit' : 'Nouveau produit')
@section('content')
@php $canPrice = auth()->user()->hasPermission('products.price') || ! $product->exists; @endphp
<div class="page-header"><h1>{{ $product->exists ? 'Modifier '.$product->name : 'Nouveau produit' }}</h1></div>
<form method="post" enctype="multipart/form-data" action="{{ $product->exists ? route('products.update', $product) : route('products.store') }}">
    @csrf
    @if ($product->exists) @method('PUT') @endif
    <div class="grid grid-2-1">
        <div class="card">
            <h2>Fiche produit</h2>
            <div class="form-grid">
                <x-field name="name" label="Nom" :value="$product->name" required class="full" />
                <x-field name="reference" label="Référence" :value="$product->reference" required help="Identifiant unique, partagé avec le site web." />
                <x-field name="category_id" label="Catégorie" type="select" :options="$categories->pluck('name', 'id')" :value="$product->category_id" placeholder="— Aucune —" />
                <x-field name="price" label="Prix de vente (F CFA)" type="number" min="0" :value="$product->price" required :readonly="! $canPrice" :help="$product->exists ? 'Toute modification de prix est enregistrée dans l\'historique.' : null" />
                <x-field name="cost_price" label="Prix d'achat (F CFA)" type="number" min="0" :value="$product->cost_price" />
                <x-field name="status" label="Disponibilité" type="select" :options="\App\Enums\ProductStatus::options()" :value="$product->status" required />
                <x-field name="low_stock_threshold" label="Seuil d'alerte de stock" type="number" min="0" :value="$product->low_stock_threshold" required help="Alerte lorsque la quantité d'un magasin atteint ce seuil." />
                <x-field name="description" label="Description" type="textarea" :value="$product->description" class="full" rows="4" />
                <x-field name="image" label="Photo" type="file" accept="image/*" />
                <div class="field"><label>&nbsp;</label><label class="checkbox"><input type="hidden" name="sync_with_website" value="0"><input type="checkbox" name="sync_with_website" value="1" @checked(old('sync_with_website', $product->exists ? $product->sync_with_website : true))> Publier sur le site web</label></div>
            </div>
        </div>
        <div>
            @if (! $product->exists && $stores->count())
                <div class="card">
                    <h2>Stock initial</h2>
                    <p class="muted small">Quantités disponibles à la création (enregistrées comme entrées de stock).</p>
                    @foreach ($stores as $s)
                        <x-field :name="'initial_stock['.$s->id.']'" :label="$s->name" type="number" min="0" value="0" />
                    @endforeach
                </div>
            @endif
            @if ($product->image_path)
                <div class="card"><img src="{{ asset('storage/'.$product->image_path) }}" alt="" style="width:100%;border-radius:8px"></div>
            @endif
        </div>
    </div>
    <div class="form-actions">
        <a class="btn btn-secondary" href="{{ $product->exists ? route('products.show', $product) : route('products.index') }}">Annuler</a>
        <button class="btn" type="submit">Enregistrer</button>
    </div>
</form>
@endsection
