<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Bon de commande {{ $order->number }}</title>
    @include('pdf._style')
</head>
<body>
@include('pdf._letterhead')

<table style="width:100%; margin-bottom:8px">
    <tr>
        <td style="width:55%; vertical-align:top">
            <div class="mg-client">
                <div class="t">FOURNISSEUR</div>
                <div class="mg-f">Nom : <span class="fl">{{ $order->supplier->name }}</span></div>
                <div class="mg-f">Adresse : <span class="fl">{{ collect([$order->supplier->address, $order->supplier->city])->filter()->implode(', ') }}</span></div>
                <div class="mg-f">Tél : <span class="fl">{{ $order->supplier->phone }}</span>@if($order->supplier->tax_id) <span style="margin-left:14px">N° CC : <span class="fl">{{ $order->supplier->tax_id }}</span></span>@endif</div>
            </div>
        </td>
        <td style="vertical-align:top">
            <div class="mg-title">BON DE COMMANDE</div>
            <div class="mg-meta">
                <div class="num">N° : {{ $order->number }}</div>
                <div>Date : {{ $order->ordered_at?->format('d/m/Y') ?? $order->created_at->format('d/m/Y') }}</div>
                @if($order->expected_at)<div>Livraison prévue : {{ $order->expected_at->format('d/m/Y') }}</div>@endif
                <div>Magasin : {{ $order->store->name }}</div>
            </div>
        </td>
    </tr>
</table>

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
        @php($fixedRows = 9)
        @foreach($order->items as $item)
        <tr class="{{ $loop->odd ? 'alt' : '' }}">
            <td class="center">{{ $loop->iteration }}</td>
            <td>{{ $item->product->name }} <span class="muted">({{ $item->product->reference }})</span></td>
            <td class="center">{{ $item->quantity }}</td>
            <td class="right">{{ money($item->unit_price, false) }}</td>
            <td class="right">{{ money($item->total, false) }}</td>
        </tr>
        @endforeach
        @for($i = $order->items->count(); $i < $fixedRows; $i++)
        <tr class="{{ ($i + 1) % 2 === 1 ? 'alt' : '' }}">
            <td class="center muted">{{ $i + 1 }}</td>
            <td>&nbsp;</td><td></td><td></td><td></td>
        </tr>
        @endfor
    </tbody>
</table>

<table class="mg-totals">
    <tr><td class="lbl">Sous-total</td><td class="val">{{ money($order->subtotal, false) }}</td></tr>
    <tr><td class="lbl">Remise</td><td class="val">@if((float) $order->discount > 0)− {{ money($order->discount, false) }}@else{{ ' ______' }}@endif</td></tr>
    @if((float) $order->tax_amount > 0)<tr><td class="lbl">Taxes ({{ (float) $order->tax_rate }} %)</td><td class="val">{{ money($order->tax_amount, false) }}</td></tr>@endif
    @if((float) $order->extra_fees > 0)<tr><td class="lbl">Frais complémentaires</td><td class="val">{{ money($order->extra_fees, false) }}</td></tr>@endif
    <tr class="grand"><td>TOTAL COMMANDE</td><td class="val">{{ money($order->total, false) }}</td></tr>
</table>

<div class="mg-arret">
    Arrêtée la présente commande à la somme de : <strong class="mg-navy">{{ montant_lettres($order->total) }}</strong> francs CFA.
</div>

@if($order->notes)<div class="mg-cond" style="color:#23283A">Observations : {{ $order->notes }}</div>@endif
@if($order->supplier->payment_terms)<div class="mg-cond">Conditions de règlement : {{ $order->supplier->payment_terms }}</div>@endif

<table class="mg-sig">
    <tr>
        <td style="width:46%; vertical-align:top">
            <div class="lbl">Signature fournisseur</div>
            <div class="box"></div>
        </td>
        <td style="width:8%"></td>
        <td style="vertical-align:top">
            <div class="lbl">Signature et cachet</div>
            <div class="box" style="text-align:center; padding-top:6px">
                <img src="{{ public_path('img/cachet.png') }}" style="width:170px; opacity:0.9">
            </div>
        </td>
    </tr>
</table>
</body>
</html>
