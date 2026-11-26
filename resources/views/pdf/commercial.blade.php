{{-- Modèle commun des devis et factures (mise en page type « facture pro forma » fournisseur). --}}
@php
    $c = config('gesma.company');
    $primary = config('gesma.colors.primary');
    $accent = config('gesma.colors.accent');
    $dark = config('gesma.colors.dark');
    $image = function (?string $path) {
        $full = $path ? public_path($path) : null;
        if (! $full || ! is_file($full)) {
            return null;
        }
        $mime = str_ends_with(strtolower($full), '.jpg') || str_ends_with(strtolower($full), '.jpeg') ? 'image/jpeg' : 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode(file_get_contents($full));
    };
    $logo = $image($c['logo'] ?? null);
    $stamp = $image($c['stamp'] ?? null);
    $customer = $document->customer;
    $lines = $document->lines;
    $nb = fn ($v) => number_format((float) $v, 0, ',', ' ');
    $rate = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', ' '), '0'), ',').' %';
    $minRows = (int) config('gesma.documents.min_rows', 22);
    $filler = max(0, $minRows - $lines->count()) * 15;
    $words = amount_in_words($document->total);
    $words = mb_strtoupper(mb_substr($words, 0, 1)).mb_substr($words, 1);
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>{{ $title }} {{ $document->number }}</title>
<style>
    @page { margin: 26px 30px 78px 30px; }
    body { font-family: "DejaVu Sans", sans-serif; font-size: 8.6px; color: #1f1f1f; margin: 0; }
    table { border-collapse: collapse; }
    .brand { text-align: center; }
    .brand-name { font-size: 26px; font-weight: bold; color: {{ $primary }}; letter-spacing: .5px; margin-top: 2px; }
    .brand-activity { font-size: 9.5px; font-weight: bold; color: {{ $dark }}; margin-top: 2px; }
    .brand-slogan { font-size: 9px; font-style: italic; color: {{ $accent }}; margin-top: 1px; }
    .rule { border-top: 3px solid {{ $accent }}; margin: 8px 0 14px; }
    .doc-title { font-size: 22px; font-weight: bold; color: #000; letter-spacing: .3px; }
    .customer-name { font-size: 10px; font-weight: bold; text-transform: uppercase; }
    .customer-info { font-size: 8px; color: #444; line-height: 1.4; }
    .ref-box td, .ref-box th { border: 1px solid #000; text-align: center; padding: 5px 6px; }
    .ref-box th { font-size: 8.6px; font-weight: bold; }
    .ref-box td { font-weight: bold; height: 14px; }
    .meta { font-size: 8.8px; line-height: 1.75; padding-left: 26px; }
    table.lines { width: 100%; margin-top: 8px; border: 1px solid #000; }
    table.lines th { border: 1px solid #000; padding: 6px 4px; font-size: 8.4px; font-weight: bold; }
    table.lines td { border-left: 1px solid #000; border-right: 1px solid #000; padding: 1.5px 5px; vertical-align: top; }
    table.lines tr.first td { padding-top: 5px; }
    table.lines tr.filler td { padding: 0; }
    .num { text-align: right; white-space: nowrap; }
    .center { text-align: center; }
    .tax td, .tax th { border: 1px solid #000; padding: 4px 4px; }
    .tax th { text-align: left; font-size: 8.4px; }
    .totals th, .totals td { border: 1px solid #000; padding: 5px 4px; text-align: center; white-space: nowrap; }
    .totals th { font-size: 8.4px; }
    .totals .ttc { font-weight: bold; font-size: 9.5px; }
    .paid td { border: 1px solid #000; padding: 4px 6px; }
    .words { font-weight: bold; font-size: 8.8px; margin-top: 4px; }
    .words .amount { padding-left: 8px; margin-top: 2px; }
    .conditions { margin-top: 18px; font-size: 7.6px; font-weight: bold; line-height: 1.5; }
    .signature { text-align: center; }
    .signature .label { text-decoration: underline; font-size: 9px; }
    .status { font-size: 13px; font-weight: bold; color: #B3261E; border: 2px solid #B3261E; padding: 3px 8px; display: inline-block; }
    footer { position: fixed; bottom: -62px; left: 0; right: 0; height: 52px; text-align: center; font-size: 8.4px; color: #222; }
    footer .bar { border-top: 3px solid {{ $accent }}; margin-bottom: 5px; }
    footer .pages { font-size: 7px; color: #888; }
</style>
</head>
<body>
<footer>
    <div class="bar"></div>
    <strong>{{ $c['name'] }}</strong> — {{ $c['legal'] }}<br>
    {{ $c['address'] }} — Tél : {{ $c['phone'] }} — {{ $c['email'] }}@if (! empty($c['website'])) — {{ $c['website'] }}@endif
    @if (! empty($c['bank']))<br>Compte bancaire : {{ $c['bank'] }}@endif
</footer>

{{-- En-tête : logo, raison sociale, activité --}}
<div class="brand">
    @if ($logo)<img src="{{ $logo }}" style="height:54px">@endif
    <div class="brand-name">{{ $c['name'] }}</div>
    @if (! empty($c['activity']))<div class="brand-activity">{{ $c['activity'] }}</div>@endif
    @if (! empty($c['slogan']))<div class="brand-slogan">{{ $c['slogan'] }}</div>@endif
</div>
<div class="rule"></div>

{{-- Titre du document et client --}}
<table style="width:100%">
    <tr>
        <td style="width:58%;vertical-align:top"><div class="doc-title">{{ $title }}</div></td>
        <td style="width:42%;vertical-align:top;padding-top:16px;padding-left:26px">
            <div class="customer-name">{{ $customer->name }}</div>
            <div class="customer-info">
                @if ($customer->address){{ $customer->address }}@if ($customer->city), {{ $customer->city }}@endif<br>@elseif ($customer->city){{ $customer->city }}<br>@endif
                @if ($customer->phone)Tél : {{ $customer->phone }}@endif
                @if ($customer->email)@if ($customer->phone) · @endif{{ $customer->email }}@endif
            </div>
        </td>
    </tr>
</table>

{{-- Numéro, date, référence / vendeur, validité --}}
<table style="width:100%;margin-top:8px">
    <tr>
        <td style="width:58%;vertical-align:top">
            <table class="ref-box" style="width:100%">
                <tr><th style="width:26%">NUMÉRO</th><th style="width:22%">DATE</th><th>RÉFÉRENCE</th></tr>
                <tr><td>{{ $document->number }}</td><td>{{ $document->issued_at->format('d/m/Y') }}</td><td>{{ $reference ?? '' }}</td></tr>
            </table>
        </td>
        <td style="width:42%;vertical-align:bottom" class="meta">
            @if ($document->user)VENDEUR : <strong>{{ mb_strtoupper($document->user->name) }}</strong><br>@endif
            Magasin : <strong>{{ $document->store->name }}</strong>@if ($document->store->phone) · {{ $document->store->phone }}@endif<br>
            @if (! empty($validity))<strong>{{ $validity }}</strong>@endif
        </td>
    </tr>
</table>

{{-- Lignes --}}
<table class="lines">
    <thead>
        <tr>
            <th style="width:13%">Référence</th>
            <th>Désignation</th>
            <th class="center" style="width:7%">Qté</th>
            <th class="center" style="width:12%">Px unit. HT</th>
            <th class="center" style="width:7%">Remise</th>
            <th class="center" style="width:12%">Px unit. NET</th>
            <th class="center" style="width:13%">Montant HT</th>
        </tr>
    </thead>
    <tbody>
    @foreach ($lines as $i => $line)
        <tr @if ($i === 0) class="first" @endif>
            <td>{{ $line->reference }}</td>
            <td>{{ $line->designation }}</td>
            <td class="num">{{ $nb($line->quantity) }}</td>
            <td class="num">{{ $nb($line->unit_price) }}</td>
            <td class="center">{{ $line->discount_rate > 0 ? $rate($line->discount_rate) : '' }}</td>
            <td class="num">{{ $nb($line->netUnitPrice()) }}</td>
            <td class="num">{{ $nb($line->line_total) }}</td>
        </tr>
    @endforeach
    @if ($filler > 0)
        <tr class="filler"><td style="height:{{ $filler }}px"></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
    @endif
    </tbody>
</table>

{{-- Taxes et totaux --}}
<table style="width:100%;margin-top:6px;page-break-inside:avoid">
    <tr>
        <td style="width:34%;vertical-align:top">
            <table class="tax" style="width:100%">
                <tr><th>Code</th><th>Base</th><th>Taux</th><th>Montant</th></tr>
                <tr><td style="height:52px"></td><td></td><td></td><td></td></tr>
                <tr><th>Total</th><td class="center">0</td><td></td><td class="num">0</td></tr>
            </table>
            {{-- Montant en lettres : toujours égal au TOTAL TTC --}}
            <div class="words" style="margin-top:6px">
                {{ $arrete }} à la somme de :
                <div class="amount">{{ $words }} FRANCS CFA</div>
            </div>
        </td>
        <td style="width:6%"></td>
        <td style="width:60%;vertical-align:top">
            <table class="totals" style="width:100%">
                <tr><th>Total HT Brut</th><th>Total Remise</th><th>Total HT Net</th><th>Livraison</th><th>TOTAL TTC</th></tr>
                <tr>
                    <td>{{ $nb($document->grossTotal()) }}</td>
                    <td>{{ $nb($document->totalDiscount()) }}</td>
                    <td>{{ $nb($document->netTotal()) }}</td>
                    <td>{{ $nb($document->delivery_fee) }}</td>
                    <td class="ttc">{{ $nb($document->total) }}</td>
                </tr>
            </table>
            @if (isset($paid))
                <table class="paid" style="width:60%;margin-left:40%;margin-top:4px">
                    <tr><td>Déjà payé</td><td class="num">{{ $nb($paid) }}</td></tr>
                    <tr><td><strong>Reste à payer</strong></td><td class="num"><strong>{{ $nb(max(0, $document->total - $paid)) }}</strong></td></tr>
                </table>
            @endif
            <div class="signature" style="margin-top:10px">
                <div class="label">{{ config('gesma.documents.signature_label') }}</div>
                @if ($stamp)<img src="{{ $stamp }}" style="max-height:90px;max-width:230px;margin-top:4px">@else<div style="height:60px"></div>@endif
            </div>
        </td>
    </tr>
</table>

@if (! empty($statusNote))<div style="margin-top:10px"><span class="status">{{ $statusNote }}</span></div>@endif

<div class="conditions" style="width:60%">
    @php $n = 0; @endphp
    @foreach (array_filter((array) $conditions) as $label => $text)
        @php $n++; @endphp
        {{ $n }}- {{ $label }} :<br>
        <span style="font-weight:normal">{{ $text }}</span><br>
    @endforeach
    @if ($document->notes)
        <div style="margin-top:6px">Notes :<br><span style="font-weight:normal">{!! nl2br(e($document->notes)) !!}</span></div>
    @endif
</div>

<script type="text/php">
    if (isset($pdf)) {
        $font = $fontMetrics->get_font("DejaVu Sans", "normal");
        $pdf->page_text($pdf->get_width() - 80, $pdf->get_height() - 18, "Page {PAGE_NUM} / {PAGE_COUNT}", $font, 7, [0.5, 0.5, 0.5]);
    }
</script>
</body>
</html>
