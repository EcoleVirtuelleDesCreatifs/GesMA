@extends('layouts.app')
@section('title', $quote->exists ? 'Modifier le devis' : 'Nouveau devis')
@section('content')
<div class="page-header"><div><h1>{{ $quote->exists ? 'Modifier le devis '.$quote->number : 'Nouveau devis' }}</h1></div></div>
<form method="post" action="{{ $quote->exists ? route('quotes.update', $quote) : route('quotes.store') }}" autocomplete="off">
    @csrf
    @if ($quote->exists) @method('PUT') @endif
    <x-document-form :document="$quote" :customer="$customer" :products="$products" :stores="$stores">
        <x-slot:dates>
            <x-field name="issued_at" label="Date du devis" type="date" :value="$quote->issued_at" required />
            <x-field name="valid_until" label="Valable jusqu'au" type="date" :value="$quote->valid_until" />
        </x-slot:dates>
        <x-slot:actions>
            <a class="btn btn-secondary" href="{{ $quote->exists ? route('quotes.show', $quote) : route('quotes.index') }}">Annuler</a>
            <button class="btn" type="submit">Enregistrer le devis</button>
        </x-slot:actions>
    </x-document-form>
</form>
@endsection
