@extends('layouts.app')
@section('title', 'Stocks')
@section('content')
<div class="page-head">
    <h1>Stocks par magasin</h1>
    <div class="actions">
        @can('stock.move')<a class="btn" href="{{ route('stocks.movements.create', ['mode' => 'entree']) }}">+ Entrée / sortie</a>@endcan
        @can('stock.transfer')<a class="btn" href="{{ route('stocks.transfers.create') }}">Transfert</a>@endcan
        @can('reports.view')<a class="btn" href="{{ route('reports.pdf', ['type' => 'stocks', 'store_id' => $storeId]) }}">PDF</a>@endcan
    </div>
</div>

<form method="GET" class="filters card">
    <div class="field"><label for="q">Produit</label><input type="search" name="q" id="q" value="{{ request('q') }}" placeholder="Nom ou référence"></div>
    <div class="field">
        <label for="category_id">Catégorie</label>
        <select name="category_id" id="category_id">
            <option value="">Toutes</option>
            @foreach($categories as $c)<option value="{{ $c->id }}" @selected(request('category_id') == $c->id)>{{ $c->name }}</option>@endforeach
        </select>
    </div>
    @include('partials.store-filter')
    <label class="checkbox"><input type="checkbox" name="low" value="1" @checked(request('low'))> Stock faible uniquement</label>
    <button class="btn btn-sm">Filtrer</button>
</form>

<div class="card">
    <div class="table-wrap">
        <table class="table responsive">
            <thead><tr><th>Produit</th><th>Catégorie</th><th>Magasin</th><th class="right">Quantité</th><th class="right">Seuil</th><th></th></tr></thead>
            <tbody>
            @forelse($stocks as $s)
                <tr class="{{ $s->isLow() ? 'row-low' : '' }}">
                    <td data-label="Produit"><a href="{{ route('products.show', $s->product_id) }}">{{ $s->product->name }}</a><br><small class="muted">{{ $s->product->reference }}</small></td>
                    <td data-label="Catégorie">{{ $s->product->category?->name ?? '—' }}</td>
                    <td data-label="Magasin">{{ $s->store->name }}</td>
                    <td data-label="Quantité" class="right num"><strong>{{ $s->quantity }}</strong></td>
                    <td data-label="Seuil" class="right num">{{ $s->alert_threshold }}</td>
                    <td data-label="" class="actions">
                        @if($s->quantity <= 0)<span class="badge badge-danger">Rupture</span>
                        @elseif($s->isLow())<span class="badge badge-warning">Faible</span>@endif
                        @can('stock.move')
                            <a class="btn btn-sm" href="{{ route('stocks.movements.create', ['product_id' => $s->product_id, 'store_id' => $s->store_id, 'mode' => 'entree']) }}">+ Entrée</a>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">Aucun stock trouvé.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $stocks->links() }}
</div>
@endsection
