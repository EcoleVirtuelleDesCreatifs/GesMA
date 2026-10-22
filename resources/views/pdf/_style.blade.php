@php($primary = config('gesma.company.primary_color'))
@php($accent = config('gesma.company.accent_color'))
<style>
    @page { margin: 28px 32px 72px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #23283A; }
    h1 { font-size: 20px; color: {{ $primary }}; margin: 0; }
    h2 { font-size: 13px; color: {{ $primary }}; margin: 16px 0 6px; }
    .header { border-bottom: 3px solid {{ $accent }}; padding-bottom: 10px; margin-bottom: 14px; }
    .header-sub { font-size: 8.5px; color: #6E7488; margin-top: 6px; }
    .header table { width: 100%; }
    .brand { font-size: 22px; font-weight: bold; color: {{ $primary }}; }
    .logo { width: 150px; }
    .header td.logo-cell { width: 165px; vertical-align: middle; padding-right: 10px; }
    .brand small { display: block; font-size: 10px; color: #6E7488; font-weight: normal; }
    .muted { color: #6E7488; }
    .right { text-align: right; }
    .center { text-align: center; }
    table.items { width: 100%; border-collapse: collapse; margin-top: 8px; table-layout: fixed; }
    table.items th { background: {{ $primary }}; color: #fff; padding: 6px; font-size: 9.5px; text-align: left; }
    table.items td { padding: 5px 6px; border-bottom: 1px solid #E4E7F0; }
    table.items tr:nth-child(even) td { background: #F7F8FC; }
    table.items tfoot td { font-weight: bold; background: #EAEDF8; border-top: 2px solid {{ $primary }}; }
    .box { border: 1px solid #E4E7F0; padding: 8px 10px; border-radius: 4px; }
    .totals { width: 260px; margin-left: auto; margin-top: 10px; border-collapse: collapse; }
    .totals td { padding: 4px 6px; }
    .totals .grand td { font-size: 13px; font-weight: bold; color: #fff; background: {{ $primary }}; }
    .kpis { width: 100%; border-collapse: separate; border-spacing: 6px; margin: 0 -6px; }
    .kpis td { border: 1px solid #E4E7F0; border-top: 3px solid {{ $accent }}; padding: 8px; width: 25%; }
    .kpis .v { font-size: 14px; font-weight: bold; }
    .footer { position: fixed; bottom: -58px; left: 0; right: 0; font-size: 8.5px; color: #6E7488; text-align: center; border-top: 1px solid #E4E7F0; padding-top: 4px; line-height: 1.4; }
    .badge { padding: 1px 5px; border-radius: 6px; background: #EBEDF4; font-size: 8.5px; }

    /* Design "MOBILIER GROUP'S" pour devis/factures */
    .mg-navy { color: #1F3864; }
    .mg-logo { display: inline-block; background: #1F3864; color: #fff; font-size: 20px; font-weight: bold; padding: 6px 9px; border-radius: 6px; }
    .mg-name { font-size: 16.5px; font-weight: bold; color: #1F3864; }
    .mg-legal { font-size: 8.5px; font-weight: bold; color: #1F3864; }
    .mg-rule { border-top: 1.5px solid #1F3864; margin: 6px 0 5px; }
    .mg-slogan { text-align: center; font-size: 7.5px; font-weight: bold; color: #1F3864; margin-bottom: 10px; }
    .mg-client { border: 0.8px solid #9FB2D8; border-radius: 4px; padding: 8px 10px; width: 52%; font-size: 9px; }
    .mg-client .t { font-size: 8.5px; font-weight: bold; color: #1F3864; margin-bottom: 4px; }
    .mg-client .line { border-bottom: 0.7px solid #9FB2D8; margin: 9px 0 2px; }
    .mg-title { font-size: 30px; font-weight: bold; color: #1F3864; text-align: right; }
    .mg-meta { text-align: right; font-size: 9px; margin-top: 4px; }
    .mg-meta .num { font-weight: bold; color: #1F3864; font-size: 10px; }
    .mg-objet { font-size: 9px; margin: 8px 0 6px; }
    .mg-objet .line { display: inline-block; border-bottom: 0.7px solid #9FB2D8; width: 70%; }
    table.mg-items { width: 100%; border-collapse: collapse; table-layout: fixed; }
    table.mg-items th { background: #1F3864; color: #fff; font-size: 8.5px; padding: 6px 8px; text-align: left; border: 0.8px solid #1F3864; }
    table.mg-items td { border: 0.6px solid #C7D2E8; padding: 6px 8px; font-size: 9px; }
    table.mg-items tr.alt td { background: #EEF2FB; }
    table.mg-totals { width: 42%; margin-left: auto; margin-top: 0; border-collapse: collapse; }
    table.mg-totals td { border: 0.6px solid #C7D2E8; padding: 5px 8px; font-size: 9px; }
    table.mg-totals td.lbl { color: #1F3864; font-weight: bold; }
    table.mg-totals td.val { text-align: right; }
    table.mg-totals tr.grand td { background: #1F3864; color: #fff; font-weight: bold; font-size: 10px; border-color: #1F3864; }
    .mg-arret { font-size: 9px; margin: 14px 0 6px; }
    .mg-cond { font-size: 8px; color: #1F3864; margin: 4px 0 14px; }
    .mg-sig { width: 100%; margin-top: 10px; }
    .mg-sig .lbl { text-align: center; font-size: 8.5px; font-weight: bold; color: #1F3864; margin-bottom: 3px; }
    .mg-sig .box { border: 0.8px dashed #1F3864; border-radius: 4px; height: 55px; }
    .mg-paymodes { font-size: 8.5px; margin: 6px 0; }
    .mg-footer { position: fixed; bottom: -58px; left: 0; right: 0; text-align: center; font-size: 7px; color: #6E7488; line-height: 1.5; }
    .mg-footer .sep { border-top: 1px solid #1F3864; margin-bottom: 4px; }
    .mg-footer .legal { color: #1F3864; font-weight: bold; font-size: 7.5px; }
    .mg-band { position: fixed; left: -32px; right: -32px; height: 10px; background: #1F3864; }
    .mg-band-top { top: -28px; }
    .mg-band-bottom { bottom: -72px; }
    .mg-refline { font-size: 9px; margin: 4px 0; }
    .mg-refline .dots { display: inline-block; border-bottom: 0.7px solid #9FB2D8; min-width: 160px; }
    .mg-dateline { text-align: right; font-size: 9px; margin: 6px 0; }
</style>
