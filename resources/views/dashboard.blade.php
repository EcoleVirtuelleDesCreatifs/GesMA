@extends('layouts.app')
@section('title', 'Tableau de bord')
@section('content')
<div class="page-header">
    <div>
        <h1>Tableau de bord</h1>
        <p class="subtitle">{{ $storeId ? $stores->firstWhere('id', $storeId)?->name : ($city ? 'Ville de '.$city : (auth()->user()->isDirection() ? 'Ensemble de Mobilier Addict' : auth()->user()->store?->name)) }} · {{ $period->label() }}</p>
    </div>
    <div class="actions">
        @can('orders.manage')
            <a class="btn" href="{{ route('orders.create') }}"><x-icon name="plus" /> Nouvelle commande</a>
            <a class="btn btn-secondary" href="{{ route('orders.create', ['type' => 'sale']) }}">Vente directe</a>
        @endcan
        @can('reports.view')
            <a class="btn btn-secondary" href="{{ route('reports.pdf', ['type' => 'ventes'] + $period->query() + array_filter(['store_id' => $storeId, 'city' => $city])) }}"><x-icon name="download" /> Rapport PDF</a>
        @endcan
    </div>
</div>

@include('partials.period-filter')

@if ($syncConflicts)
    <div class="alert alert-warning">{{ $syncConflicts }} opération(s) hors connexion nécessitent une vérification. <a href="{{ route('sync.index') }}">Voir les conflits</a></div>
@endif

<div class="stats">
    <div class="stat"><div class="label">Chiffre d'affaires</div><div class="value">{{ money($summary['revenue']) }}</div><div class="hint">Encaissé : {{ money($summary['collected']) }}</div></div>
    <div class="stat info"><div class="label">Commandes</div><div class="value">{{ $summary['orders_count'] }}</div><div class="hint">Panier moyen : {{ money($summary['average_basket']) }}</div></div>
    <div class="stat warning"><div class="label">Dépenses</div><div class="value">{{ money($summary['expenses']) }}</div></div>
    <div class="stat {{ $summary['result'] >= 0 ? 'success' : 'danger' }}"><div class="label">Résultat (ventes − dépenses)</div><div class="value">{{ money($summary['result']) }}</div></div>
</div>
<div class="stats">
    <a class="stat info" href="{{ route('orders.index', ['period' => 'today']) }}"><div class="label">Commandes du jour</div><div class="value">{{ $summary['today_orders'] }}</div><div class="hint">{{ money($summary['today_revenue']) }}</div></a>
    <a class="stat success" href="{{ route('orders.index', ['status' => 'delivered'] + $period->query()) }}"><div class="label">Livrées</div><div class="value">{{ $summary['delivered'] }}</div></a>
    <a class="stat warning" href="{{ route('orders.index', ['status' => 'not_delivered'] + $period->query()) }}"><div class="label">Non livrées</div><div class="value">{{ $summary['not_delivered'] }}</div></a>
    <a class="stat danger" href="{{ route('orders.index', ['status' => 'returned'] + $period->query()) }}"><div class="label">Retournées</div><div class="value">{{ $summary['returned'] }}</div><div class="hint">{{ money($summary['returned_amount']) }}</div></a>
    <a class="stat" href="{{ route('orders.index', ['status' => 'cancelled'] + $period->query()) }}"><div class="label">Annulées</div><div class="value">{{ $summary['cancelled'] }}</div><div class="hint">{{ money($summary['cancelled_amount']) }}</div></a>
    <a class="stat info" href="{{ route('deliveries.index') }}"><div class="label">Livraisons en cours</div><div class="value">{{ $pendingDeliveries }}</div></a>
</div>

<div class="grid grid-2-1">
    <div class="card">
        <div class="card-header"><h2>Ventes et dépenses</h2><span class="muted small">Annulations et retours exclus</span></div>
        <x-bar-chart :points="$timeline" />
    </div>
    <div class="card">
        <div class="card-header"><h2>Commandes par statut</h2></div>
        @php $totalStatus = max(1, array_sum($summary['status_counts'])); @endphp
        <ul class="bar-list">
            @foreach (\App\Enums\OrderStatus::cases() as $status)
                <li>
                    <div class="bar-label"><span>{!! badge($status) !!}</span><strong>{{ $summary['status_counts'][$status->value] }}</strong></div>
                    <div class="bar"><span style="width: {{ round(100 * $summary['status_counts'][$status->value] / $totalStatus) }}%"></span></div>
                </li>
            @endforeach
        </ul>
    </div>
</div>

