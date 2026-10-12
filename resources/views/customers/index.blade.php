@extends('layouts.app')
@section('title', 'Clients')
@section('content')
<div class="page-header">
    <div><h1>Clients</h1><p class="subtitle">{{ $customers->total() }} client(s)</p></div>
    <div class="actions">
        <a class="btn btn-secondary" href="{{ route('customers.top') }}">Meilleurs clients</a>
        @can('customers.manage')<a class="btn" href="{{ route('customers.create') }}"><x-icon name="plus" /> Nouveau client</a>@endcan
    </div>
</div>
<form method="get" class="filters">
    <div class="field grow"><label for="q">Recherche</label><input type="search" id="q" name="q" value="{{ request('q') }}" placeholder="Nom, téléphone, e-mail, ville" autofocus></div>
    <div class="field"><label for="city">Ville</label><select name="city" id="city"><option value="">Toutes</option>@foreach ($cities as $c)<option @selected(request('city') === $c)>{{ $c }}</option>@endforeach</select></div>
    <div class="field"><label for="sort">Trier par</label>
        <select name="sort" id="sort">
            @foreach (['recent' => 'Plus récents', 'name' => 'Nom', 'orders' => 'Nombre de commandes', 'spent' => 'Montant des achats'] as $k => $l)<option value="{{ $k }}" @selected(request('sort', 'recent') === $k)>{{ $l }}</option>@endforeach
        </select>
    </div>
    <button class="btn btn-secondary" type="submit"><x-icon name="search" /> Rechercher</button>
</form>
<div class="card flush">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Client</th><th>Téléphone</th><th class="hide-mobile">Ville</th><th class="num">Commandes</th><th class="num">Achats</th></tr></thead>
            <tbody>
            @forelse ($customers as $c)
                <tr>
                    <td><a class="strong" href="{{ route('customers.show', $c) }}">{{ $c->name }}</a><div class="muted small">{{ $c->email }}</div></td>
                    <td>@if($c->phone)<a href="tel:{{ $c->phone }}">{{ $c->phone }}</a>@endif</td>
                    <td class="hide-mobile">{{ $c->city }}</td>
                    <td class="num">{{ $c->orders_count }}</td>
                    <td class="num">{{ money($c->total_spent) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">Aucun client trouvé.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
{{ $customers->links() }}
@endsection
