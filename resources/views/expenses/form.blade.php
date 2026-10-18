@extends('layouts.app')
@section('title', $expense->exists ? 'Modifier la dépense' : 'Nouvelle dépense')
@section('content')
<div class="page-head"><h1>{{ $expense->exists ? 'Modifier la dépense' : 'Nouvelle dépense' }}</h1></div>
<div class="card" style="max-width: 680px">
    <form method="POST" action="{{ $expense->exists ? route('expenses.update', $expense) : route('expenses.store') }}">
        @csrf
        @if($expense->exists) @method('PUT') @endif
        <div class="form-grid">
            <div class="field"><label for="spent_at">Date *</label><input type="date" name="spent_at" id="spent_at" max="{{ today()->toDateString() }}" value="{{ old('spent_at', $expense->spent_at?->toDateString()) }}" required></div>
            <div class="field"><label for="amount">Montant ({{ config('gesma.company.currency') }}) *</label><input type="number" min="1" name="amount" id="amount" value="{{ old('amount', $expense->amount ? (int) $expense->amount : '') }}" required></div>
            <div class="field">
                <label for="category">Catégorie *</label>
                <select name="category" id="category" required>
                    <option value="">— Choisir —</option>
                    @foreach(config('gesma.expense_categories') as $k => $l)<option value="{{ $k }}" @selected(old('category', $expense->category) === $k)>{{ $l }}</option>@endforeach
                </select>
            </div>
            @if(auth()->user()->isDirection())
                <div class="field">
                    <label for="store_id">Magasin *</label>
                    <select name="store_id" id="store_id" required>
                        @foreach($stores as $s)<option value="{{ $s->id }}" @selected(old('store_id', $expense->store_id) == $s->id)>{{ $s->name }}</option>@endforeach
                    </select>
                </div>
            @endif
            <div class="field full"><label for="description">Description</label><input type="text" name="description" id="description" maxlength="255" value="{{ old('description', $expense->description) }}" placeholder="Ex. : Carburant camion de livraison"></div>
        </div>
        <div class="form-actions">
            @if($expense->exists)
                <button form="delete-expense" class="btn btn-danger" style="margin-right:auto">Supprimer</button>
            @endif
            <a class="btn" href="{{ route('expenses.index') }}">Annuler</a>
            <button class="btn btn-primary">Enregistrer</button>
        </div>
    </form>
    @if($expense->exists)
        <form id="delete-expense" method="POST" action="{{ route('expenses.destroy', $expense) }}" onsubmit="return confirm('Supprimer cette dépense ?')">@csrf @method('DELETE')</form>
    @endif
</div>
@endsection
