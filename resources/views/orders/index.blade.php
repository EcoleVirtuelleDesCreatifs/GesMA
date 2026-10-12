@extends('layouts.app')
@section('title', 'Commandes')
@section('content')
<div class="page-header">
    <div><h1>Commandes</h1><p class="subtitle">{{ $orders->total() }} commande(s)</p></div>
    <div class="actions">
        @can('orders.manage')
            <a class="btn" href="{{ route('orders.create') }}"><x-icon name="plus" /> Nouvelle commande</a>
            <a class="btn btn-secondary" href="{{ route('orders.create', ['type' => 'sale']) }}">Vente directe</a>
        @endcan
    </div>
</div>

<div class="tabs">
    <a href="{{ route('orders.index') }}" @class(['active' => ! request()->hasAny(['period', 'status', 'from'])])>Toutes</a>
    <a href="{{ route('orders.index', ['period' => 'today']) }}" @class(['active' => request('period') === 'today' && ! request('status')])>Du jour</a>
    @foreach (\App\Enums\OrderStatus::cases() as $s)
        <a href="{{ route('orders.index', ['status' => $s->value]) }}" @class(['active' => request('status') === $s->value && ! request('period')])>{{ $s->label() }}</a>
    @endforeach
</div>

<form method="get" class="filters">
    <div class="field grow"><label for="q">Recherche</label><input type="search" id="q" name="q" value="{{ request('q') }}" placeholder="N° de commande, réf. site, client, téléphone"></div>
    <div class="field"><label for="status">Statut</label>
        <select name="status" id="status"><option value="">Tous</option>@foreach (\App\Enums\OrderStatus::options() as $v => $l)<option value="{{ $v }}" @selected(request('status') === $v)>{{ $l }}</option>@endforeach</select>
    </div>
    <div class="field"><label for="source">Origine</label>
        <select name="source" id="source"><option value="">Toutes</option>@foreach (\App\Enums\OrderSource::options() as $v => $l)<option value="{{ $v }}" @selected(request('source') === $v)>{{ $l }}</option>@endforeach</select>
    </div>
    <div class="field"><label for="type">Type</label>
        <select name="type" id="type"><option value="">Tous</option>@foreach (\App\Enums\OrderType::options() as $v => $l)<option value="{{ $v }}" @selected(request('type') === $v)>{{ $l }}</option>@endforeach</select>
    </div>
    @include('partials.store-filter')
    <div class="field"><label for="from">Du</label><input type="date" id="from" name="from" value="{{ request('from') }}"></div>
    <div class="field"><label for="to">Au</label><input type="date" id="to" name="to" value="{{ request('to') }}"></div>
    @if (request('from') || request('to'))<input type="hidden" name="period" value="custom">@elseif(request('period'))<input type="hidden" name="period" value="{{ request('period') }}">@endif
    <button class="btn btn-secondary" type="submit"><x-icon name="search" /> Rechercher</button>
</form>

<div class="card flush">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Commande</th><th>Date</th><th>Client</th><th class="hide-mobile">Magasin</th><th>Statut</th><th class="hide-mobile">Livraison</th><th class="num">Total</th></tr></thead>
            <tbody>
            @forelse ($orders as $order)
                <tr>
                    <td>
                        <a class="mono strong" href="{{ route('orders.show', $order) }}">{{ $order->number }}</a>
                        <div class="small">{!! badge($order->type) !!} @if($order->source->value !== 'gesma'){!! badge($order->source) !!}@endif</div>
                    </td>
                    <td class="nowrap">{{ $order->ordered_at->format('d/m/Y') }}<div class="muted small">{{ $order->ordered_at->format('H:i') }}</div></td>
                    <td>{{ $order->customer?->name ?? 'Client comptoir' }}<div class="muted small">{{ $order->customer?->phone }}</div></td>
                    <td class="hide-mobile">{{ $order->store->name }}</td>
                    <td>{!! badge($order->status) !!}</td>
                    <td class="hide-mobile">{!! $order->delivery ? badge($order->delivery->status) : '<span class="muted small">—</span>' !!}</td>
                    <td class="num">{{ money($order->total) }}@if($order->balanceDue() > 0 && $order->status->holdsStock())<div class="small text-warning">reste {{ money($order->balanceDue()) }}</div>@endif</td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty">Aucune commande ne correspond à votre recherche.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
{{ $orders->links() }}
@endsection
