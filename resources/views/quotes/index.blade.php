@extends('layouts.app')
@section('title', 'Devis')
@section('content')
<div class="page-header">
    <div><h1>Devis</h1><p class="subtitle">{{ $quotes->total() }} devis</p></div>
    <div class="actions"><a class="btn" href="{{ route('quotes.create') }}"><x-icon name="plus" /> Nouveau devis</a></div>
</div>
<form method="get" class="filters">
    <div class="field grow"><label for="q">Recherche</label><input type="search" name="q" id="q" value="{{ request('q') }}" placeholder="N° de devis ou client"></div>
    <div class="field"><label for="status">Statut</label><select name="status" id="status"><option value="">Tous</option>@foreach (\App\Enums\QuoteStatus::options() as $v => $l)<option value="{{ $v }}" @selected(request('status') === $v)>{{ $l }}</option>@endforeach</select></div>
    @include('partials.store-filter')
    <button class="btn btn-secondary" type="submit">Filtrer</button>
</form>
<div class="card flush">
    <div class="table-wrap"><table class="table">
        <thead><tr><th>N°</th><th>Date</th><th>Client</th><th class="hide-mobile">Magasin</th><th>Statut</th><th class="num">Total</th><th></th></tr></thead>
        <tbody>
        @forelse ($quotes as $q)
            <tr>
                <td><a class="mono strong" href="{{ route('quotes.show', $q) }}">{{ $q->number }}</a></td>
                <td>{{ $q->issued_at->format('d/m/Y') }}@if($q->valid_until)<div class="muted small">valable → {{ $q->valid_until->format('d/m/Y') }}</div>@endif</td>
                <td>{{ $q->customer->name }}</td>
                <td class="hide-mobile">{{ $q->store->name }}</td>
                <td>{!! badge($q->status) !!}</td>
                <td class="num">{{ money($q->total) }}</td>
                <td class="text-right"><a class="btn btn-ghost btn-sm" href="{{ route('quotes.pdf', $q) }}" title="Télécharger le PDF"><x-icon name="download" /></a></td>
            </tr>
        @empty
            <tr><td colspan="7" class="empty">Aucun devis.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
{{ $quotes->links() }}
@endsection
