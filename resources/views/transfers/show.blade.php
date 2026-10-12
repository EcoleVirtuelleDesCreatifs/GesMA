@extends('layouts.app')
@section('title', 'Transfert '.$transfer->number)
@section('content')
<div class="page-header"><div><h1>Transfert <span class="mono">{{ $transfer->number }}</span></h1><p class="subtitle">{{ $transfer->transferred_at->format('d/m/Y à H:i') }} · par {{ $transfer->user?->name }}</p></div><div class="actions"><button class="btn btn-secondary" onclick="window.print()"><x-icon name="print" /> Imprimer</button></div></div>
<div class="grid grid-2-1">
    <div class="card flush">
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Produit</th><th>Référence</th><th class="num">Quantité</th></tr></thead>
            <tbody>@foreach ($transfer->items as $i)<tr><td>{{ $i->product?->name }}</td><td class="mono">{{ $i->product?->reference }}</td><td class="num">{{ $i->quantity }}</td></tr>@endforeach</tbody>
            <tfoot><tr><td colspan="2">Total</td><td class="num">{{ $transfer->items->sum('quantity') }}</td></tr></tfoot>
        </table></div>
    </div>
    <div class="card">
        <dl class="details">
            <dt>Départ</dt><dd>{{ $transfer->fromStore->name }} ({{ $transfer->fromStore->city }})</dd>
            <dt>Destination</dt><dd>{{ $transfer->toStore->name }} ({{ $transfer->toStore->city }})</dd>
            @if ($transfer->notes)<dt>Notes</dt><dd>{{ $transfer->notes }}</dd>@endif
        </dl>
    </div>
</div>
@endsection
