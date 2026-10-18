@extends('layouts.app')
@section('title', $document->typeLabel().' '.$document->number)
@section('content')
<div class="page-head">
    <div>
        <h1>{{ $document->typeLabel() }} {{ $document->number }}</h1>
        <small class="muted">{{ $document->issued_at->format('d/m/Y') }} · {{ $document->store->name }}@if($document->user) · par {{ $document->user->name }}@endif</small>
    </div>
    <div class="actions">
        <a class="btn btn-primary" href="{{ route('documents.pdf', $document) }}">Télécharger le PDF</a>
        @if($document->isQuote())
            @if($invoice)
                <a class="btn" href="{{ route('documents.show', $invoice) }}">Voir la facture {{ $invoice->number }}</a>
            @else
                @can('documents.manage')
                    <form method="POST" action="{{ route('documents.convert', $document) }}" class="inline-form">@csrf<button class="btn btn-accent">Transformer en facture</button></form>
                @endcan
            @endif
        @endif
        @can('documents.delete')
            <form method="POST" action="{{ route('documents.destroy', $document) }}" class="inline-form" onsubmit="return confirm('Supprimer {{ $document->typeLabel() }} {{ $document->number }} ? Cette action est définitive.')">
                @csrf @method('DELETE')
                <button class="btn btn-danger">Supprimer</button>
            </form>
        @endcan
    </div>
</div>
<div class="grid grid-main">
    <div class="card">
        <div class="table-wrap">
            <table class="table responsive">
                <thead><tr><th>Désignation</th><th class="right">Qté</th><th class="right">Prix unitaire</th><th class="right">Total</th></tr></thead>
                <tbody>
                @foreach($document->items as $i)
                    <tr>
                        <td data-label="Désignation">{{ $i->description }}@if($i->product_reference)<br><small class="muted">{{ $i->product_reference }}</small>@endif</td>
                        <td data-label="Qté" class="right num">{{ $i->quantity }}</td>
                        <td data-label="Prix unitaire" class="right num">@money($i->unit_price)</td>
                        <td data-label="Total" class="right num">@money($i->total)</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="totals mt">
            <div><span>Sous-total</span><span class="num">@money($document->subtotal)</span></div>
            @if((float) $document->discount > 0)<div><span>Remise</span><span class="num">− @money($document->discount)</span></div>@endif
            @if((float) $document->delivery_fee > 0)<div><span>Livraison</span><span class="num">@money($document->delivery_fee)</span></div>@endif
            <div class="grand"><span>Total</span><span class="num">@money($document->total)</span></div>
            @unless($document->isQuote())
                <div><span>Réglé</span><span class="num">@money($document->amount_paid)</span></div>
                <div><strong>Reste dû</strong><strong class="num">@money(max(0, $document->total - $document->amount_paid))</strong></div>
            @endunless
        </div>
        @if($document->notes)<p class="mt"><strong>Notes :</strong> {{ $document->notes }}</p>@endif
    </div>
    <div>
        <div class="card">
            <h2>Client</h2>
            <p><a href="{{ route('customers.show', $document->customer) }}"><strong>{{ $document->customer->name }}</strong></a><br>
                {{ $document->customer->phone }}<br><small class="muted">{{ $document->customer->address }} {{ $document->customer->city }}</small></p>
            <dl class="dl">
                @if($document->valid_until)<dt>Valable jusqu’au</dt><dd>{{ $document->valid_until->format('d/m/Y') }}</dd>@endif
                @if($document->order)<dt>Commande</dt><dd><a href="{{ route('orders.show', $document->order) }}">{{ $document->order->number }}</a></dd>@endif
                @if($document->sourceDocument)<dt>Devis d’origine</dt><dd><a href="{{ route('documents.show', $document->sourceDocument) }}">{{ $document->sourceDocument->number }}</a></dd>@endif
            </dl>
        </div>
        @if(!$document->isQuote())
            @can('documents.manage')
            <div class="card">
                <h2>Règlement</h2>
                <form method="POST" action="{{ route('documents.payment', $document) }}">
                    @csrf @method('PUT')
                    <div class="field mb"><label for="amount_paid">Montant total réglé</label><input type="number" min="0" name="amount_paid" id="amount_paid" value="{{ (int) $document->amount_paid }}"></div>
                    <button class="btn btn-block">Mettre à jour</button>
                </form>
            </div>
            @endcan
        @endif
    </div>
</div>
@endsection
