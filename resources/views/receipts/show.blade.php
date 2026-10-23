@extends('layouts.app')
@section('title', 'Réception '.$receipt->number)
@section('content')
<div class="page-head">
    <h1>Réception {{ $receipt->number }}</h1>
    <div class="actions"><a class="btn" href="{{ route('purchases.show', $receipt->purchaseOrder) }}">Commande {{ $receipt->purchaseOrder->number }}</a></div>
</div>

<div class="grid grid-2 mb">
    <div class="card">
        <ul class="list">
            <li><span class="muted">Fournisseur</span><span>{{ $receipt->supplier->name }}</span></li>
            <li><span class="muted">Magasin destinataire</span><span>{{ $receipt->store->name }}</span></li>
            <li><span class="muted">Validée le</span><span>{{ $receipt->validated_at?->format('d/m/Y H:i') }}</span></li>
            <li><span class="muted">Par</span><span>{{ $receipt->user?->name ?? '—' }}</span></li>
            @if($receipt->notes)<li><span class="muted">Notes</span><span>{{ $receipt->notes }}</span></li>@endif
        </ul>
    </div>
    <div class="card">
        <div class="card-head"><h2>Produits</h2></div>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Produit</th><th class="right">Attendu</th><th class="right">Accepté → stock</th><th class="right">Refusé</th><th>Motif</th></tr></thead>
            <tbody>
            @foreach($receipt->items as $item)
                <tr>
                    <td>{{ $item->product->name }}</td>
                    <td class="right">{{ $item->quantity_expected }}</td>
                    <td class="right"><strong class="num">{{ $item->quantity_received }}</strong></td>
                    <td class="right">@if($item->quantity_rejected)<span class="badge badge-warning">{{ $item->quantity_rejected }}</span>@else 0 @endif</td>
                    <td>{{ $item->defect_reason ?? '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        <p class="muted">Les quantités acceptées ont été ajoutées au stock de {{ $receipt->store->name }} (mouvement « Réception fournisseur »).</p>
    </div>
</div>
@endsection
