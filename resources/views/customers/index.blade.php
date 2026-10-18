@extends('layouts.app')
@section('title', 'Clients')
@section('content')
<div class="page-head">
    <h1>Clients</h1>
    <div class="actions">@can('customers.manage')<a class="btn btn-primary" href="{{ route('customers.create') }}">+ Nouveau client</a>@endcan</div>
</div>

<div class="grid grid-2 mb">
    <div class="card">
        <div class="card-head"><h2>Commandent le plus souvent</h2></div>
        @forelse($topByCount as $c)
            @if($loop->first)<ul class="list">@endif
            <li><a href="{{ route('customers.show', $c->customer) }}">{{ $c->customer->name }}</a><span class="badge badge-primary">{{ $c->orders_count }} commande(s)</span></li>
            @if($loop->last)</ul>@endif
        @empty <p class="empty">Pas encore de commande.</p> @endforelse
    </div>
    <div class="card">
        <div class="card-head"><h2>Achats les plus importants</h2></div>
        @forelse($topByAmount as $c)
            @if($loop->first)<ul class="list">@endif
            <li><a href="{{ route('customers.show', $c->customer) }}">{{ $c->customer->name }}</a><strong class="num">@money($c->amount)</strong></li>
            @if($loop->last)</ul>@endif
        @empty <p class="empty">Pas encore de commande.</p> @endforelse
    </div>
</div>

<form method="GET" class="filters card">
    <div class="field"><label for="q">Recherche</label><input type="search" id="q" name="q" value="{{ request('q') }}" placeholder="Nom, téléphone ou e-mail" autofocus></div>
    <div class="field">
        <label for="city">Ville</label>
        <select name="city" id="city">
            <option value="">Toutes</option>
            @foreach($cities as $city)<option @selected(request('city') === $city)>{{ $city }}</option>@endforeach
        </select>
    </div>
    <div class="field">
        <label for="sort">Trier par</label>
        <select name="sort" id="sort">
            <option value="name" @selected(request('sort', 'name') === 'name')>Nom</option>
            <option value="frequency" @selected(request('sort') === 'frequency')>Nombre de commandes</option>
            <option value="amount" @selected(request('sort') === 'amount')>Montant des achats</option>
            <option value="recent" @selected(request('sort') === 'recent')>Dernière commande</option>
        </select>
    </div>
    @include('partials.store-filter')
    <button class="btn btn-sm">Rechercher</button>
</form>

<div class="card">
    <div class="table-wrap">
        <table class="table responsive">
            <thead><tr><th>Client</th><th>Téléphone</th><th>Ville</th><th class="right">Commandes</th><th class="right">Montant</th><th>Dernière commande</th>@can('customers.delete')<th></th>@endcan</tr></thead>
            <tbody>
            @forelse($customers as $c)
                <tr>
                    <td data-label="Client"><a href="{{ route('customers.show', $c) }}"><strong>{{ $c->name }}</strong></a>@if($c->email)<br><small class="muted">{{ $c->email }}</small>@endif</td>
                    <td data-label="Téléphone" class="nowrap">@if($c->phone)<a href="tel:{{ $c->phone }}">{{ $c->phone }}</a>@else — @endif</td>
                    <td data-label="Ville">{{ $c->city ?? '—' }}</td>
                    <td data-label="Commandes" class="right num">{{ $c->orders_count }}</td>
                    <td data-label="Montant" class="right num">@money($c->amount)</td>
                    <td data-label="Dernière commande" class="nowrap">{{ $c->last_order ? \Illuminate\Support\Carbon::parse($c->last_order)->format('d/m/Y') : '—' }}</td>
                    @can('customers.delete')
                        <td data-label="" class="actions">
                            <form method="POST" action="{{ route('customers.destroy', $c) }}" class="inline-form" onsubmit="return confirm('Supprimer le client {{ $c->name }} ?@if($c->orders_count > 0) Ses {{ $c->orders_count }} commande(s) seront conservées sans lien client.@endif')">
                                @csrf @method('DELETE')
                                <button class="btn btn-danger btn-sm">Supprimer</button>
                            </form>
                        </td>
                    @endcan
                </tr>
            @empty
                <tr><td colspan="7" class="empty">Aucun client trouvé.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $customers->links() }}
</div>
@endsection
