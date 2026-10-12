@extends('layouts.app')
@section('title', 'Inventaire')
@section('content')
<div class="page-header">
    <div><h1>Inventaire et corrections de stock</h1><p class="subtitle">Saisissez les quantités réellement comptées. Seuls les écarts sont enregistrés (action historisée).</p></div>
</div>
<form method="get" class="filters">
    @if ($stores->count() > 1)
        <div class="field"><label for="store_id">Magasin</label><select name="store_id" id="store_id" onchange="this.form.submit()">@foreach ($stores as $s)<option value="{{ $s->id }}" @selected($storeId == $s->id)>{{ $s->name }}</option>@endforeach</select></div>
    @endif
    <div class="field grow"><label for="q">Produit</label><input type="search" name="q" id="q" value="{{ request('q') }}"></div>
    <div class="field"><label for="category_id">Catégorie</label><select id="category_id" name="category_id"><option value="">Toutes</option>@foreach ($categories as $c)<option value="{{ $c->id }}" @selected(request('category_id') == $c->id)>{{ $c->name }}</option>@endforeach</select></div>
    <button class="btn btn-secondary" type="submit">Filtrer</button>
</form>
<form method="post" action="{{ route('stock.correct') }}" data-confirm="Enregistrer les corrections de stock ?">
    @csrf
    <input type="hidden" name="store_id" value="{{ $storeId }}">
    <div class="card flush">
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Produit</th><th class="num">Stock enregistré</th><th style="width:160px">Quantité comptée</th></tr></thead>
            <tbody>
            @forelse ($products as $p)
                <tr>
                    <td>{{ $p->name }}<div class="muted small mono">{{ $p->reference }}</div></td>
                    <td class="num">{{ $p->stocks->first()?->quantity ?? 0 }}</td>
                    <td><input type="number" min="0" name="counts[{{ $p->id }}]" placeholder="—"></td>
                </tr>
            @empty
                <tr><td colspan="3" class="empty">Aucun produit.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>
    <div class="card">
        <x-field name="reason" label="Motif de la correction" required placeholder="Ex. : inventaire mensuel, casse constatée…" />
        <div class="form-actions"><button class="btn" type="submit">Enregistrer les écarts</button></div>
    </div>
</form>
@endsection
