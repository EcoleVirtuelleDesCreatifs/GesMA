@extends('layouts.app')
@section('title', 'Devis & factures')
@section('content')
<div class="page-head">
    <h1>Devis & factures</h1>
    <div class="actions">
        @can('documents.manage')
            <a class="btn" href="{{ route('documents.create', ['type' => 'devis']) }}">+ Devis</a>
            <a class="btn btn-primary" href="{{ route('documents.create', ['type' => 'facture']) }}">+ Facture</a>
        @endcan
    </div>
</div>
<form method="GET" class="filters card">
    <div class="field"><label for="q">Recherche</label><input type="search" name="q" id="q" value="{{ request('q') }}" placeholder="Numéro ou client"></div>
    <div class="field">
        <label for="type">Type</label>
        <select name="type" id="type">
            <option value="">Tous</option>
            @foreach(\App\Models\Document::TYPES as $k => $l)<option value="{{ $k }}" @selected(request('type') === $k)>{{ $l }}</option>@endforeach
        </select>
    </div>
    @include('partials.store-filter')
    <button class="btn btn-sm">Filtrer</button>
</form>
<div class="card">
    <div class="table-wrap">
        <table class="table responsive">
            <thead><tr><th>Numéro</th><th>Type</th><th>Date</th><th>Client</th><th>Magasin</th><th class="right">Montant</th><th></th></tr></thead>
            <tbody>
            @forelse($documents as $d)
                <tr>
                    <td data-label="Numéro"><a href="{{ route('documents.show', $d) }}"><strong>{{ $d->number }}</strong></a></td>
                    <td data-label="Type"><span class="badge {{ $d->isQuote() ? 'badge-info' : 'badge-primary' }}">{{ $d->typeLabel() }}</span></td>
                    <td data-label="Date">{{ $d->issued_at->format('d/m/Y') }}</td>
                    <td data-label="Client">{{ $d->customer->name }}</td>
                    <td data-label="Magasin">{{ $d->store->city }}</td>
                    <td data-label="Montant" class="right num">@money($d->total)</td>
                    <td data-label="" class="actions">
                        <a class="btn btn-sm" href="{{ route('documents.pdf', $d) }}">PDF</a>
                        <a class="btn btn-sm btn-whatsapp" href="{{ whatsapp_document_link($d) }}" target="_blank" rel="noopener">WhatsApp</a>
                        @can('documents.delete')
                            <form method="POST" action="{{ route('documents.destroy', $d) }}" class="inline-form" onsubmit="return confirm('Supprimer {{ $d->typeLabel() }} {{ $d->number }} ? Cette action est définitive.')">
                                @csrf @method('DELETE')
                                <button class="btn btn-danger btn-sm">Supprimer</button>
                            </form>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty">Aucun document.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $documents->links() }}
</div>
@endsection
