@extends('layouts.app')
@section('title', 'Paiements & dettes')
@section('content')
<div class="page-head"><h1>Paiements & dettes fournisseurs</h1></div>

<div class="grid grid-2 mb">
    <div class="card">
        <div class="card-head"><h2>Dettes par fournisseur</h2><strong class="num">@money($totalDebt)</strong></div>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Fournisseur</th><th class="right">Facturé</th><th class="right">Réglé</th><th class="right">Dette</th></tr></thead>
            <tbody>
            @forelse($debts as $d)
                <tr>
                    <td><a href="{{ route('suppliers.show', $d->supplier_id) }}">{{ $d->name }}</a> <small class="muted">{{ $d->code }}</small></td>
                    <td class="right num">@money($d->facture)</td>
                    <td class="right num">@money($d->paye)</td>
                    <td class="right num"><strong>@money($d->dette)</strong></td>
                </tr>
            @empty
                <tr><td colspan="4" class="empty">Aucune dette en cours.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>
    <div class="card">
        <div class="card-head"><h2>Factures échues</h2></div>
        <ul class="list">
            @forelse($overdue as $i)
                <li><a href="{{ route('supplier-invoices.show', $i) }}">{{ $i->number }} — {{ $i->supplier->name }} <small class="muted">éch. {{ $i->due_at->format('d/m/Y') }}</small></a><strong class="num">@money($i->balance())</strong></li>
            @empty <p class="empty">Aucune facture échue.</p> @endforelse
        </ul>
    </div>
</div>

<div class="card">
    <div class="card-head"><h2>Règlements enregistrés</h2></div>
    <div class="table-wrap"><table class="table responsive">
        <thead><tr><th>Date</th><th>Fournisseur</th><th>Facture</th><th>Mode</th><th>Référence</th><th>Magasin</th><th>Par</th><th class="right">Montant</th></tr></thead>
        <tbody>
        @forelse($payments as $p)
            <tr>
                <td data-label="Date" class="nowrap">{{ $p->paid_at->format('d/m/Y') }}</td>
                <td data-label="Fournisseur">{{ $p->supplier->name }}</td>
                <td data-label="Facture"><a href="{{ route('supplier-invoices.show', $p->invoice) }}">{{ $p->invoice->number }}</a></td>
                <td data-label="Mode">{{ $p->methodLabel() }}</td>
                <td data-label="Référence">{{ $p->reference ?? '—' }}</td>
                <td data-label="Magasin">{{ $p->store?->name ?? '—' }}</td>
                <td data-label="Par">{{ $p->user?->name ?? '—' }}</td>
                <td data-label="Montant" class="right num">@money($p->amount)</td>
            </tr>
        @empty
            <tr><td colspan="8" class="empty">Aucun règlement.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    {{ $payments->links() }}
</div>
@endsection
