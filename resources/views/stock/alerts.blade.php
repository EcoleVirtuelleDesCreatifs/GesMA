@extends('layouts.app')
@section('title', 'Alertes de stock')
@section('content')
<div class="page-header">
    <div><h1>Alertes de stock</h1><p class="subtitle">Produits dont la quantité a atteint le seuil d'alerte</p></div>
    <div class="actions">@can('stock.transfer')<a class="btn btn-secondary" href="{{ route('transfers.create') }}"><x-icon name="transfer" /> Transfert</a>@endcan @can('stock.manage')<a class="btn" href="{{ route('stock.create') }}">Réception de stock</a>@endcan</div>
</div>
<form method="get" class="filters">@include('partials.store-filter')<button class="btn btn-secondary" type="submit">Filtrer</button></form>
<div class="card flush">
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Produit</th><th>Magasin</th><th class="num">Quantité</th><th class="num">Seuil</th><th></th></tr></thead>
        <tbody>
        @forelse ($stocks as $s)
            <tr class="{{ $s->quantity <= 0 ? 'row-danger' : 'row-warning' }}">
                <td><a href="{{ route('products.show', $s->product) }}">{{ $s->product->name }}</a><div class="muted small mono">{{ $s->product->reference }}</div></td>
                <td>{{ $s->store->name }}</td>
                <td class="num strong">{{ $s->quantity }}</td>
                <td class="num">{{ $s->threshold() }}</td>
                <td class="text-right">{{ $s->quantity <= 0 ? ($s->quantity < 0 ? 'À régulariser' : 'Rupture') : 'Stock faible' }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="empty">Aucune alerte : tous les stocks sont au-dessus de leur seuil.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
@endsection
