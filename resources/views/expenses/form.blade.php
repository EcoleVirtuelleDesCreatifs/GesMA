@extends('layouts.app')
@section('title', $expense->exists ? 'Modifier la dépense' : 'Nouvelle dépense')
@section('content')
<div class="page-header"><h1>{{ $expense->exists ? 'Modifier la dépense' : 'Nouvelle dépense' }}</h1></div>
<form class="card" method="post" action="{{ $expense->exists ? route('expenses.update', $expense) : route('expenses.store') }}" style="max-width:760px">
    @csrf
    @if ($expense->exists) @method('PUT') @endif
    <div class="form-grid">
        <x-field name="spent_at" label="Date" type="date" :value="$expense->spent_at" required :max="today()->toDateString()" />
        <x-field name="amount" label="Montant (F CFA)" type="number" min="1" :value="$expense->amount" required />
        <x-field name="expense_category_id" label="Catégorie" type="select" :options="$categories->pluck('name', 'id')" :value="$expense->expense_category_id" placeholder="— Choisir —" required />
        @include('partials.store-select', ['value' => $expense->store_id])
        <x-field name="description" label="Description" :value="$expense->description" required class="full" placeholder="Ex. : carburant camion de livraison" />
        <x-field name="payment_method" label="Mode de paiement" type="select" :options="config('gesma.payment_methods')" :value="$expense->payment_method" placeholder="—" />
    </div>
    <div class="form-actions"><a class="btn btn-secondary" href="{{ route('expenses.index') }}">Annuler</a><button class="btn" type="submit">Enregistrer</button></div>
</form>
@endsection
