@extends('layouts.app')
@section('title', 'Achats — Tableau de bord')
@section('content')
<div class="page-head">
    <h1>Tableau de bord fournisseurs</h1>
    <div class="actions">@can('suppliers.manage')<a class="btn btn-primary" href="{{ route('suppliers.create') }}">+ Fournisseur</a>@endcan</div>
</div>

<div class="grid grid-4 mb">
    <div class="kpi brand"><div class="label">Fournisseurs actifs</div><div class="value">{{ $activeSuppliers }}</div></div>
    <div class="kpi accent"><div class="label">Commandes en cours</div><div class="value">{{ $openOrders }}</div></div>
    <div class="kpi accent"><div class="label">Achats du mois</div><div class="value">@money($monthPurchases)</div></div>
    <div class="kpi"><div class="label">Réceptions en attente</div><div class="value">{{ $pendingReceipts }}</div><div class="sub">unités commandées non reçues</div></div>
    <div class="kpi {{ $totalDebt > 0 ? 'danger' : 'success' }}"><div class="label">Total des dettes</div><div class="value">@money($totalDebt)</div></div>
    <div class="kpi {{ $overdueInvoices > 0 ? 'danger' : '' }}"><div class="label">Factures échues</div><div class="value">{{ $overdueInvoices }}</div></div>
    <div class="kpi accent"><div class="label">Paiements du mois</div><div class="value">@money($monthPayments)</div></div>
    <div class="kpi"><div class="label">Retours du mois</div><div class="value">{{ $returnsCount }}</div></div>
</div>

@php($hasAlerts = $toValidate->count() || $lateDeliveries->count() || $partialOrders->count() || $dueSoon->count() || $overdue->count())
@if($hasAlerts)
<div class="card mb">
    <div class="card-head"><h2>Alertes</h2></div>
    <ul class="list">
        @foreach($toValidate as $o)
            <li><a href="{{ route('purchases.show', $o) }}">Commande {{ $o->number }} — {{ $o->supplier->name }}</a><span class="badge badge-warning">À valider</span></li>
        @endforeach
        @foreach($lateDeliveries as $o)
            <li><a href="{{ route('purchases.show', $o) }}">Livraison en retard : {{ $o->number }} — {{ $o->supplier->name }} (prévue le {{ $o->expected_at->format('d/m/Y') }})</a><span class="badge badge-danger">Retard</span></li>
        @endforeach
        @foreach($partialOrders as $o)
            <li><a href="{{ route('purchases.show', $o) }}">Réception partielle : {{ $o->number }} — {{ $o->supplier->name }}</a><span class="badge badge-info">Partielle</span></li>
        @endforeach
        @foreach($dueSoon as $i)
            <li><a href="{{ route('supplier-invoices.show', $i) }}">Facture {{ $i->number }} — {{ $i->supplier->name }} : échéance {{ $i->due_at->format('d/m/Y') }}</a><span class="badge badge-warning">@money($i->balance())</span></li>
        @endforeach
        @foreach($overdue as $i)
            <li><a href="{{ route('supplier-invoices.show', $i) }}">Dette échue : {{ $i->number }} — {{ $i->supplier->name }}</a><span class="badge badge-danger">@money($i->balance())</span></li>
        @endforeach
    </ul>
</div>
@endif

<div class="grid grid-2">
    <div class="card">
        <div class="card-head"><h2>Achats (12 derniers mois)</h2></div>
        @php($max = max(1, max($purchasesSeries)))
        <div class="bars">
            @foreach($purchasesSeries as $m => $v)
                <div class="bar" style="height:{{ max(2, round($v / $max * 100, 1)) }}%" title="{{ \Illuminate\Support\Carbon::parse($m.'-01')->format('m/Y') }} : {{ money($v) }}"></div>
            @endforeach
        </div>
        <div class="bars-axis">
            <span>{{ \Illuminate\Support\Carbon::parse(array_key_first($purchasesSeries).'-01')->format('m/Y') }}</span>
            <span>{{ \Illuminate\Support\Carbon::parse(array_key_last($purchasesSeries).'-01')->format('m/Y') }}</span>
        </div>
    </div>
    <div class="card">
        <div class="card-head"><h2>Top fournisseurs</h2></div>
        <ul class="list">
            @forelse($topSuppliers as $s)
                <li><a href="{{ route('suppliers.show', $s) }}">{{ $s->name }} <small class="muted">{{ $s->code }}</small></a><strong class="num">@money($s->achats_total ?? 0)</strong></li>
            @empty <p class="empty">Aucun fournisseur.</p> @endforelse
        </ul>
    </div>
</div>
@endsection
