@extends('layouts.app')
@section('title', 'Catégories de produits')
@section('content')
<div class="page-header"><div><h1>Catégories de produits</h1></div><div class="actions"><a class="btn btn-secondary" href="{{ route('products.index') }}">Produits</a></div></div>
<div class="grid grid-2-1">
    <div class="card flush">
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Catégorie</th><th class="num">Produits</th><th></th></tr></thead>
            <tbody>
            @forelse ($categories as $c)
                <tr>
                    <td>
                        <form method="post" action="{{ route('categories.update', $c) }}" style="display:flex;gap:.4rem">@csrf @method('PUT')
                            <input type="text" name="name" value="{{ $c->name }}" required>
                            <input type="text" name="description" value="{{ $c->description }}" placeholder="Description" class="hide-mobile">
                            <button class="btn btn-secondary btn-sm" type="submit">OK</button>
                        </form>
                    </td>
                    <td class="num">{{ $c->products_count }}</td>
                    <td class="text-right">@if(! $c->products_count)<form method="post" action="{{ route('categories.destroy', $c) }}" data-confirm="Supprimer cette catégorie ?">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm" type="submit"><x-icon name="trash" /></button></form>@endif</td>
                </tr>
            @empty
                <tr><td colspan="3" class="empty">Aucune catégorie.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>
    <form class="card" method="post" action="{{ route('categories.store') }}">
        @csrf
        <h2>Nouvelle catégorie</h2>
        <x-field name="name" label="Nom" required />
        <x-field name="description" label="Description" />
        <button class="btn btn-block" type="submit">Ajouter</button>
    </form>
</div>
@endsection
