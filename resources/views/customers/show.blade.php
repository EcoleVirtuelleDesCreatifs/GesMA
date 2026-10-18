@extends('layouts.app')
@section('title', $customer->name)
@section('content')
<div class="page-head">
    <div><h1>{{ $customer->name }}</h1><small class="muted">Client depuis le {{ $customer->created_at->format('d/m/Y') }}</small></div>
    <div class="actions">
        @can('orders.create')<a class="btn btn-primary" href="{{ route('orders.create', ['customer_id' => $customer->id]) }}">+ Commande</a>@endcan
        @can('documents.manage')
            <a class="btn" href="{{ route('documents.create', ['type' => 'devis', 'customer_id' => $customer->id]) }}">+ Devis</a>
            <a class="btn" href="{{ route('documents.create', ['type' => 'facture', 'customer_id' => $customer->id]) }}">+ Facture</a>
        @endcan
        @can('customers.manage')<a class="btn" href="{{ route('customers.edit', $customer) }}">Modifier</a>@endcan
        @can('customers.delete')
            <form method="POST" action="{{ route('customers.destroy', $customer) }}" class="inline-form" onsubmit="return confirm('Supprimer le client {{ $customer->name }} ? Cette action est définitive.')">
                @csrf @method('DELETE')
                <button class="btn btn-danger">Supprimer</button>
            </form>
        @endcan
    </div>
</div>

<div class="grid grid-4 mb">
    <div class="kpi accent"><div class="label">Commandes</div><div class="value">{{ $summary['orders'] }}</div></div>
    <div class="kpi accent"><div class="label">Total des achats</div><div class="value">@money($summary['amount'])</div></div>
    <div class="kpi {{ $summary['balance'] > 0 ? 'danger' : '' }}"><div class="label">Reste à payer</div><div class="value">@money($summary['balance'])</div></div>
    <div class="kpi"><div class="label">Annulées / retournées</div><div class="value">{{ $summary['cancelled'] }} / {{ $summary['returned'] }}</div></div>
</div>

<div class="grid grid-main">
    <div>
        <div class="card">
            <div class="tabs" data-tabs>
                <button type="button" class="active" data-tab="orders">Commandes ({{ $orders->count() }})</button>
                <button type="button" data-tab="documents">Devis & factures ({{ $documents->count() }})</button>
                <button type="button" data-tab="deliveries">Livraisons ({{ $deliveries->count() }})</button>
            </div>
            <div data-tab-panel="orders">
                @include('orders._table', ['orders' => $orders, 'showCustomer' => false])
            </div>
            <div data-tab-panel="documents" class="hidden">
                <div class="table-wrap"><table class="table responsive">
                    <thead><tr><th>N°</th><th>Type</th><th>Date</th><th class="right">Montant</th></tr></thead>
                    <tbody>
                    @forelse($documents as $d)
                        <tr>
                            <td data-label="N°"><a href="{{ route('documents.show', $d) }}">{{ $d->number }}</a></td>
                            <td data-label="Type"><span class="badge {{ $d->isQuote() ? 'badge-info' : 'badge-primary' }}">{{ $d->typeLabel() }}</span></td>
                            <td data-label="Date">{{ $d->issued_at->format('d/m/Y') }}</td>
                            <td data-label="Montant" class="right num">@money($d->total)</td>
                        </tr>
                    @empty <tr><td colspan="4" class="empty">Aucun document.</td></tr> @endforelse
                    </tbody>
                </table></div>
            </div>
            <div data-tab-panel="deliveries" class="hidden">
                <div class="table-wrap"><table class="table responsive">
                    <thead><tr><th>Commande</th><th>Adresse</th><th>Date prévue</th><th>Statut</th></tr></thead>
                    <tbody>
                    @forelse($deliveries as $d)
                        <tr>
                            <td data-label="Commande"><a href="{{ route('orders.show', $d->order_id) }}">{{ $d->order->number }}</a></td>
                            <td data-label="Adresse">{{ $d->address }} {{ $d->city }}</td>
                            <td data-label="Date prévue">{{ $d->scheduled_date?->format('d/m/Y') ?? '—' }}</td>
                            <td data-label="Statut"><span class="badge {{ status_badge($d->status) }}">{{ $d->statusLabel() }}</span>@if($d->failure_reason)<br><small class="muted">{{ $d->failure_reason }}</small>@endif</td>
                        </tr>
                    @empty <tr><td colspan="4" class="empty">Aucune livraison.</td></tr> @endforelse
                    </tbody>
                </table></div>
            </div>
        </div>
    </div>
    <div>
        <div class="card">
            <h2>Coordonnées</h2>
            <dl class="dl">
                <dt>Téléphone</dt><dd>@if($customer->phone)<a href="tel:{{ $customer->phone }}">{{ $customer->phone }}</a>@else — @endif</dd>
                <dt>E-mail</dt><dd>{{ $customer->email ?? '—' }}</dd>
                <dt>Adresse</dt><dd>{{ $customer->address ?? '—' }}</dd>
                <dt>Ville</dt><dd>{{ $customer->city ?? '—' }}</dd>
                <dt>Magasin</dt><dd>{{ $customer->store?->name ?? '—' }}</dd>
            </dl>
            @if($customer->notes)<p class="mt">{{ $customer->notes }}</p>@endif
        </div>
    </div>
</div>
@endsection
