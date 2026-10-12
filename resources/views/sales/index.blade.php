@extends('layouts.app')
@section('title', 'Ventes')
@section('content')
<div class="page-header">
    <div><h1>Ventes</h1><p class="subtitle">{{ $period->label() }} — annulations et retours présentés séparément</p></div>
    <div class="actions">
        @can('reports.view')
            <a class="btn btn-secondary" href="{{ route('reports.pdf', ['type' => 'ventes'] + $period->query() + array_filter(['store_id' => $storeId, 'city' => $city])) }}"><x-icon name="download" /> PDF</a>
            <a class="btn btn-secondary" href="{{ route('reports.csv', ['type' => 'ventes'] + $period->query() + array_filter(['store_id' => $storeId, 'city' => $city])) }}">Excel (CSV)</a>
        @endcan
    </div>
</div>

<div class="tabs">
    @foreach (['today' => "Aujourd'hui", 'week' => 'Semaine', 'month' => 'Mois', 'year' => 'Année'] as $k => $l)
        <a href="{{ route('sales.index', ['period' => $k] + array_filter(['store_id' => $storeId, 'city' => $city])) }}" @class(['active' => $period->key === $k])>{{ $l }}</a>
    @endforeach
</div>
@include('partials.period-filter')

<div class="stats">
    <div class="stat"><div class="label">Chiffre d'affaires</div><div class="value">{{ money($summary['revenue']) }}</div></div>
    <div class="stat info"><div class="label">Ventes valides</div><div class="value">{{ $summary['orders_count'] }}</div><div class="hint">dont {{ $summary['direct_sales'] }} vente(s) directe(s)</div></div>
    <div class="stat success"><div class="label">Encaissé</div><div class="value">{{ money($summary['collected']) }}</div></div>
    <div class="stat info"><div class="label">Panier moyen</div><div class="value">{{ money($summary['average_basket']) }}</div></div>
    <div class="stat"><div class="label">Annulées</div><div class="value">{{ $summary['cancelled'] }}</div><div class="hint">{{ money($summary['cancelled_amount']) }}</div></div>
    <div class="stat danger"><div class="label">Retournées</div><div class="value">{{ $summary['returned'] }}</div><div class="hint">{{ money($summary['returned_amount']) }}</div></div>
</div>

<div class="card"><div class="card-header"><h2>Évolution</h2></div><x-bar-chart :points="$timeline" /></div>

<div class="grid grid-2">
    <div class="card flush">
        <div class="card-header"><h2>Par magasin</h2></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Magasin</th><th>Ville</th><th class="num">Ventes</th><th class="num">CA</th><th class="num">Annul./retours</th></tr></thead>
                <tbody>
                @foreach ($byStore as $r)
                    <tr><td>{{ $r->store->name }}</td><td>{{ $r->store->city }}</td><td class="num">{{ $r->orders }}</td><td class="num">{{ money($r->revenue) }}</td><td class="num">{{ $r->cancelled_returned }}</td></tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="card flush">
        <div class="card-header"><h2>Par ville</h2></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Ville</th><th class="num">Magasins</th><th class="num">Ventes</th><th class="num">CA</th></tr></thead>
                <tbody>
                @foreach ($byCity as $r)
                    <tr><td>{{ $r->city }}</td><td class="num">{{ $r->stores }}</td><td class="num">{{ $r->orders }}</td><td class="num">{{ money($r->revenue) }}</td></tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="grid grid-2-1" style="margin-top:1.25rem">
    <div class="card flush">
        <div class="card-header"><h2>Ventes de la période</h2></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>N°</th><th>Date</th><th>Client</th><th class="hide-mobile">Magasin</th><th>Statut</th><th class="num">Total</th></tr></thead>
                <tbody>
                @forelse ($validOrders as $o)
                    <tr>
                        <td><a class="mono" href="{{ route('orders.show', $o) }}">{{ $o->number }}</a></td>
                        <td class="nowrap">{{ $o->ordered_at->format('d/m H:i') }}</td>
                        <td>{{ $o->customer?->name ?? 'Comptoir' }}</td>
                        <td class="hide-mobile">{{ $o->store->name }}</td>
                        <td>{!! badge($o->status) !!}</td>
                        <td class="num">{{ money($o->total) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">Aucune vente sur la période.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $validOrders->links() }}
    </div>
    <div>
        <div class="card">
            <h2>Produits les plus vendus</h2>
            <div class="table-wrap"><table class="table"><tbody>
                @forelse ($topProducts as $p)
                    <tr><td>{{ $p->name }}<div class="muted small">{{ money($p->revenue) }}</div></td><td class="num strong">{{ $p->quantity }}</td></tr>
                @empty
                    <tr><td class="empty">—</td></tr>
                @endforelse
            </tbody></table></div>
        </div>
        <div class="card">
            <h2>Annulations et retours</h2>
            <div class="table-wrap"><table class="table"><tbody>
                @forelse ($cancelledOrReturned as $o)
                    <tr><td><a class="mono" href="{{ route('orders.show', $o) }}">{{ $o->number }}</a><div class="muted small">{{ $o->status_reason }}</div></td><td>{!! badge($o->status) !!}</td><td class="num">{{ money($o->total) }}</td></tr>
                @empty
                    <tr><td class="empty">Aucune annulation ni retour.</td></tr>
                @endforelse
            </tbody></table></div>
        </div>
    </div>
</div>
@endsection