@if (auth()->user()->isDirection() && $byStore->count())
    <div class="grid grid-2-1" style="margin-top:1.25rem">
        <div class="card flush">
            <div class="card-header"><h2>Résultats par magasin</h2></div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Magasin</th><th class="num">Commandes</th><th class="num">Chiffre d'affaires</th><th class="num">Dépenses</th><th class="num">Résultat</th></tr></thead>
                    <tbody>
                    @foreach ($byStore as $row)
                        <tr>
                            <td><a href="{{ route('dashboard', ['store_id' => $row->store->id] + $period->query()) }}">{{ $row->store->name }}</a><div class="muted small">{{ $row->store->city }}</div></td>
                            <td class="num">{{ $row->orders }}</td>
                            <td class="num">{{ money($row->revenue) }}</td>
                            <td class="num">{{ money($row->expenses) }}</td>
                            <td class="num {{ $row->result < 0 ? 'text-danger' : 'text-success' }}">{{ money($row->result) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot><tr><td>Mobilier Addict</td><td class="num">{{ $byStore->sum('orders') }}</td><td class="num">{{ money($byStore->sum('revenue')) }}</td><td class="num">{{ money($byStore->sum('expenses')) }}</td><td class="num">{{ money($byStore->sum('result')) }}</td></tr></tfoot>
                </table>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h2>Résultats par ville</h2></div>
            @php $maxCity = max(1, $byCity->max('revenue')); @endphp
            <ul class="bar-list">
                @foreach ($byCity as $row)
                    <li>
                        <div class="bar-label"><a href="{{ route('dashboard', ['city' => $row->city] + $period->query()) }}">{{ $row->city }}</a><strong>{{ money($row->revenue) }}</strong></div>
                        <div class="bar"><span style="width: {{ round(100 * $row->revenue / $maxCity) }}%"></span></div>
                        <div class="muted small">{{ $row->orders }} commande(s) · dépenses {{ money($row->expenses) }} · résultat {{ money($row->result) }}</div>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
@endif

<div class="grid grid-3" style="margin-top:1.25rem">
    <div class="card">
        <div class="card-header"><h2>Produits les plus vendus</h2></div>
        @php $maxQty = max(1, $topProducts->max('quantity')); @endphp
        <ul class="bar-list">
            @forelse ($topProducts as $p)
                <li>
                    <div class="bar-label"><span>{{ $p->name }}</span><strong>{{ $p->quantity }}</strong></div>
                    <div class="bar"><span style="width: {{ round(100 * $p->quantity / $maxQty) }}%"></span></div>
                    <div class="muted small">{{ money($p->revenue) }}</div>
                </li>
            @empty
                <li class="muted">Aucune vente sur la période.</li>
            @endforelse
        </ul>
    </div>
    <div class="card">
        <div class="card-header"><h2>Stock faible</h2><a class="small" href="{{ route('stock.alerts') }}">Tout voir</a></div>
        <div class="table-wrap">
            <table class="table">
                <tbody>
                @forelse ($lowStock as $s)
                    <tr class="{{ $s->quantity <= 0 ? 'row-danger' : '' }}">
                        <td><a href="{{ route('products.show', $s->product) }}">{{ $s->product->name }}</a><div class="muted small">{{ $s->store->name }}</div></td>
                        <td class="num"><strong>{{ $s->quantity }}</strong><div class="muted small">seuil {{ $s->threshold() }}</div></td>
                    </tr>
                @empty
                    <tr><td class="empty">Aucune alerte de stock.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><h2>Meilleurs clients</h2><a class="small" href="{{ route('customers.top') }}">Tout voir</a></div>
        <div class="table-wrap">
            <table class="table">
                <tbody>
                @forelse ($topCustomers as $c)
                    <tr>
                        <td><a href="{{ route('customers.show', $c->customer) }}">{{ $c->customer->name }}</a><div class="muted small">{{ $c->orders_count }} commande(s)</div></td>
                        <td class="num">{{ money($c->total_spent) }}</td>
                    </tr>
                @empty
                    <tr><td class="empty">Aucun client sur la période.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="grid grid-2" style="margin-top:1.25rem">
    <div class="card flush">
        <div class="card-header"><h2>Commandes du jour</h2><a class="small" href="{{ route('orders.index', ['period' => 'today']) }}">Toutes</a></div>
        <div class="table-wrap">
            <table class="table">
                <tbody>
                @forelse ($todayOrders as $o)
                    <tr>
                        <td><a class="mono" href="{{ route('orders.show', $o) }}">{{ $o->number }}</a><div class="muted small">{{ $o->ordered_at->format('H:i') }} · {{ $o->customer?->name ?? 'Client comptoir' }}</div></td>
                        <td>{!! badge($o->status) !!}</td>
                        <td class="num">{{ money($o->total) }}</td>
                    </tr>
                @empty
                    <tr><td class="empty">Aucune commande aujourd'hui.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><h2>Dépenses par catégorie</h2>@can('expenses.view')<a class="small" href="{{ route('expenses.index', $period->query()) }}">Détail</a>@endcan</div>
        @php $maxExp = max(1, $expensesByCategory->max('amount')); @endphp
        <ul class="bar-list">
            @forelse ($expensesByCategory as $e)
                <li>
                    <div class="bar-label"><span>{{ $e->category }}</span><strong>{{ money($e->amount) }}</strong></div>
                    <div class="bar"><span style="width: {{ round(100 * $e->amount / $maxExp) }}%; background: var(--accent)"></span></div>
                </li>
            @empty
                <li class="muted">Aucune dépense sur la période.</li>
            @endforelse
        </ul>
    </div>
</div>
@endsection
