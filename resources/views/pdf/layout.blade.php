@php
    $c = config('gesma.company');
    $primary = config('gesma.colors.primary');
    $accent = config('gesma.colors.accent');
    $dark = config('gesma.colors.dark');
    $logoPath = public_path($c['logo']);
    $logo = is_file($logoPath) ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath)) : null;
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>@yield('title')</title>
<style>
    @page { margin: 110px 36px 70px 36px; }
    body { font-family: "DejaVu Sans", sans-serif; font-size: 9.5px; color: #333; }
    header { position: fixed; top: -90px; left: 0; right: 0; height: 80px; }
    footer { position: fixed; bottom: -50px; left: 0; right: 0; height: 40px; font-size: 8px; color: #777; text-align: center; border-top: 1px solid #e5ddd3; padding-top: 6px; }
    .head-table { width: 100%; border-collapse: collapse; }
    .head-table td { vertical-align: top; }
    .brand-name { font-size: 18px; font-weight: bold; color: {{ $primary }}; letter-spacing: .5px; }
    .brand-sub { color: {{ $accent }}; font-size: 9px; }
    .company { font-size: 8.5px; color: #666; line-height: 1.45; }
    .band { height: 5px; background: {{ $primary }}; margin-top: 6px; }
    .band span { display: block; height: 5px; width: 30%; background: {{ $accent }}; }
    h1 { font-size: 17px; color: {{ $dark }}; margin: 0 0 4px; }
    h2 { font-size: 11.5px; color: {{ $primary }}; margin: 16px 0 6px; text-transform: uppercase; letter-spacing: .4px; }
    .muted { color: #888; }
    table.grid { width: 100%; border-collapse: collapse; margin-top: 4px; }
    table.grid th { background: {{ $primary }}; color: #fff; text-align: left; padding: 5px 6px; font-size: 8.5px; text-transform: uppercase; }
    table.grid td { padding: 5px 6px; border-bottom: 1px solid #eee5da; }
    table.grid th.num { text-align: right; }
    table.grid tr:nth-child(even) td { background: #fbf8f4; }
    table.grid tfoot td { font-weight: bold; background: #f3e7da; border-top: 1px solid {{ $primary }}; }
    .num { text-align: right; white-space: nowrap; }
    .box { border: 1px solid #e5ddd3; border-radius: 4px; padding: 8px 10px; }
    .kpis { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin: 0 -6px; }
    .kpis td { border: 1px solid #e5ddd3; border-left: 3px solid {{ $accent }}; padding: 6px 8px; width: 25%; }
    .kpis .l { font-size: 7.5px; color: #888; text-transform: uppercase; }
    .kpis .v { font-size: 12px; font-weight: bold; color: {{ $dark }}; margin-top: 2px; }
    .danger { color: #B3261E; }
    .success { color: #2E7D4F; }
    .totals { width: 45%; margin-left: 55%; border-collapse: collapse; margin-top: 8px; }
    .totals td { padding: 4px 6px; }
    .totals .grand td { font-size: 12px; font-weight: bold; color: #fff; background: {{ $primary }}; }
    .small { font-size: 8px; }
</style>
</head>
<body>
<header>
    <table class="head-table">
        <tr>
            <td style="width:60px">@if($logo)<img src="{{ $logo }}" style="width:52px;height:52px">@endif</td>
            <td>
                <div class="brand-name">{{ $c['name'] }}</div>
                <div class="brand-sub">{{ $c['slogan'] }}</div>
            </td>
            <td class="company" style="text-align:right">
                @yield('header-right')
                {{ $c['address'] }}<br>Tél. {{ $c['phone'] }} · {{ $c['email'] }}<br>{{ $c['website'] }}
            </td>
        </tr>
    </table>
    <div class="band"><span></span></div>
</header>
<footer>
    {{ $c['name'] }} · {{ $c['legal'] }} · {{ $c['address'] }}<br>
    Document généré par GesMA le {{ now()->format('d/m/Y à H:i') }}
</footer>
<main>
    @yield('content')
</main>
<script type="text/php">
    if (isset($pdf)) {
        $font = $fontMetrics->get_font("DejaVu Sans", "normal");
        $pdf->page_text($pdf->get_width() - 90, $pdf->get_height() - 30, "Page {PAGE_NUM} / {PAGE_COUNT}", $font, 7, [0.5, 0.5, 0.5]);
    }
</script>
</body>
</html>
