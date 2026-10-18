<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $document->typeLabel() }} {{ $document->number }}</title>
    @include('pdf._style')
</head>
<body>
@php($company = config('gesma.company'))
<div class="footer">
    {{ $company['slogan'] }}<br>
    {{ $company['products_line'] }}<br>
    {{ $company['name'] }} — {{ $document->store->name }}, {{ $document->store->address ?? $document->store->city }}
    — Tél. {{ $document->store->phone ?: $company['phone'] }} — {{ $company['website'] }}
</div>

<div class="header">
    <table>
        <tr>
            <td class="logo-cell">@include('pdf._logo')</td>
            <td></td>
            <td class="right" style="vertical-align: top">
                <h1>{{ strtoupper($document->typeLabel()) }}</h1>
                <div style="font-size: 12px; font-weight: bold">N° {{ $document->number }}</div>
                <div class="muted">Date : {{ $document->issued_at->format('d/m/Y') }}</div>
                @if($document->valid_until)<div class="muted">Valable jusqu’au : {{ $document->valid_until->format('d/m/Y') }}</div>@endif
                @if($document->order)<div class="muted">Commande : {{ $document->order->number }}</div>@endif
            </td>
        </tr>
    </table>
</div>

<table style="width:100%">
    <tr>
        <td style="width:55%"></td>
        <td class="box">
            <strong>Client</strong><br>
            {{ $document->customer->name }}<br>
            @if($document->customer->address){{ $document->customer->address }}<br>@endif
            @if($document->customer->city){{ $document->customer->city }}<br>@endif
            @if($document->customer->phone)Tél. {{ $document->customer->phone }}<br>@endif
            @if($document->customer->email){{ $document->customer->email }}@endif
        </td>
    </tr>
</table>

<table class="items" style="margin-top: 16px">
    <thead><tr><th style="width:42%">Désignation</th><th style="width:14%">Réf.</th><th class="right" style="width:10%">Qté</th><th class="right" style="width:16%">Prix unitaire</th><th class="right" style="width:18%">Total</th></tr></thead>
    <tbody>
    @foreach($document->items as $item)
        <tr>
            <td>{{ $item->description }}</td>
            <td class="muted">{{ $item->product_reference }}</td>
            <td class="right">{{ $item->quantity }}</td>
            <td class="right">{{ money($item->unit_price) }}</td>
            <td class="right">{{ money($item->total) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<table class="totals">
    <tr><td>Sous-total</td><td class="right">{{ money($document->subtotal) }}</td></tr>
    @if((float) $document->discount > 0)<tr><td>Remise</td><td class="right">− {{ money($document->discount) }}</td></tr>@endif
    @if((float) $document->delivery_fee > 0)<tr><td>Livraison</td><td class="right">{{ money($document->delivery_fee) }}</td></tr>@endif
    <tr class="grand"><td>TOTAL</td><td class="right">{{ money($document->total) }}</td></tr>
    @unless($document->isQuote())
        <tr><td>Déjà réglé</td><td class="right">{{ money($document->amount_paid) }}</td></tr>
        <tr><td><strong>Reste à payer</strong></td><td class="right"><strong>{{ money(max(0, $document->total - $document->amount_paid)) }}</strong></td></tr>
    @endunless
</table>

@if($document->notes)
    <h2>Conditions et remarques</h2>
    <div class="box">{!! nl2br(e($document->notes)) !!}</div>
@endif

@if($document->isQuote())
    <p class="muted" style="margin-top: 24px">Devis valable jusqu’au {{ $document->valid_until?->format('d/m/Y') }}. Bon pour accord (date, nom et signature du client) :</p>
    <div class="box" style="height: 60px; width: 45%"></div>
@else
    <p class="muted" style="margin-top: 24px">Merci pour votre confiance.</p>
@endif
</body>
</html>
