@extends('pdf.layout')
@section('title', $kind.' '.$document->number)
@section('content')
@php $customer = $document->customer; @endphp
<table style="width:100%;border-collapse:collapse">
    <tr>
        <td style="width:55%;vertical-align:top">
            <h1>{{ mb_strtoupper($kind) }}</h1>
            <div style="font-size:11px"><strong>N° {{ $document->number }}</strong></div>
            <div class="muted">Date : {{ $document->issued_at->format('d/m/Y') }}</div>
            @isset($validity)<div class="muted">{{ $validity }}</div>@endisset
            <div class="muted">Magasin : {{ $document->store->name }} ({{ $document->store->city }})@if($document->store->phone) · {{ $document->store->phone }}@endif</div>
            @if ($document->user)<div class="muted">Conseiller : {{ $document->user->name }}</div>@endif
            @isset($reference)<div class="muted">{{ $reference }}</div>@endisset
        </td>
        <td style="width:45%;vertical-align:top">
            <div class="box">
                <div class="muted small" style="text-transform:uppercase">Client</div>
                <div style="font-size:11px;font-weight:bold;margin:2px 0">{{ $customer->name }}</div>
                @if ($customer->address){{ $customer->address }}<br>@endif
                @if ($customer->city){{ $customer->city }}<br>@endif
                @if ($customer->phone)Tél. {{ $customer->phone }}<br>@endif
                @if ($customer->email){{ $customer->email }}@endif
            </div>
        </td>
    </tr>
</table>

<table class="grid" style="margin-top:18px">
    <thead><tr><th style="width:5%">#</th><th>Désignation</th><th style="width:14%">Réf.</th><th class="num" style="width:8%">Qté</th><th class="num" style="width:16%">Prix unitaire</th><th class="num" style="width:17%">Total</th></tr></thead>
    <tbody>
    @foreach ($document->lines as $i => $line)
        <tr>
            <td>{{ $i + 1 }}</td>
            <td>{{ $line->designation }}</td>
            <td class="small">{{ $line->reference }}</td>
            <td class="num">{{ $line->quantity }}</td>
            <td class="num">{{ money($line->unit_price) }}</td>
            <td class="num">{{ money($line->line_total) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<table class="totals">
    <tr><td>Sous-total</td><td class="num">{{ money($document->subtotal) }}</td></tr>
    @if ($document->discount)<tr><td>Remise</td><td class="num">- {{ money($document->discount) }}</td></tr>@endif
    @if ($document->delivery_fee)<tr><td>Frais de livraison</td><td class="num">{{ money($document->delivery_fee) }}</td></tr>@endif
    <tr class="grand"><td>TOTAL</td><td class="num">{{ money($document->total) }}</td></tr>
    @isset($paid)
        <tr><td>Déjà payé</td><td class="num">{{ money($paid) }}</td></tr>
        <tr><td><strong>Reste à payer</strong></td><td class="num"><strong>{{ money(max(0, $document->total - $paid)) }}</strong></td></tr>
    @endisset
</table>

<p style="margin-top:14px"><strong>Arrêté le présent document à la somme de {{ money($document->total) }}.</strong></p>
@isset($statusNote)<p class="danger" style="font-size:12px;font-weight:bold">{{ $statusNote }}</p>@endisset
@if ($document->notes)
    <h2>Notes</h2>
    <div>{!! nl2br(e($document->notes)) !!}</div>
@endif
<p class="muted" style="margin-top:20px">{{ $footer }}</p>
<table style="width:100%;margin-top:30px">
    <tr>
        <td style="width:50%">Signature du client<br><br><br>.................................................</td>
        <td style="width:50%;text-align:right">Pour {{ config('gesma.company.name') }}<br><br><br>.................................................</td>
    </tr>
</table>
@endsection
