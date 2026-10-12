@extends('pdf.layout')
@section('title', $title)
@section('content')
@include('pdf.report-header')
<table class="kpis" style="margin-top:12px">
    <tr>
        <td><div class="l">Commandes valides</div><div class="v">{{ $summary['orders_count'] }}</div></td>
        <td><div class="l">Chiffre d'affaires</div><div class="v">{{ money($summary['revenue']) }}</div></td>
        <td><div class="l">Livrées / non livrées</div><div class="v">{{ $summary['delivered'] }} / {{ $summary['not_delivered'] }}</div></td>
        <td><div class="l">Annulées / retournées</div><div class="v">{{ $summary['cancelled'] }} / {{ $summary['returned'] }}</div></td>
    </tr>
</table>
<h2>Détail des commandes ({{ $orders->count() }})</h2>
<table class="grid">
    <thead><tr><th>N°</th><th>Date</th><th>Type</th><th>Origine</th><th>Client</th><th>Magasin</th><th>Statut</th><th class="num">Total</th><th class="num">Payé</th></tr></thead>
    <tbody>
    @forelse ($orders as $o)
        <tr><td>{{ $o->number }}</td><td>{{ $o->ordered_at->format('d/m/Y H:i') }}</td><td>{{ $o->type->label() }}</td><td>{{ $o->source->label() }}{{ $o->external_reference ? ' ('.$o->external_reference.')' : '' }}</td><td>{{ $o->customer?->name ?? 'Comptoir' }}</td><td>{{ $o->store->name }}</td><td>{{ $o->status->label() }}</td><td class="num">{{ money($o->total) }}</td><td class="num">{{ money($o->amount_paid) }}</td></tr>
    @empty
        <tr><td colspan="9" class="muted">Aucune commande sur la période.</td></tr>
    @endforelse
    </tbody>
</table>
@endsection
