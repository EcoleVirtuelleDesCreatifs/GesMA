@extends('layouts.app')
@section('title', 'Livraisons')
@section('content')
<div class="page-header">
    <div><h1>Livraisons</h1><p class="subtitle">Commandes à préparer, en route, livrées, en échec ou retournées</p></div>
    <div class="actions"><a class="btn btn-secondary" href="{{ route('offline.app') }}"><x-icon name="wifi-off" /> Suivi hors connexion</a></div>
</div>
<div class="stats">
    @foreach (\App\Enums\DeliveryStatus::cases() as $s)
        <a class="stat {{ $s->color() === 'muted' ? '' : $s->color() }}" href="{{ route('deliveries.index', ['view' => 'all', 'status' => $s->value]) }}"><div class="label">{{ $s->label() }}</div><div class="value">{{ $counts[$s->value] ?? 0 }}</div></a>
    @endforeach
</div>
<div class="tabs">
    @foreach (['active' => 'À traiter', 'today' => "Prévues aujourd'hui", 'mine' => 'Mes livraisons', 'all' => 'Toutes'] as $k => $l)
        <a href="{{ route('deliveries.index', ['view' => $k]) }}" @class(['active' => $view === $k && ! request('status')])>{{ $l }}</a>
    @endforeach
</div>
<form method="get" class="filters">
    <input type="hidden" name="view" value="{{ $view }}">
    <div class="field grow"><label for="q">Recherche</label><input type="search" name="q" id="q" value="{{ request('q') }}" placeholder="N° de commande, client, téléphone"></div>
    <div class="field"><label for="status">Statut</label><select name="status" id="status"><option value="">Tous</option>@foreach (\App\Enums\DeliveryStatus::options() as $v => $l)<option value="{{ $v }}" @selected(request('status') === $v)>{{ $l }}</option>@endforeach</select></div>
    @include('partials.store-filter')
    <button class="btn btn-secondary" type="submit">Filtrer</button>
</form>
<div class="card flush">
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Commande</th><th>Client / adresse</th><th>Date prévue</th><th class="hide-mobile">Livreur</th><th>Statut</th><th class="num">Frais</th></tr></thead>
        <tbody>
        @forelse ($deliveries as $d)
            <tr class="{{ $d->status === \App\Enums\DeliveryStatus::Failed ? 'row-warning' : '' }}">
                <td><a class="mono strong" href="{{ route('deliveries.show', $d) }}">{{ $d->order->number }}</a>@if(auth()->user()->isDirection())<div class="muted small">{{ $d->store->name }}</div>@endif</td>
                <td>{{ $d->order->customer?->name ?? $d->contact_name }}<div class="muted small">{{ $d->address }}{{ $d->city ? ', '.$d->city : '' }}</div>@if($d->contact_phone)<a class="small" href="tel:{{ $d->contact_phone }}">{{ $d->contact_phone }}</a>@endif</td>
                <td class="nowrap {{ $d->scheduled_date?->isPast() && in_array($d->status->value, \App\Enums\DeliveryStatus::active()) && ! $d->scheduled_date->isToday() ? 'text-danger strong' : '' }}">{{ $d->scheduled_date?->format('d/m/Y') ?? 'À définir' }}</td>
                <td class="hide-mobile">{{ $d->driverLabel() ?? '—' }}</td>
                <td>{!! badge($d->status) !!}@if($d->failure_reason)<div class="small text-danger">{{ $d->failure_reason }}</div>@endif</td>
                <td class="num">{{ money($d->fee) }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="empty">Aucune livraison.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
{{ $deliveries->links() }}
@endsection
