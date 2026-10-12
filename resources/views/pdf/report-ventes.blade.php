@extends('pdf.layout')
@section('title', $title)
@section('content')
@include('pdf.report-header')
<table class="kpis" style="margin-top:12px">
    <tr>
        <td><div class="l">Chiffre d'affaires</div><div class="v">{{ money($summary['revenue']) }}</div></td>
        <td><div class="l">Commandes valides</div><div class="v">{{ $summary['orders_count'] }}</div></td>
        <td><div class="l">Panier moyen</div><div class="v">{{ money($summary['average_basket']) }}</div></td>
        <td><div class="l">Encaissé</div><div class="v">{{ money($summary['collected']) }}</div></td>
    </tr>
</table>
<table class="kpis" style="margin-top:6px">
    <tr>
        <td><div class="l">Dépenses</div><div class="v">{{ money($summary['expenses']) }}</div></td>
        <td><div class="l">Résultat</div><div class="v {{ $summary['result'] < 0 ? 'danger' : 'success' }}">{{ money($summary['result']) }}</div></td>
        <td><div class="l">Annulées</div><div class="v">{{ $summary['cancelled'] }} · {{ money($summary['cancelled_amount']) }}</div></td>
        <td><div class="l">Retournées</div><div class="v">{{ $summary['returned'] }} · {{ money($summary['returned_amount']) }}</div></td>
    </tr>
</table>

<h2>Résultats par magasin</h2>
<table class="grid">
    <thead><tr><th>Magasin</th><th>Ville</th><th class="num">Commandes</th><th class="num">Chiffre d'affaires</th><th class="num">Annul./retours</th><th class="num">Dépenses</th><th class="num">Résultat</th></tr></thead>
    <tbody>@foreach ($byStore as $r)<tr><td>{{ $r->store->name }}</td><td>{{ $r->store->city }}</td><td class="num">{{ $r->orders }}</td><td class="num">{{ money($r->revenue) }}</td><td class="num">{{ $r->cancelled_returned }}</td><td class="num">{{ money($r->expenses) }}</td><td class="num">{{ money($r->result) }}</td></tr>@endforeach</tbody>
    <tfoot><tr><td colspan="2">Total</td><td class="num">{{ $byStore->sum('orders') }}</td><td class="num">{{ money($byStore->sum('revenue')) }}</td><td class="num">{{ $byStore->sum('cancelled_returned') }}</td><td class="num">{{ money($byStore->sum('expenses')) }}</td><td class="num">{{ money($byStore->sum('result')) }}</td></tr></tfoot>
</table>

<h2>Résultats par ville</h2>
<table class="grid">
    <thead><tr><th>Ville</th><th class="num">Magasins</th><th class="num">Commandes</th><th class="num">Chiffre d'affaires</th><th class="num">Dépenses</th><th class="num">Résultat</th></tr></thead>
    <tbody>@foreach ($byCity as $r)<tr><td>{{ $r->city }}</td><td class="num">{{ $r->stores }}</td><td class="num">{{ $r->orders }}</td><td class="num">{{ money($r->revenue) }}</td><td class="num">{{ money($r->expenses) }}</td><td class="num">{{ money($r->result) }}</td></tr>@endforeach</tbody>
</table>

<h2>Évolution des ventes</h2>
<table class="grid">
    <thead><tr><th>Période</th><th class="num">Ventes</th><th class="num">Dépenses</th></tr></thead>
    <tbody>@foreach (array_filter($timeline, fn ($p) => $p['sales'] || $p['expenses']) as $p)<tr><td>{{ $p['label'] }}</td><td class="num">{{ money($p['sales']) }}</td><td class="num">{{ money($p['expenses']) }}</td></tr>@endforeach</tbody>
</table>

<h2>Produits les plus vendus</h2>
<table class="grid">
    <thead><tr><th>Produit</th><th>Référence</th><th class="num">Quantité</th><th class="num">Chiffre d'affaires</th></tr></thead>
    <tbody>@forelse ($topProducts as $p)<tr><td>{{ $p->name }}</td><td>{{ $p->reference }}</td><td class="num">{{ $p->quantity }}</td><td class="num">{{ money($p->revenue) }}</td></tr>@empty<tr><td colspan="4" class="muted">Aucune vente.</td></tr>@endforelse</tbody>
</table>

<h2>Meilleurs clients</h2>
<table class="grid">
    <thead><tr><th>Client</th><th>Téléphone</th><th class="num">Commandes</th><th class="num">Montant</th></tr></thead>
    <tbody>@forelse ($topCustomers as $c)<tr><td>{{ $c->customer->name }}</td><td>{{ $c->customer->phone }}</td><td class="num">{{ $c->orders_count }}</td><td class="num">{{ money($c->total_spent) }}</td></tr>@empty<tr><td colspan="4" class="muted">—</td></tr>@endforelse</tbody>
</table>

<h2>Annulations et retours (exclus du chiffre d'affaires)</h2>
<table class="grid">
    <thead><tr><th>N°</th><th>Date</th><th>Client</th><th>Magasin</th><th>Statut</th><th>Motif</th><th class="num">Montant</th></tr></thead>
    <tbody>@forelse ($cancelledOrReturned as $o)<tr><td>{{ $o->number }}</td><td>{{ $o->ordered_at->format('d/m/Y') }}</td><td>{{ $o->customer?->name }}</td><td>{{ $o->store->name }}</td><td>{{ $o->status->label() }}</td><td>{{ $o->status_reason }}</td><td class="num">{{ money($o->total) }}</td></tr>@empty<tr><td colspan="7" class="muted">Aucune annulation ni retour.</td></tr>@endforelse</tbody>
</table>
@endsection
