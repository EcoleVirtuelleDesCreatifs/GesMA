@extends('layouts.app')
@section('title', 'Produits')
@section('content')
<div class="page-header">
    <div><h1>Produits</h1><p class="subtitle">{{ $products->total() }} produit(s) au catalogue</p></div>
    <div class="actions">
        @can('products.manage')
            <a class="btn btn-secondary" href="{{ route('categories.index') }}"><x-icon name="tag" /> Catégories</a>
            <a class="btn" href="{{ route('products.create') }}"><x-icon name="plus" /> Nouveau produit</a>
        @endcan
    </div>
</div>
<form method="get" class="filters">
    <div class="field grow"><label for="q">Recherche</label><input type="search" id="q" name="q" value="{{ request('q') }}" placeholder="Nom ou référence"></div>
    <div class="field"><label for="category_id">Catégorie</label><select id="category_id" name="category_id"><option value="">Toutes</option>@foreach ($categories as $c)<option value="{{ $c->id }}" @selected(request('category_id') == $c->id)>{{ $c->name }}</option>@endforeach</select></div>
    <div class="field"><label for="status">Disponibilité</label><select id="status" name="status"><option value="">Toutes</option>@foreach (\App\Enums\ProductStatus::options() as $v => $l)<option value="{{ $v }}" @selected(request('status') === $v)>{{ $l }}</option>@endforeach</select></div>
    @include('partials.store-filter')
    <button class="btn btn-secondary" type="submit"><x-icon name="search" /> Filtrer</button>
</form>
<div class="card flush">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Produit</th><th class="hide-mobile">Catégorie</th><th class="num">Prix</th><th>Disponibilité</th><th class="num">Stock {{ $storeId ? '(magasin)' : (auth()->user()->isDirection() ? '(tous magasins)' : '') }}</th></tr></thead>
            <tbody>
            @forelse ($products as $p)
                @php $qty = $p->quantityIn($storeId); @endphp
                <tr>
                    <td>
                        <div style="display:flex;gap:.6rem;align-items:center">
                            @if ($p->image_path)<img class="product-thumb" src="{{ asset('storage/'.$p->image_path) }}" alt="">@endif
                            <div><a class="strong" href="{{ route('products.show', $p) }}">{{ $p->name }}</a><div class="muted small mono">{{ $p->reference }}</div></div>
                        </div>
                    </td>
                    <td class="hide-mobile">{{ $p->category?->name }}</td>
                    <td class="num">{{ money($p->price) }}</td>
                    <td>{!! badge($p->status) !!}</td>
                    <td class="num {{ $qty <= $p->low_stock_threshold ? 'text-danger strong' : '' }}">{{ $qty }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">Aucun produit.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
{{ $products->links() }}
@endsection
