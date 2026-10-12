@extends('layouts.app')
@section('title', 'Factures')
@section('content')
<div class="page-header">
    <div><h1>Factures</h1><p class="subtitle">{{ $invoices->total() }} facture(s) · Total {{ money($totals['total']) }} · Payé {{ money($totals['paid']) }} · Reste {{ money($totals['total'] - $totals['paid']) }}</p></div>
    <div class="actions"><a class="btn" href="{{ route('invoices.create') }}"><x-icon name="plus" /> Nouvelle facture</a></div>
</div>
<form method="get" class="filters">
    <div class="field grow"><label for="q">Recherche</label><input type="search" name="q" id="q" value="{{ request('q') }}" placeholder="N° de facture ou client"></div>
    <div class="field"><label for="status">Statut</label><select name="status" id="status"><option value="">Tous</option>@foreach (\App\Enums\InvoiceStatus::options() as $v => $l)<option value="{{ $v }}" @selected(request('status') === $v)>{{ $l }}</option>@endforeach</select></div>
    @include('partials.store-filter')
    <button class="btn btn-secondary" type="submit">Filtrer</button>
</form>
<div class="card flush">
    <div class="table-wrap"><table class="table">
        <thead><tr><th>N°</th><th>Date</th><th>Client</th><th class="hide-mobile">Commande</th><th>Statut</th><th class="num">Total</th><th class="num">Reste</th><th></th></tr></thead>
        <tbody>
        @forelse ($invoices as $i)
            <tr>
                <td><a class="mono strong" href="{{ route('invoices.show', $i) }}">{{ $i->number }}</a></td>
                <td>{{ $i->issued_at->format('d/m/Y') }}</td>
                <td>{{ $i->customer->name }}</td>
                <td class="hide-mobile mono small">{{ $i->order?->number }}</td>
                <td>{!! badge($i->status) !!}</td>
                <td class="num">{{ money($i->total) }}</td>
                <td class="num">{{ $i->status === \App\Enums\InvoiceStatus::Cancelled ? '—' : money($i->balanceDue()) }}</td>
                <td class="text-right"><a class="btn btn-ghost btn-sm" href="{{ route('invoices.pdf', $i) }}" title="Télécharger le PDF"><x-icon name="download" /></a></td>
            </tr>
        @empty
            <tr><td colspan="8" class="empty">Aucune facture.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
{{ $invoices->links() }}
@endsection
