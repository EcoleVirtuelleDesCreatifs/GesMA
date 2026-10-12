@extends('layouts.app')
@section('title', 'Magasins')
@section('content')
<div class="page-header">
    <div><h1>Magasins</h1><p class="subtitle">Chaque magasin dispose de ses propres stocks, commandes, ventes, livraisons et dépenses.</p></div>
    <div class="actions"><a class="btn" href="{{ route('stores.create') }}"><x-icon name="plus" /> Nouveau magasin</a></div>
</div>
<div class="card flush">
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Code</th><th>Magasin</th><th>Ville</th><th>Contact</th><th class="num">Utilisateurs</th><th class="num">Commandes</th><th class="num">Articles en stock</th><th>État</th><th></th></tr></thead>
        <tbody>
        @foreach ($stores as $s)
            <tr>
                <td class="mono strong">{{ $s->code }}</td>
                <td><a href="{{ route('dashboard', ['store_id' => $s->id]) }}">{{ $s->name }}</a><div class="muted small">{{ $s->address }}</div></td>
                <td>{{ $s->city }}</td>
                <td class="small">{{ $s->phone }}<br>{{ $s->email }}</td>
                <td class="num">{{ $s->users_count }}</td>
                <td class="num">{{ $s->orders_count }}</td>
                <td class="num">{{ (int) $s->stocks_sum_quantity }}</td>
                <td>{!! $s->is_active ? '<span class="badge badge-success">Actif</span>' : '<span class="badge badge-muted">Inactif</span>' !!}</td>
                <td class="text-right"><a class="btn btn-ghost btn-sm" href="{{ route('stores.edit', $s) }}"><x-icon name="edit" /></a></td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
</div>
@endsection
