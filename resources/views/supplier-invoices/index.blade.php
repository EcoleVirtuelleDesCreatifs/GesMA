@extends('layouts.app')
@section('title', 'Factures fournisseurs')
@section('content')
<div class="page-head">
    <h1>Factures fournisseurs</h1>
    <div class="actions">@can('supplier_invoices.manage')<a class="btn btn-primary" href="{{ route('supplier-invoices.create') }}">+ Nouvelle facture</a>@endcan</div>
</div>

<div class="grid grid-2 mb">
    <div class="kpi {{ $totalDebt > 0 ? 'danger' : 'success' }}"><div class="label">Dette totale</div><div class="value">@money($totalDebt)</div></div>
    <div class="kpi {{ $overdueCount > 0 ? 'danger' : '' }}"><div class="label">Factures échues</div><div class="value">{{ $overdueCount }}</div></div>
</div>

<form method="GET" class="filters card">
    <div class="field"><label>Recherche</label><input type="search" name="q" value="{{ request('q') }}" placeholder="N° ou fournisseur" autofocus></div>
    <div class="field"><label>Statut</label>
        <select name="status"><option value="">Toutes</option>
            <option value="non_payee" @selected(request('status') === 'non_payee')>Non soldées</option>
            <option value="en_retard" @selected(request('status') === 'en_retard')>En retard</option>
        </select>
    </div>
    <button class="btn btn-sm">Filtrer</button>
</form>

<div class="card">
    <div class="table-wrap"><table class="table responsive">
        <thead><tr><th>N° facture</th><th>Fournisseur</th><th>Commande</th><th>Date</th><th>Échéance</th><th class="right">Montant</th><th class="right">Réglé</th><th>Statut</th><th></th></tr></thead>
        <tbody>
        @forelse($invoices as $i)
            <tr>
                <td data-label="N°" class="nowrap"><a href="{{ route('supplier-invoices.show', $i) }}"><strong>{{ $i->number }}</strong></a></td>
                <td data-label="Fournisseur">{{ $i->supplier->name }}</td>
                <td data-label="Commande">@if($i->purchaseOrder)<a href="{{ route('purchases.show', $i->purchaseOrder) }}">{{ $i->purchaseOrder->number }}</a>@else — @endif</td>
                <td data-label="Date" class="nowrap">{{ $i->invoiced_at->format('d/m/Y') }}</td>
                <td data-label="Échéance" class="nowrap">{{ $i->due_at?->format('d/m/Y') ?? '—' }}</td>
                <td data-label="Montant" class="right num">@money($i->total)</td>
                <td data-label="Réglé" class="right num">@money($i->payments->sum('amount'))</td>
                <td data-label="Statut"><span class="badge {{ $i->balance() <= 0 ? 'badge-success' : ($i->isDue() ? 'badge-danger' : 'badge-warning') }}">{{ $i->statusLabel() }}</span></td>
                <td data-label="" class="actions"><a class="btn btn-sm" href="{{ route('supplier-invoices.show', $i) }}">Voir</a></td>
            </tr>
        @empty
            <tr><td colspan="9" class="empty">Aucune facture fournisseur.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    {{ $invoices->links() }}
</div>
@endsection
