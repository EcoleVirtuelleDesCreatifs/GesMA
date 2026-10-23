@extends('layouts.app')
@section('title', 'Fournisseurs')
@section('content')
<div class="page-head">
    <h1>Fournisseurs</h1>
    <div class="actions">@can('suppliers.manage')<a class="btn btn-primary" href="{{ route('suppliers.create') }}">+ Nouveau fournisseur</a>@endcan</div>
</div>

<form method="GET" class="filters card">
    <div class="field"><label for="q">Recherche</label><input type="search" id="q" name="q" value="{{ request('q') }}" placeholder="Nom, code, contact, téléphone" autofocus></div>
    <div class="field">
        <label for="type">Type</label>
        <select name="type" id="type"><option value="">Tous</option>
            @foreach($types as $k => $l)<option value="{{ $k }}" @selected(request('type') === $k)>{{ $l }}</option>@endforeach
        </select>
    </div>
    <div class="field">
        <label for="status">Statut</label>
        <select name="status" id="status"><option value="">Tous</option>
            <option value="actif" @selected(request('status') === 'actif')>Actif</option>
            <option value="inactif" @selected(request('status') === 'inactif')>Archivé</option>
        </select>
    </div>
    <button class="btn btn-sm">Rechercher</button>
</form>

<div class="card">
    <div class="table-wrap">
        <table class="table responsive">
            <thead><tr><th>Code</th><th>Fournisseur</th><th>Contact</th><th class="right">Produits</th><th class="right">Dette</th><th>Statut</th><th></th></tr></thead>
            <tbody>
            @forelse($suppliers as $s)
                <tr>
                    <td data-label="Code" class="nowrap">{{ $s->code }}</td>
                    <td data-label="Fournisseur"><a href="{{ route('suppliers.show', $s) }}"><strong>{{ $s->name }}</strong></a>@if($s->type)<br><small class="muted">{{ $s->typeLabel() }}</small>@endif</td>
                    <td data-label="Contact">{{ $s->contact_name ?? '—' }}@if($s->phone)<br><small class="muted">{{ $s->phone }}</small>@endif</td>
                    <td data-label="Produits" class="right num">{{ $s->products_count }}</td>
                    <td data-label="Dette" class="right num">@money(max(0, $debts[$s->id] ?? 0))</td>
                    <td data-label="Statut"><span class="badge {{ $s->is_active ? 'badge-success' : 'badge-muted' }}">{{ $s->is_active ? 'Actif' : 'Archivé' }}</span></td>
                    <td data-label="" class="actions">
                        <a class="btn btn-sm" href="{{ route('suppliers.show', $s) }}">Voir</a>
                        @can('suppliers.manage')<a class="btn btn-sm" href="{{ route('suppliers.edit', $s) }}">Modifier</a>@endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty">Aucun fournisseur.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $suppliers->links() }}
</div>
@endsection
