@extends('layouts.app')
@section('title', 'Réception '.$order->number)
@section('content')
<div class="page-head"><h1>Réceptionner la commande {{ $order->number }}</h1></div>

<div class="card mb">
    <ul class="list">
        <li><span class="muted">Fournisseur</span><span>{{ $order->supplier->name }}</span></li>
        <li><span class="muted">Magasin de la commande</span><span>{{ $order->store->name }}</span></li>
        <li><span class="muted">Statut</span><span class="badge badge-info">{{ $order->statusLabel() }}</span></li>
    </ul>
</div>

<form method="POST" action="{{ route('receipts.store', $order) }}" class="card">
    @csrf
    @if(auth()->user()->isDirection() && $stores->count() > 1)
    <div class="field" style="max-width:320px"><label>Magasin destinataire *</label>
        <select name="store_id">
            @foreach($stores as $s)<option value="{{ $s->id }}" @selected($s->id === $order->store_id)>{{ $s->name }}</option>@endforeach
        </select>
    </div>
    @endif

    <div class="table-wrap"><table class="table">
        <thead><tr><th>Produit</th><th class="right">Reste attendu</th><th class="right">Reçu (accepté)</th><th class="right">Refusé / défectueux</th><th>Motif du refus</th></tr></thead>
        <tbody>
        @foreach($order->items as $item)
            @if($item->remaining() > 0)
            <tr>
                <td>{{ $item->product->name }} <small class="muted">{{ $item->product->reference }}</small></td>
                <td class="right">{{ $item->remaining() }}</td>
                <td><input type="number" min="0" max="{{ $item->remaining() }}" name="items[{{ $item->id }}][received]" class="right" value="{{ $item->remaining() }}" style="width:90px"></td>
                <td><input type="number" min="0" max="{{ $item->remaining() }}" name="items[{{ $item->id }}][rejected]" class="right" value="0" style="width:90px"></td>
                <td><input name="items[{{ $item->id }}][defect_reason]" placeholder="Ex : emballage déchiré" style="width:100%"></td>
            </tr>
            @endif
        @endforeach
        </tbody>
    </table></div>
    <div class="field"><label>Notes de réception</label><textarea name="notes" rows="2"></textarea></div>

    <div class="actions mt">
        <button class="btn btn-primary">Valider la réception (entrée en stock)</button>
        <a class="btn" href="{{ route('purchases.show', $order) }}">Annuler</a>
    </div>
</form>
@endsection
