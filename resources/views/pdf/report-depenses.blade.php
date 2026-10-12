@extends('pdf.layout')
@section('title', $title)
@section('content')
@include('pdf.report-header')
<table class="kpis" style="margin-top:12px">
    <tr>
        <td><div class="l">Total des dépenses</div><div class="v">{{ money($expenses->sum('amount')) }}</div></td>
        <td><div class="l">Ventes de la période</div><div class="v">{{ money($summary['revenue']) }}</div></td>
        <td><div class="l">Ventes − dépenses</div><div class="v {{ $summary['result'] < 0 ? 'danger' : 'success' }}">{{ money($summary['result']) }}</div></td>
        <td><div class="l">Nombre de dépenses</div><div class="v">{{ $expenses->count() }}</div></td>
    </tr>
</table>
<h2>Par catégorie</h2>
<table class="grid">
    <thead><tr><th>Catégorie</th><th class="num">Nombre</th><th class="num">Montant</th></tr></thead>
    <tbody>@forelse ($byCategory as $c)<tr><td>{{ $c->category }}</td><td class="num">{{ $c->n }}</td><td class="num">{{ money($c->amount) }}</td></tr>@empty<tr><td colspan="3" class="muted">—</td></tr>@endforelse</tbody>
</table>
<h2>Par magasin (comparaison avec les ventes)</h2>
<table class="grid">
    <thead><tr><th>Magasin</th><th class="num">Dépenses</th><th class="num">Ventes</th><th class="num">Résultat</th></tr></thead>
    <tbody>@foreach ($byStore as $r)<tr><td>{{ $r->store->name }}</td><td class="num">{{ money($r->expenses) }}</td><td class="num">{{ money($r->revenue) }}</td><td class="num">{{ money($r->result) }}</td></tr>@endforeach</tbody>
</table>
<h2>Détail</h2>
<table class="grid">
    <thead><tr><th>Date</th><th>Magasin</th><th>Catégorie</th><th>Description</th><th>Saisie par</th><th class="num">Montant</th></tr></thead>
    <tbody>@forelse ($expenses as $e)<tr><td>{{ $e->spent_at->format('d/m/Y') }}</td><td>{{ $e->store->name }}</td><td>{{ $e->category->name }}</td><td>{{ $e->description }}</td><td>{{ $e->user?->name }}</td><td class="num">{{ money($e->amount) }}</td></tr>@empty<tr><td colspan="6" class="muted">Aucune dépense.</td></tr>@endforelse</tbody>
    <tfoot><tr><td colspan="5">Total</td><td class="num">{{ money($expenses->sum('amount')) }}</td></tr></tfoot>
</table>
@endsection
