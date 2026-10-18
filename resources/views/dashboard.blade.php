@extends('layouts.app')
@section('title', 'Tableau de bord')
@section('content')
<div class="page-head">
    <div>
        <h1>Tableau de bord</h1>
        <small class="muted">
            {{ $storeId ? $stores->firstWhere('id', $storeId)?->name : (auth()->user()->isDirection() ? 'Ensemble de Mobilier Addict' : auth()->user()->store?->name) }}
            · {{ $period->label() }}
        </small>
    </div>
    <div class="actions">
        @can('orders.create')<a class="btn btn-primary" href="{{ route('orders.create') }}">+ Nouvelle commande / vente</a>@endcan
        @can('reports.view')
            <a class="btn" href="{{ route('reports.pdf', ['type' => 'ventes', 'store_id' => $storeId] + $period->query()) }}">Exporter en PDF</a>
        @endcan
    </div>
</div>

<form method="GET" class="filters card" data-autosubmit>
    @include('partials.period-filter')
    @include('partials.store-filter')
    <button class="btn btn-sm" type="submit">Afficher</button>
</form>

<div class="grid grid-4 mb">
    <div class="kpi brand">
        <div class="label">Chiffre d’affaires</div>
        <div class="value">@money($stats['revenue'])</div>
        <div class="sub">{{ $stats['sales_count'] }} vente(s) · encaissé @money($stats['collected'])</div>
    </div>
    <div class="kpi accent">
        <div class="label">Commandes</div>
        <div class="value">{{ $stats['orders_count'] }}</div>
        <div class="sub"><a href="{{ route('orders.index', ['period' => 'today', 'store_id' => $storeId]) }}">{{ $stats['today_orders'] }} aujourd’hui</a></div>
    </div>
    <div class="kpi accent">
        <div class="label">Dépenses</div>
        <div class="value">@money($stats['expenses'])</div>
        <div class="sub"><a href="{{ route('expenses.index', ['store_id' => $storeId] + $period->query()) }}">Voir le détail</a></div>
    </div>
    <div class="kpi {{ $stats['result'] >= 0 ? 'success' : 'danger' }}">
        <div class="label">Résultat (ventes − dépenses)</div>
        <div class="value">@money($stats['result'])</div>
        <div class="sub">Panier moyen @money($stats['average_basket'])</div>
    </div>
</div>

<div class="grid grid-4 mb">
    @foreach(['livree' => 'success', 'en_preparation' => 'info', 'non_livree' => 'warning', 'retournee' => 'danger'] as $status => $tone)
        <a class="kpi" href="{{ route('orders.index', ['status' => $status, 'store_id' => $storeId] + $period->query()) }}" style="text-decoration:none">
            <div class="label">{{ $stats['by_status'][$status]['label'] }}</div>
            <div class="value">{{ $stats['by_status'][$status]['count'] }}</div>
            <div class="sub"><span class="badge badge-{{ $tone }}">@money($stats['by_status'][$status]['amount'])</span></div>
        </a>
    @endforeach
</div>
<p class="muted" style="font-size:.85rem;margin-top:-.4rem">
    Expédiées : {{ $stats['by_status']['expediee']['count'] }} ·
    Annulées : {{ $stats['by_status']['annulee']['count'] }} (@money($stats['by_status']['annulee']['amount'])) —
    les annulations et les retours sont exclus du chiffre d’affaires.
</p>

