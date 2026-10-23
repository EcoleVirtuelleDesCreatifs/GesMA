@extends('layouts.app')
@section('title', 'Réceptions')
@section('content')
<div class="page-head"><h1>Réceptions fournisseurs</h1></div>

<div class="card mb">
    <div class="card-head"><h2>Commandes à réceptionner</h2></div>
    <ul class="list">
        @forelse($openOrders as $o)
            <li>
                <a href="{{ route('purchases.show', $o) }}">{{ $o->number }} — {{ $o->supplier->name }}</a>
                <span>
                    <span class="badge badge-warning">{{ $o->statusLabel() }}</span>
                    @can('receipts.manage')<a class="btn btn-sm btn-primary" href="{{ route('receipts.create', $o) }}">Réceptionner</a>@endcan
                </span>
            </li>
        @empty <p class="empty">Aucune commande en attente de livraison.</p> @endforelse
    </ul>
</div>

<div class="card">
    <div class="card-head"><h2>Historique des réceptions</h2></div>
    <div class="table-wrap"><table class="table responsive">
        <thead><tr><th>N°</th><th>Commande</th><th>Fournisseur</th><th>Magasin</th><th>Date</th><th>Par</th><th></th></tr></thead>
        <tbody>
        @forelse($receipts as $r)
            <tr>
                <td data-label="N°"><a href="{{ route('receipts.show', $r) }}"><strong>{{ $r->number }}</strong></a></td>
                <td data-label="Commande"><a href="{{ route('purchases.show', $r->purchaseOrder) }}">{{ $r->purchaseOrder->number }}</a></td>
                <td data-label="Fournisseur">{{ $r->supplier->name }}</td>
                <td data-label="Magasin">{{ $r->store->name }}</td>
                <td data-label="Date" class="nowrap">{{ $r->created_at->format('d/m/Y H:i') }}</td>
                <td data-label="Par">{{ $r->user?->name ?? '—' }}</td>
                <td data-label="" class="actions"><a class="btn btn-sm" href="{{ route('receipts.show', $r) }}">Voir</a></td>
            </tr>
        @empty
            <tr><td colspan="7" class="empty">Aucune réception enregistrée.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    {{ $receipts->links() }}
</div>
@endsection
