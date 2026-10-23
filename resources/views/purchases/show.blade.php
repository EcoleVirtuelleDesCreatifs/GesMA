@extends('layouts.app')
@section('title', 'Commande '.$order->number)
@section('content')
<div class="page-head">
    <h1>Commande {{ $order->number }} <span class="badge badge-info">{{ $order->statusLabel() }}</span></h1>
    <div class="actions">
        <a class="btn" href="{{ route('purchases.pdf', $order) }}">Bon de commande PDF</a>
        @can('receipts.manage')
            @if(in_array($order->status, ['validee', 'envoyee', 'partielle'], true))
                <a class="btn btn-primary" href="{{ route('receipts.create', $order) }}">Réceptionner</a>
            @endif
        @endcan
    </div>
</div>

@php($labels = ['en_attente' => 'Soumettre pour validation', 'validee' => 'Valider', 'envoyee' => 'Marquer envoyée', 'annulee' => 'Annuler'])
@if($transitions)
<div class="card mb">
    <form method="POST" action="{{ route('purchases.status', $order) }}" class="filters">
        @csrf @method('PUT')
        <div class="field"><label>Changer le statut</label>
            <select name="status">
                @foreach($transitions as $t)<option value="{{ $t }}">{{ $labels[$t] ?? config("gesma.purchase_statuses.$t") }}</option>@endforeach
            </select>
        </div>
        <button class="btn btn-sm btn-primary">Appliquer</button>
        @cannot('purchases.validate')<small class="muted">La validation est réservée à la Direction.</small>@endcannot
    </form>
</div>
@endif

<div class="grid grid-2 mb">
    <div class="card">
        <div class="card-head"><h2>Commande</h2></div>
        <ul class="list">
            <li><span class="muted">Fournisseur</span><a href="{{ route('suppliers.show', $order->supplier) }}">{{ $order->supplier->name }}</a></li>
            <li><span class="muted">Magasin destinataire</span><span>{{ $order->store->name }}</span></li>
            <li><span class="muted">Date de commande</span><span>{{ $order->ordered_at?->format('d/m/Y') ?? '—' }}</span></li>
            <li><span class="muted">Livraison prévue</span><span>{{ $order->expected_at?->format('d/m/Y') ?? '—' }}@if($order->isLate()) <span class="badge badge-danger">En retard</span>@endif</span></li>
            <li><span class="muted">Créée par</span><span>{{ $order->user?->name ?? '—' }}</span></li>
            @if($order->notes)<li><span class="muted">Observations</span><span>{{ $order->notes }}</span></li>@endif
        </ul>
    </div>
    <div class="card">
        <div class="card-head"><h2>Montants</h2></div>
        <ul class="list">
            <li><span class="muted">Sous-total</span><span class="num">@money($order->subtotal)</span></li>
            <li><span class="muted">Remise globale</span><span class="num">@money($order->discount)</span></li>
            <li><span class="muted">Taxes ({{ (float) $order->tax_rate }} %)</span><span class="num">@money($order->tax_amount)</span></li>
            <li><span class="muted">Frais complémentaires</span><span class="num">@money($order->extra_fees)</span></li>
            <li><strong>Total</strong><strong class="num">@money($order->total)</strong></li>
        </ul>
    </div>
</div>

<div class="card mb">
    <div class="card-head"><h2>Produits commandés</h2></div>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Produit</th><th class="right">Commandé</th><th class="right">Reçu</th><th class="right">Reste</th><th class="right">P.U.</th><th class="right">Remise</th><th class="right">Total</th></tr></thead>
        <tbody>
        @foreach($order->items as $item)
            <tr>
                <td>{{ $item->product->name }} <small class="muted">{{ $item->product->reference }}</small></td>
                <td class="right">{{ $item->quantity }}</td>
                <td class="right">{{ $item->quantity_received }}</td>
                <td class="right">{{ $item->remaining() }}</td>
                <td class="right num">@money($item->unit_price)</td>
                <td class="right num">@money($item->discount)</td>
                <td class="right num">@money($item->total)</td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
</div>

<div class="grid grid-2">
    <div class="card">
        <div class="card-head"><h2>Réceptions</h2></div>
        <ul class="list">
            @forelse($order->receipts as $r)
                <li><a href="{{ route('receipts.show', $r) }}">{{ $r->number }} <small class="muted">{{ $r->created_at->format('d/m/Y H:i') }} · {{ $r->store->name }}</small></a><span>{{ $r->items->sum('quantity_received') }} reçu(s)@if($r->items->sum('quantity_rejected')) · <span class="badge badge-warning">{{ $r->items->sum('quantity_rejected') }} refusé(s)</span>@endif</span></li>
            @empty <p class="empty">Aucune réception.</p> @endforelse
        </ul>
    </div>
    <div class="card">
        <div class="card-head"><h2>Factures liées</h2></div>
        <ul class="list">
            @forelse($order->invoices as $i)
                <li><a href="{{ route('supplier-invoices.show', $i) }}">{{ $i->number }} <small class="muted">{{ $i->invoiced_at->format('d/m/Y') }}</small></a><span class="num">@money($i->total) — {{ $i->statusLabel() }}</span></li>
            @empty <p class="empty">Aucune facture liée.</p> @endforelse
        </ul>
    </div>
</div>
@endsection