<div class="grid grid-main">
    <div>
        <div class="card">
            <div class="card-head"><h2>Ventes par jour</h2><small class="muted">hors annulations et retours</small></div>
            @php($max = max(1, $stats['daily']->max('amount')))
            @if($stats['daily']->isEmpty())
                <p class="empty">Aucune donnée sur la période.</p>
            @else
                <div class="bars">
                    @foreach($stats['daily'] as $d)
                        <div class="bar" style="height: {{ round($d->amount / $max * 100, 1) }}%"
                             title="{{ $d->day->format('d/m') }} : {{ money($d->amount) }} ({{ $d->count }} vente(s))"></div>
                    @endforeach
                </div>
                <div class="bars-axis">
                    <span>{{ $stats['daily']->first()->day->format('d/m') }}</span>
                    <span>{{ $stats['daily']->last()->day->format('d/m') }}</span>
                </div>
            @endif
        </div>

        <div class="card">
            <div class="card-head"><h2>Résultats par magasin</h2></div>
            <div class="table-wrap">
                <table class="table responsive">
                    <thead><tr><th>Magasin</th><th>Ville</th><th class="right">Ventes</th><th class="right">CA</th><th class="right">Dépenses</th><th class="right">Résultat</th><th class="right">Annul. / retours</th></tr></thead>
                    <tbody>
                    @foreach($stats['by_store'] as $row)
                        <tr>
                            <td data-label="Magasin"><a href="{{ route('dashboard', ['store_id' => $row->store->id] + $period->query()) }}">{{ $row->store->name }}</a></td>
                            <td data-label="Ville">{{ $row->city }}</td>
                            <td data-label="Ventes" class="right num">{{ $row->sales_count }}</td>
                            <td data-label="CA" class="right num">@money($row->revenue)</td>
                            <td data-label="Dépenses" class="right num">@money($row->expenses)</td>
                            <td data-label="Résultat" class="right num" style="color: {{ $row->result >= 0 ? 'var(--success)' : 'var(--danger)' }}">@money($row->result)</td>
                            <td data-label="Annul. / retours" class="right num">{{ $row->cancelled }} / {{ $row->returned }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                    @if($stats['by_store']->count() > 1)
                        <tfoot><tr>
                            <td colspan="2">Total Mobilier Addict</td>
                            <td class="right num">{{ $stats['by_store']->sum('sales_count') }}</td>
                            <td class="right num">@money($stats['by_store']->sum('revenue'))</td>
                            <td class="right num">@money($stats['by_store']->sum('expenses'))</td>
                            <td class="right num">@money($stats['by_store']->sum('result'))</td>
                            <td class="right num">{{ $stats['by_store']->sum('cancelled') }} / {{ $stats['by_store']->sum('returned') }}</td>
                        </tr></tfoot>
                    @endif
                </table>
            </div>
        </div>

        @if($stats['by_city']->count() > 1)
        <div class="card">
            <div class="card-head"><h2>Résultats par ville</h2></div>
            <div class="table-wrap">
                <table class="table responsive">
                    <thead><tr><th>Ville</th><th class="right">Ventes</th><th class="right">CA</th><th class="right">Dépenses</th><th class="right">Résultat</th></tr></thead>
                    <tbody>
                    @foreach($stats['by_city'] as $row)
                        <tr>
                            <td data-label="Ville">{{ $row->city }}</td>
                            <td data-label="Ventes" class="right num">{{ $row->sales_count }}</td>
                            <td data-label="CA" class="right num">@money($row->revenue)</td>
                            <td data-label="Dépenses" class="right num">@money($row->expenses)</td>
                            <td data-label="Résultat" class="right num">@money($row->result)</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        <div class="card">
            <div class="card-head"><h2>Produits les plus vendus</h2></div>
            @if($stats['top_products']->isEmpty())
                <p class="empty">Aucune vente sur la période.</p>
            @else
                @php($maxQty = max(1, $stats['top_products']->max('quantity')))
                <ul class="list">
                    @foreach($stats['top_products'] as $p)
                        <li>
                            <div style="flex:1">
                                <strong>{{ $p->product_name }}</strong> <small class="muted">{{ $p->product_reference }}</small>
                                <div class="hbar"><span style="width: {{ round($p->quantity / $maxQty * 100) }}%"></span></div>
                            </div>
                            <div class="right nowrap"><strong>{{ $p->quantity }}</strong> u.<br><small class="muted">@money($p->amount)</small></div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    <div>
        <div class="card">
            <div class="card-head">
                <h2>Stocks faibles</h2>
                <a href="{{ route('stocks.index', ['low' => 1, 'store_id' => $storeId]) }}">Tout voir</a>
            </div>
            @forelse($stats['low_stocks'] as $s)
                @if($loop->first)<ul class="list">@endif
                <li>
                    <div><a href="{{ route('products.show', $s->product_id) }}">{{ $s->product->name }}</a><br><small class="muted">{{ $s->store->name }}</small></div>
                    <span class="badge {{ $s->quantity <= 0 ? 'badge-danger' : 'badge-warning' }}">{{ $s->quantity }} / seuil {{ $s->alert_threshold }}</span>
                </li>
                @if($loop->last)</ul>@endif
            @empty
                <p class="empty">Aucun produit en stock faible.</p>
            @endforelse
        </div>

        <div class="card">
            <div class="card-head"><h2>Meilleurs clients</h2><a href="{{ route('customers.index', ['sort' => 'amount']) }}">Tout voir</a></div>
            @forelse($stats['top_customers'] as $c)
                @if($loop->first)<ul class="list">@endif
                <li>
                    <div><a href="{{ route('customers.show', $c->customer) }}">{{ $c->customer->name }}</a><br><small class="muted">{{ $c->orders_count }} commande(s)</small></div>
                    <strong class="num">@money($c->amount)</strong>
                </li>
                @if($loop->last)</ul>@endif
            @empty
                <p class="empty">Aucun client sur la période.</p>
            @endforelse
        </div>

        <div class="card">
            <div class="card-head"><h2>Dépenses par catégorie</h2></div>
            @forelse($stats['expenses_by_category'] as $e)
                @if($loop->first)<ul class="list">@endif
                <li><span>{{ $e->label }}</span><strong class="num">@money($e->total)</strong></li>
                @if($loop->last)</ul>@endif
            @empty
                <p class="empty">Aucune dépense sur la période.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
