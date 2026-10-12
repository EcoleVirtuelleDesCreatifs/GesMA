@extends('layouts.app')
@section('title', 'Mouvements de stock')
@section('content')
<div class="page-header">
    <div><h1>Mouvements de stock</h1><p class="subtitle">Entrées, sorties, ventes, retours, corrections et transferts</p></div>
    <div class="actions">@can('stock.manage')<a class="btn" href="{{ route('stock.create') }}"><x-icon name="plus" /> Entrée / sortie</a>@endcan</div>
</div>
<form method="get" class="filters">
    <div class="field"><label for="type">Type</label><select name="type" id="type"><option value="">Tous</option>@foreach (\App\Enums\StockMovementType::options() as $v => $l)<option value="{{ $v }}" @selected(request('type') === $v)>{{ $l }}</option>@endforeach</select></div>
    @include('partials.store-filter')
    <div class="field"><label for="from">Du</label><input type="date" name="from" id="from" value="{{ request('from') }}"></div>
    <div class="field"><label for="to">Au</label><input type="date" name="to" id="to" value="{{ request('to') }}"></div>
    @if (request('product_id'))<input type="hidden" name="product_id" value="{{ request('product_id') }}">@endif
    <button class="btn btn-secondary" type="submit">Filtrer</button>
</form>
<div class="card flush">
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Date</th><th>Produit</th><th>Magasin</th><th>Type</th><th class="num">Qté</th><th class="num">Solde</th><th>Motif / référence</th><th class="hide-mobile">Par</th></tr></thead>
        <tbody>
        @forelse ($movements as $m)
            <tr>
                <td class="nowrap">{{ $m->created_at->format('d/m/Y H:i') }}</td>
                <td><a href="{{ route('products.show', $m->product_id) }}">{{ $m->product?->name }}</a></td>
                <td>{{ $m->store->name }}</td>
                <td>{!! badge($m->type) !!}</td>
                <td class="num {{ $m->quantity < 0 ? 'text-danger' : 'text-success' }}">{{ $m->quantity > 0 ? '+' : '' }}{{ $m->quantity }}</td>
                <td class="num">{{ $m->balance_after }}</td>
                <td class="small">{{ $m->reason }}</td>
                <td class="hide-mobile small">{{ $m->user?->name ?? 'Système' }}</td>
            </tr>
        @empty
            <tr><td colspan="8" class="empty">Aucun mouvement.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
{{ $movements->links() }}
@endsection
