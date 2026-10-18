@extends('layouts.app')
@section('title', 'Commandes & ventes')
@section('content')
<div class="page-head">
    <h1>Commandes & ventes</h1>
    <div class="actions">
        @can('orders.create')<a class="btn btn-primary" href="{{ route('orders.create') }}">+ Nouvelle commande / vente</a>@endcan
    </div>
</div>

<div class="tabs">
    <a href="{{ route('orders.index', ['period' => 'today']) }}" class="{{ request('period') === 'today' && !request('status') ? 'active' : '' }}">Aujourd’hui</a>
    <a href="{{ route('orders.index', ['status' => 'en_preparation']) }}" class="{{ request('status') === 'en_preparation' ? 'active' : '' }}">À traiter</a>
    <a href="{{ route('orders.index', ['status' => 'expediee']) }}" class="{{ request('status') === 'expediee' ? 'active' : '' }}">Expédiées</a>
    <a href="{{ route('orders.index', ['status' => 'non_livree']) }}" class="{{ request('status') === 'non_livree' ? 'active' : '' }}">Non livrées</a>
    <a href="{{ route('orders.index', ['source' => 'site']) }}" class="{{ request('source') === 'site' ? 'active' : '' }}">Site web</a>
    <a href="{{ route('orders.index') }}" class="{{ !request()->hasAny(['period', 'status', 'source', 'q']) ? 'active' : '' }}">Toutes</a>
</div>

<form method="GET" class="filters card">
    <div class="field"><label for="q">Recherche</label><input type="search" name="q" id="q" value="{{ request('q') }}" placeholder="N° de commande, client, téléphone"></div>
    <div class="field">
        <label for="status">Statut</label>
        <select name="status" id="status">
            <option value="">Tous</option>
            @foreach(config('gesma.order_statuses') as $k => $l)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>@endforeach
        </select>
    </div>
    <div class="field">
        <label for="source">Origine</label>
        <select name="source" id="source">
            <option value="">Toutes</option>
            @foreach(config('gesma.order_sources') as $k => $l)<option value="{{ $k }}" @selected(request('source') === $k)>{{ $l }}</option>@endforeach
        </select>
    </div>
    @include('partials.period-filter', ['period' => $period, 'allowAll' => true])
    @include('partials.store-filter')
    <button class="btn btn-sm">Filtrer</button>
</form>

<div class="card">
    <div class="card-head">
        <small class="muted">{{ $totals->n ?? 0 }} commande(s){{ $period ? ' · '.$period->label() : '' }}</small>
        <strong>Ventes (hors annulations/retours) : @money($totals->revenue ?? 0)</strong>
    </div>
    @include('orders._table', ['orders' => $orders])
    {{ $orders->links() }}
</div>
@endsection
