@extends('layouts.app')
@section('title', $customer->name)
@section('content')
<div class="page-header">
    <div>
        <h1>{{ $customer->name }}</h1>
        <p class="subtitle">Client depuis le {{ $customer->created_at->format('d/m/Y') }}@if($customer->website_customer_id) · compte site web n° {{ $customer->website_customer_id }}@endif</p>
    </div>
    <div class="actions">
        @can('orders.manage')<a class="btn" href="{{ route('orders.create', ['customer_id' => $customer->id]) }}"><x-icon name="plus" /> Commande</a>@endcan
        @can('quotes.manage')<a class="btn btn-secondary" href="{{ route('quotes.create', ['customer_id' => $customer->id]) }}">Devis</a>@endcan
        @can('invoices.manage')<a class="btn btn-secondary" href="{{ route('invoices.create', ['customer_id' => $customer->id]) }}">Facture</a>@endcan
        @can('customers.manage')<a class="btn btn-secondary" href="{{ route('customers.edit', $customer) }}"><x-icon name="edit" /> Modifier</a>@endcan
    </div>
</div>

<div class="grid grid-2-1">
    <div>
        <div class="stats">
            <div class="stat"><div class="label">Total des achats</div><div class="value">{{ money($customer->total_spent) }}</div></div>
            <div class="stat info"><div class="label">Commandes</div><div class="value">{{ $orders->count() }}</div></div>
            <div class="stat"><div class="label">Devis</div><div class="value">{{ $quotes->count() }}</div></div>
            <div class="stat"><div class="label">Factures</div><div class="value">{{ $invoices->count() }}</div></div>
        </div>

        <div class="card flush">
            <div class="card-header"><h2>Commandes</h2></div>
            <div class="table-wrap"><table class="table">
                <thead><tr><th>N°</th><th>Date</th><th class="hide-mobile">Magasin</th><th>Statut</th><th class="num">Total</th></tr></thead>
                <tbody>
                @forelse ($orders as $o)
                    <tr><td><a class="mono" href="{{ route('orders.show', $o) }}">{{ $o->number }}</a></td><td>{{ $o->ordered_at->format('d/m/Y') }}</td><td class="hide-mobile">{{ $o->store->name }}</td><td>{!! badge($o->status) !!}</td><td class="num">{{ money($o->total) }}</td></tr>
                @empty
                    <tr><td colspan="5" class="empty">Aucune commande.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>

        <div class="card flush">
            <div class="card-header"><h2>Livraisons</h2></div>
            <div class="table-wrap"><table class="table">
                <thead><tr><th>Commande</th><th>Adresse</th><th>Date prévue</th><th>Statut</th></tr></thead>
                <tbody>
                @forelse ($deliveries as $d)
                    <tr><td><a class="mono" href="{{ route('deliveries.show', $d) }}">{{ $d->order->number }}</a></td><td>{{ $d->address }}</td><td>{{ $d->scheduled_date?->format('d/m/Y') ?? '—' }}</td><td>{!! badge($d->status) !!}</td></tr>
                @empty
                    <tr><td colspan="4" class="empty">Aucune livraison.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>

        <div class="grid grid-2">
            <div class="card flush">
                <div class="card-header"><h2>Devis</h2></div>
                <div class="table-wrap"><table class="table"><tbody>
                    @forelse ($quotes as $q)
                        <tr><td><a class="mono" href="{{ route('quotes.show', $q) }}">{{ $q->number }}</a><div class="muted small">{{ $q->issued_at->format('d/m/Y') }}</div></td><td>{!! badge($q->status) !!}</td><td class="num">{{ money($q->total) }}</td></tr>
                    @empty
                        <tr><td class="empty">Aucun devis.</td></tr>
                    @endforelse
                </tbody></table></div>
            </div>
            <div class="card flush">
                <div class="card-header"><h2>Factures</h2></div>
                <div class="table-wrap"><table class="table"><tbody>
                    @forelse ($invoices as $i)
                        <tr><td><a class="mono" href="{{ route('invoices.show', $i) }}">{{ $i->number }}</a><div class="muted small">{{ $i->issued_at->format('d/m/Y') }}</div></td><td>{!! badge($i->status) !!}</td><td class="num">{{ money($i->total) }}</td></tr>
                    @empty
                        <tr><td class="empty">Aucune facture.</td></tr>
                    @endforelse
                </tbody></table></div>
            </div>
        </div>
    </div>
    <div>
        <div class="card">
            <h2>Coordonnées</h2>
            <dl class="details">
                <dt>Téléphone</dt><dd>@if($customer->phone)<a href="tel:{{ $customer->phone }}">{{ $customer->phone }}</a>@else — @endif</dd>
                @if ($customer->phone_alt)<dt>Autre tél.</dt><dd><a href="tel:{{ $customer->phone_alt }}">{{ $customer->phone_alt }}</a></dd>@endif
                <dt>E-mail</dt><dd>{{ $customer->email ?? '—' }}</dd>
                <dt>Ville</dt><dd>{{ $customer->city ?? '—' }}</dd>
                <dt>Adresse</dt><dd>{{ $customer->address ?? '—' }}</dd>
            </dl>
            @if ($customer->notes)<p class="small" style="margin:.8rem 0 0">{!! nl2br(e($customer->notes)) !!}</p>@endif
        </div>
    </div>
</div>
@endsection
