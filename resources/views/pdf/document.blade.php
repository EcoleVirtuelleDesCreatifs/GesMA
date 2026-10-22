<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $document->typeLabel() }} {{ $document->number }}</title>
    @include('pdf._style')
</head>
<body>
@php
    $id = config('gesma.documents');
    $isQuote = $document->isQuote();
    // DEV-2026-0001 -> DV-0001/2026 ; FAC-2026-0001 -> FA-0001/2026
    $parts = explode('-', $document->number);
    $docNum = ($isQuote ? 'DV' : 'FA').'-'.($parts[2] ?? $document->number).'/'.($parts[1] ?? $document->issued_at->format('Y'));
    $validity = $document->valid_until ? $document->issued_at->diffInDays($document->valid_until) : $id['quote_validity_days'];
    $payMethod = $document->order?->payment_method;
    $modes = ['especes' => 'Espèces', 'mobile_money' => 'Mobile Money', 'cheque' => 'Chèque', 'virement' => 'Virement'];
@endphp

@include('pdf._letterhead')

<table style="width:100%; margin-bottom:8px">
    <tr>
        <td style="width:55%; vertical-align:top">
            <div class="mg-client">
                <div class="t">CLIENT</div>
                <div>Nom : {{ $document->customer->name ?: ' ' }}@unless($document->customer->name)<div class="line"></div>@endunless</div>
                <div>Adresse : {{ $document->customer->address ?? $document->customer->city ?? ' ' }}@unless($document->customer->address || $document->customer->city)<div class="line"></div>@endunless</div>
                <div>Tél : {{ $document->customer->phone ?? ' ' }}@unless($isQuote) <span style="margin-left:14px">N° CC : ________</span>@endunless</div>
            </div>
        </td>
        <td style="vertical-align:top">
            <div class="mg-title">{{ strtoupper($document->typeLabel()) }}</div>
            <div class="mg-meta">
                <div class="num">N° : {{ $docNum }}</div>
                <div>Date : {{ $document->issued_at->format('d/m/Y') }}</div>
                @if($isQuote)<div>Validité : {{ $validity }} jours</div>@endif
            </div>
        </td>
    </tr>
</table>

@if($isQuote)
<div class="mg-objet">Objet : <span class="line"></span></div>
@endif

<table class="mg-items">
    <thead>
        <tr>
            <th style="width:7%">N°</th>
            <th style="width:45%">Désignation</th>
            <th class="center" style="width:10%">Qté</th>
            <th class="right" style="width:18%">P.U. (F CFA)</th>
            <th class="right" style="width:20%">Montant (F CFA)</th>
        </tr>
    </thead>
    <tbody>
        @foreach($document->items as $item)
        <tr class="{{ $loop->even ? 'alt' : '' }}">
            <td class="center">{{ $loop->iteration }}</td>
            <td>{{ $item->description }}@if($item->product_reference) <span class="muted">({{ $item->product_reference }})</span>@endif</td>
            <td class="center">{{ $item->quantity }}</td>
            <td class="right">{{ money($item->unit_price, false) }}</td>
            <td class="right">{{ money($item->total, false) }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

<table class="mg-totals">
    <tr><td class="lbl">Sous-total</td><td class="val">{{ money($document->subtotal, false) }}</td></tr>
    @if((float) $document->discount > 0)<tr><td class="lbl">Remise</td><td class="val">− {{ money($document->discount, false) }}</td></tr>@endif
    @if((float) $document->delivery_fee > 0)<tr><td class="lbl">Livraison</td><td class="val">{{ money($document->delivery_fee, false) }}</td></tr>@endif
    @if($isQuote)
        <tr class="grand"><td>TOTAL DEVIS</td><td class="val">{{ money($document->total, false) }}</td></tr>
    @else
        @if((float) $document->amount_paid > 0)<tr><td class="lbl">Déjà réglé</td><td class="val">− {{ money($document->amount_paid, false) }}</td></tr>@endif
        <tr class="grand"><td>NET À PAYER</td><td class="val">{{ money(max(0, $document->total - $document->amount_paid), false) }}</td></tr>
    @endif
</table>

<div class="mg-arret">
    {{ $isQuote ? 'Arrêté le présent devis' : 'Arrêtée la présente facture' }} à la somme de :
    <strong class="mg-navy">{{ montant_lettres($document->total) }}</strong> francs CFA.
</div>

@if($isQuote)
    <div class="mg-cond">Conditions : acompte de ____ % à la commande, solde à la livraison. Délai de livraison : ________________.</div>
@else
    <div class="mg-paymodes">
        <strong class="mg-navy">Mode de règlement</strong><br>
        @foreach($modes as $key => $label)
            {{ $payMethod === $key ? '☒' : '☐' }} {{ $label }}&nbsp;&nbsp;
        @endforeach
    </div>
@endif

@if($document->notes)
    <div class="mg-cond" style="color:#23283A">{!! nl2br(e($document->notes)) !!}</div>
@endif

<table class="mg-sig">
    <tr>
        <td style="width:46%; vertical-align:top">
            @if($isQuote)
                <div class="lbl">Bon pour accord (client)</div>
                <div class="box"></div>
            @endif
        </td>
        <td style="width:8%"></td>
        <td style="vertical-align:top">
            <div class="lbl">Signature et cachet</div>
            <div class="box"></div>
        </td>
    </tr>
</table>
</body>
</html>
