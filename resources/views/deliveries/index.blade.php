@extends('layouts.app')
@section('title', 'Livraisons')
@section('content')
<div class="page-head">
    <h1>Livraisons</h1>
    <div class="actions">
        <a class="btn" href="{{ route('deliveries.index', ['view' => $view, 'mine' => 1]) }}">Mes livraisons</a>
    </div>
</div>

<div class="grid grid-4 mb">
    <div class="kpi accent"><div class="label">À préparer</div><div class="value">{{ $counts['preparation'] ?? 0 }}</div></div>
    <div class="kpi accent"><div class="label">En route</div><div class="value">{{ $counts['en_route'] ?? 0 }}</div></div>
    <div class="kpi danger"><div class="label">Échecs à reprogrammer</div><div class="value">{{ $counts['echec'] ?? 0 }}</div></div>
    <div class="kpi success"><div class="label">Livrées</div><div class="value">{{ $counts['livree'] ?? 0 }}</div></div>
</div>

<div class="tabs">
    @foreach(['a_traiter' => 'À traiter', 'aujourdhui' => 'Prévues aujourd’hui', 'terminees' => 'Terminées', 'toutes' => 'Toutes'] as $k => $l)
        <a href="{{ route('deliveries.index', ['view' => $k, 'store_id' => $storeId]) }}" class="{{ $view === $k ? 'active' : '' }}">{{ $l }}</a>
    @endforeach
</div>

<form method="GET" class="filters card">
    <input type="hidden" name="view" value="{{ $view }}">
    <div class="field"><label for="q">Recherche</label><input type="search" name="q" id="q" value="{{ request('q') }}" placeholder="N° commande, client"></div>
    <div class="field">
        <label for="status">Statut</label>
        <select name="status" id="status">
            <option value="">Tous</option>
            @foreach(config('gesma.delivery_statuses') as $k => $l)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>@endforeach
        </select>
    </div>
    <div class="field">
        <label for="driver_id">Livreur</label>
        <select name="driver_id" id="driver_id">
            <option value="">Tous</option>
            @foreach($drivers as $d)<option value="{{ $d->id }}" @selected(request('driver_id') == $d->id)>{{ $d->name }}</option>@endforeach
        </select>
    </div>
    @include('partials.store-filter')
    <button class="btn btn-sm">Filtrer</button>
</form>

<div class="card">
    <div class="table-wrap">
        <table class="table responsive">
            <thead><tr><th>Commande</th><th>Client</th><th>Adresse</th><th>Prévue le</th><th>Livreur</th><th class="right">À encaisser</th><th>Statut</th><th></th></tr></thead>
            <tbody>
            @forelse($deliveries as $d)
                <tr>
                    <td data-label="Commande" class="nowrap"><a href="{{ route('orders.show', $d->order_id) }}">{{ $d->order->number }}</a>@if(auth()->user()->isDirection())<br><small class="muted">{{ $d->store->city }}</small>@endif</td>
                    <td data-label="Client">{{ $d->order->customer?->name ?? $d->contact_name }}@if($d->contact_phone)<br><a href="tel:{{ $d->contact_phone }}"><small>{{ $d->contact_phone }}</small></a>@endif</td>
                    <td data-label="Adresse">{{ $d->address }} {{ $d->city }}</td>
                    <td data-label="Prévue le" class="nowrap">
                        {{ $d->scheduled_date?->format('d/m/Y') ?? '—' }}
                        @if($d->scheduled_date && $d->scheduled_date->isPast() && !$d->scheduled_date->isToday() && $d->isOpen())<span class="badge badge-danger">En retard</span>@endif
                    </td>
                    <td data-label="Livreur">{{ $d->driverLabel() }}</td>
                    <td data-label="À encaisser" class="right num">@money($d->order->balance())</td>
                    <td data-label="Statut"><span class="badge {{ status_badge($d->status) }}">{{ $d->statusLabel() }}</span>@if($d->failure_reason)<br><small class="muted">{{ $d->failure_reason }}</small>@endif</td>
                    <td data-label="" class="actions"><a class="btn btn-sm" href="{{ route('deliveries.edit', $d) }}">Suivre</a></td>
                </tr>
            @empty
                <tr><td colspan="8" class="empty">Aucune livraison.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $deliveries->links() }}
</div>
@endsection
