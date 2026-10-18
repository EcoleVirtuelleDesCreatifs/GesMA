@extends('layouts.app')
@section('title', 'Magasins')
@section('content')
<div class="page-head">
    <h1>Magasins</h1>
    <div class="actions"><a class="btn btn-primary" href="{{ route('stores.create') }}">+ Nouveau magasin</a></div>
</div>
<div class="card">
    <div class="table-wrap">
        <table class="table responsive">
            <thead><tr><th>Code</th><th>Nom</th><th>Ville</th><th>Téléphone</th><th class="right">Utilisateurs</th><th class="right">Commandes</th><th>État</th><th></th></tr></thead>
            <tbody>
            @foreach($stores as $s)
                <tr>
                    <td data-label="Code"><strong>{{ $s->code }}</strong></td>
                    <td data-label="Nom">{{ $s->name }}</td>
                    <td data-label="Ville">{{ $s->city }}</td>
                    <td data-label="Téléphone">{{ $s->phone ?? '—' }}</td>
                    <td data-label="Utilisateurs" class="right">{{ $s->users_count }}</td>
                    <td data-label="Commandes" class="right">{{ $s->orders_count }}</td>
                    <td data-label="État"><span class="badge {{ $s->is_active ? 'badge-success' : 'badge-muted' }}">{{ $s->is_active ? 'Actif' : 'Fermé' }}</span></td>
                    <td data-label="" class="actions">
                        <a class="btn btn-sm" href="{{ route('dashboard', ['store_id' => $s->id]) }}">Résultats</a>
                        <a class="btn btn-sm" href="{{ route('stores.edit', $s) }}">Modifier</a>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
