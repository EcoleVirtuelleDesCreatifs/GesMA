@extends('layouts.app')
@section('title', 'Retour '.$return->number)
@section('content')
<div class="page-head"><h1>Retour {{ $return->number }} <span class="badge badge-info">{{ $return->statusLabel() }}</span></h1></div>

<div class="grid grid-2 mb">
    <div class="card">
        <div class="card-head"><h2>Retour</h2></div>
        <ul class="list">
            <li><span class="muted">Fournisseur</span><a href="{{ route('suppliers.show', $return->supplier) }}">{{ $return->supplier->name }}</a></li>
            <li><span class="muted">Date</span><span>{{ $return->returned_at->format('d/m/Y') }}</span></li>
            <li><span class="muted">Magasin</span><span>{{ $return->store->name }}</span></li>
            <li><span class="muted">Décision</span><span>{{ $return->decisionLabel() }}</span></li>
            <li><span class="muted">Commande liée</span><span>@if($return->purchaseOrder)<a href="{{ route('purchases.show', $return->purchaseOrder) }}">{{ $return->purchaseOrder->number }}</a>@else — @endif</span></li>
            <li><span class="muted">Réception liée</span><span>@if($return->goodsReceipt)<a href="{{ route('receipts.show', $return->goodsReceipt) }}">{{ $return->goodsReceipt->number }}</a>@else — @endif</span></li>
            <li><span class="muted">Déclaré par</span><span>{{ $return->user?->name ?? '—' }}</span></li>
            @if($return->notes)<li><span class="muted">Notes</span><span>{{ $return->notes }}</span></li>@endif
        </ul>
        @can('supplier_returns.manage')
        <form method="POST" action="{{ route('supplier-returns.status', $return) }}" class="filters" style="margin-top:.6rem">
            @csrf @method('PUT')
            <div class="field"><label>Statut</label>
                <select name="status">@foreach($statuses as $k => $l)<option value="{{ $k }}" @selected($return->status === $k)>{{ $l }}</option>@endforeach</select>
            </div>
            <button class="btn btn-sm">Mettre à jour</button>
        </form>
        @endcan
    </div>
    <div class="card">
        <div class="card-head"><h2>Produits retournés</h2></div>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Produit</th><th class="right">Quantité</th><th>Sortie de stock</th><th>Motif</th></tr></thead>
            <tbody>
            @foreach($return->items as $item)
                <tr>
                    <td>{{ $item->product->name }} <small class="muted">{{ $item->product->reference }}</small></td>
                    <td class="right">{{ $item->quantity }}</td>
                    <td>@if($item->from_stock)<span class="badge badge-warning">Oui</span>@else<span class="badge badge-muted">Non</span>@endif</td>
                    <td>{{ $item->reason ?? '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    </div>
</div>
@endsection
