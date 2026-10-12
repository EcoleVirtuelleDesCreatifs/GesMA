@extends('layouts.app')
@section('title', 'Catégories de dépenses')
@section('content')
<div class="page-header"><h1>Catégories de dépenses</h1></div>
<div class="grid grid-2-1">
    <div class="card flush">
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Catégorie</th><th class="num">Dépenses</th></tr></thead>
            <tbody>
            @foreach ($categories as $c)
                <tr>
                    <td><form method="post" action="{{ route('expense-categories.update', $c) }}" style="display:flex;gap:.4rem">@csrf @method('PUT')<input type="text" name="name" value="{{ $c->name }}" required><button class="btn btn-secondary btn-sm" type="submit">OK</button></form></td>
                    <td class="num">{{ $c->expenses_count }}</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    </div>
    <form class="card" method="post" action="{{ route('expense-categories.store') }}">
        @csrf
        <h2>Nouvelle catégorie</h2>
        <x-field name="name" label="Nom" required />
        <button class="btn btn-block" type="submit">Ajouter</button>
    </form>
</div>
@endsection
