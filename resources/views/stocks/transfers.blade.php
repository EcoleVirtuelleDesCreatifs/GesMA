@extends('layouts.app')
@section('title', 'Transferts')
@section('content')
<div class="page-head">
    <h1>Transferts entre magasins</h1>
    <div class="actions">@can('stock.transfer')<a class="btn btn-primary" href="{{ route('stocks.transfers.create') }}">+ Nouveau transfert</a>@endcan</div>
</div>
<div class="card">
    <div class="table-wrap">
        <table class="table responsive">
            <thead><tr><th>N°</th><th>Date</th><th>Produit</th><th class="right">Qté</th><th>De</th><th>Vers</th><th>Notes</th><th>Par</th></tr></thead>
            <tbody>
            @forelse($transfers as $t)
                <tr>
                    <td data-label="N°" class="nowrap">{{ $t->number }}</td>
                    <td data-label="Date" class="nowrap">{{ $t->created_at->format('d/m/Y H:i') }}</td>
                    <td data-label="Produit"><a href="{{ route('products.show', $t->product_id) }}">{{ $t->product->name }}</a></td>
                    <td data-label="Qté" class="right num"><strong>{{ $t->quantity }}</strong></td>
                    <td data-label="De">{{ $t->fromStore->name }}</td>
                    <td data-label="Vers">{{ $t->toStore->name }}</td>
                    <td data-label="Notes">{{ $t->notes ?? '—' }}</td>
                    <td data-label="Par">{{ $t->user?->name }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="empty">Aucun transfert.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $transfers->links() }}
</div>
@endsection
