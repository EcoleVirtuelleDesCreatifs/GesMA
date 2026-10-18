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
</style>
