@extends('layouts.app')
@section('title', 'Stocks')
@section('content')
<div class="page-header">
    <div><h1>Stocks par magasin</h1><p class="subtitle">Quantités disponibles produit par produit</p></div>
    <div class="actions">
        <a class="btn btn-secondary" href="{{ route('stock.alerts') }}"><x-icon name="alert" /> Alertes</a>
        <a class="btn btn-secondary" href="{{ route('stock.movements') }}"><x-icon name="history" /> Mouvements</a>
        @can('stock.adjust')<a class="btn btn-secondary" href="{{ route('stock.correction') }}">Inventaire</a>@endcan
        @can('stock.manage')<a class="btn" href="{{ route('stock.create') }}"><x-icon name="plus" /> Entrée / sortie</a>@endcan
    </div>
</div>
<form method="get" class="filters">
    <div class="field grow"><label for="q">Produit</label><input type="search" id="q" name="q" value="{{ request('q') }}" placeholder="Nom ou référence"></div>
    <div class="field"><label for="category_id">Catégorie</label><select id="category_id" name="category_id"><option value="">Toutes</option>@foreach ($categories as $c)<option value="{{ $c->id }}" @selected(request('category_id') == $c->id)>{{ $c->name }}</option>@endforeach</select></div>
    @include('partials.store-filter')
    <button class="btn btn-secondary" type="submit">Filtrer</button>
</form>
<div class="card flush">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Produit</th>
                    @foreach ($visibleStores as $s)<th class="num">{{ $s->name }}</th>@endforeach
                    @if ($visibleStores->count() > 1)<th class="num">Total</th>@endif
                </tr>
            </thead>
            <tbody>
            @forelse ($products as $p)
                @php $total = 0; @endphp
                <tr>
                    <td><a href="{{ route('products.show', $p) }}">{{ $p->name }}</a><div class="muted small mono">{{ $p->reference }} · seuil {{ $p->low_stock_threshold }}</div></td>
                    @foreach ($visibleStores as $s)
                        @php $stock = $p->stocks->firstWhere('store_id', $s->id); $q = $stock?->quantity ?? 0; $total += $q; $threshold = $stock?->low_stock_threshold ?? $p->low_stock_threshold; @endphp
                        <td class="num {{ $q <= 0 ? 'text-danger strong' : ($q <= $threshold ? 'text-warning strong' : '') }}">{{ $q }}</td>
                    @endforeach
                    @if ($visibleStores->count() > 1)<td class="num strong">{{ $total }}</td>@endif
                </tr>
            @empty
                <tr><td colspan="{{ $visibleStores->count() + 2 }}" class="empty">Aucun produit.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
{{ $products->links() }}
@endsection
