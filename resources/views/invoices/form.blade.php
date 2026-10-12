@extends('layouts.app')
@section('title', 'Nouvelle facture')
@section('content')
<div class="page-header"><div><h1>Nouvelle facture</h1><p class="subtitle">Astuce : une facture peut aussi être créée directement depuis une commande ou un devis.</p></div></div>
<form method="post" action="{{ route('invoices.store') }}" autocomplete="off">
    @csrf
    <x-document-form :document="$invoice" :customer="$customer" :products="$products" :stores="$stores">
        <x-slot:dates>
            <x-field name="issued_at" label="Date de facture" type="date" :value="$invoice->issued_at" required />
            <x-field name="due_at" label="Échéance" type="date" :value="$invoice->due_at" />
        </x-slot:dates>
        <x-slot:extra>
            <x-field name="amount_paid" label="Montant déjà payé (F CFA)" type="number" min="0" value="0" />
        </x-slot:extra>
        <x-slot:actions>
            <a class="btn btn-secondary" href="{{ route('invoices.index') }}">Annuler</a>
            <button class="btn" type="submit">Créer la facture</button>
        </x-slot:actions>
    </x-document-form>
</form>
@endsection
