@extends('layouts.app')
@section('title', 'Produits')
@section('content')
<div class="page-head">
    <h1>Produits</h1>
    <div class="actions">
        @can('products.manage')<a class="btn btn-primary" href="{{ route('products.create') }}">+ Nouveau produit</a>@endcan
    </div>
</div>

<form method="GET" class="filters card">
    <div class="field"><label for="q">Recherche</label><input type="search" id="q" name="q" value="{{ request('q') }}" placeholder="Nom ou référence"></div>
    <div class="field">
        <label for="category_id">Catégorie</label>
        <select name="category_id" id="category_id">
            <option value="">Toutes</option>
            @foreach($categories as $c)<option value="{{ $c->id }}" @selected(request('category_id') == $c->id)>{{ $c->name }}</option>@endforeach
        </select>
    </div>
    <div class="field">
        <label for="availability">Disponibilité</label>
        <select name="availability" id="availability">
            <option value="">Tous</option>
            <option value="available" @selected(request('availability') === 'available')>Disponibles</option>
            <option value="unavailable" @selected(request('availability') === 'unavailable')>Indisponibles</option>
        </select>
    </div>
    @include('partials.store-filter')
    <label class="checkbox"><input type="checkbox" name="low" value="1" @checked(request('low'))> Stock faible</label>
    <button class="btn btn-sm" type="submit">Filtrer</button>
</form>

<div class="card">
    <div class="table-wrap">
        <table class="table responsive">
            <thead><tr><th>Référence</th><th>Produit</th><th>Catégorie</th><th class="right">Prix</th><th class="right">Stock {{ $storeId ? '' : '(total)' }}</th><th>État</th>@can('products.manage')<th></th>@endcan</tr></thead>
            <tbody>
            @forelse($products as $p)
                @php($qty = $p->stocks->sum('quantity'))
                @php($low = $p->stocks->contains(fn ($s) => $s->isLow()))
                <tr>
                    <td data-label="Référence" class="nowrap">{{ $p->reference }}</td>
                    <td data-label="Produit"><a href="{{ route('products.show', $p) }}"><strong>{{ $p->name }}</strong></a></td>
                    <td data-label="Catégorie">{{ $p->category?->name ?? '—' }}</td>
                    <td data-label="Prix" class="right num">@money($p->price)</td>
                    <td data-label="Stock" class="right num">
                        {{ $qty }}
                        @if($low)<span class="badge badge-warning" title="Au moins un magasin sous le seuil">faible</span>@endif
                    </td>
                    <td data-label="État">
                        <span class="badge {{ $p->is_available ? 'badge-success' : 'badge-muted' }}">{{ $p->is_available ? 'Disponible' : 'Indisponible' }}</span>
                    </td>
                    @can('products.manage')
                        <td data-label="" class="actions">
                            <form method="POST" action="{{ route('products.destroy', $p) }}" class="inline-form" onsubmit="return confirm('Supprimer le produit « {{ $p->name }} » ? S’il a déjà été vendu, il sera seulement marqué indisponible.')">
                                @csrf @method('DELETE')
                                <button class="btn btn-danger btn-sm">Supprimer</button>
                            </form>
                        </td>
                    @endcan
                </tr>
            @empty
                <tr><td colspan="7" class="empty">Aucun produit trouvé.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $products->links() }}
</div>
@endsection
