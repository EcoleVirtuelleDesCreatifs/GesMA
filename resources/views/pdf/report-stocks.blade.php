@extends('pdf.layout')
@section('title', $title)
@section('content')
<h1>{{ $title }}</h1>
<div class="muted">Situation au {{ now()->format('d/m/Y à H:i') }} · Périmètre : {{ $scopeLabel }} · Édité par {{ $generatedBy }}</div>
@php $value = 0; $units = 0; @endphp
<h2>Quantités par magasin</h2>
<table class="grid">
    <thead><tr><th>Produit</th><th>Réf.</th>@foreach ($stores as $s)<th class="num">{{ $s->code }}</th>@endforeach<th class="num">Total</th><th class="num">Valeur (prix de vente)</th></tr></thead>
    <tbody>
    @foreach ($products as $p)
        @php $total = $stores->sum(fn ($s) => $p->quantityIn($s->id)); $units += max(0, $total); $value += max(0, $total) * $p->price; @endphp
        <tr><td>{{ $p->name }}</td><td class="small">{{ $p->reference }}</td>@foreach ($stores as $s)@php $q = $p->quantityIn($s->id); @endphp<td class="num {{ $q <= 0 ? 'danger' : '' }}">{{ $q }}</td>@endforeach<td class="num"><strong>{{ $total }}</strong></td><td class="num">{{ money(max(0, $total) * $p->price) }}</td></tr>
    @endforeach
    </tbody>
    <tfoot><tr><td colspan="{{ 2 + $stores->count() }}">Total</td><td class="num">{{ $units }}</td><td class="num">{{ money($value) }}</td></tr></tfoot>
</table>
<p class="small muted">Codes magasins : @foreach ($stores as $s){{ $s->code }} = {{ $s->name }}@if(! $loop->last), @endif @endforeach</p>
<h2>Alertes de stock faible ({{ $lowStock->count() }})</h2>
<table class="grid">
    <thead><tr><th>Produit</th><th>Magasin</th><th class="num">Quantité</th><th class="num">Seuil</th></tr></thead>
    <tbody>@forelse ($lowStock as $s)<tr><td>{{ $s->product->name }}</td><td>{{ $s->store->name }}</td><td class="num danger">{{ $s->quantity }}</td><td class="num">{{ $s->threshold() }}</td></tr>@empty<tr><td colspan="4" class="muted">Aucune alerte.</td></tr>@endforelse</tbody>
</table>
@endsection
