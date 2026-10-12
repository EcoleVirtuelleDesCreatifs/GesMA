@extends('layouts.app')
@section('title', $customer->exists ? 'Modifier le client' : 'Nouveau client')
@section('content')
<div class="page-header"><h1>{{ $customer->exists ? 'Modifier '.$customer->name : 'Nouveau client' }}</h1></div>
<form class="card" method="post" action="{{ $customer->exists ? route('customers.update', $customer) : route('customers.store') }}" style="max-width:820px">
    @csrf
    @if ($customer->exists) @method('PUT') @endif
    <input type="hidden" name="redirect" value="{{ request('redirect') }}">
    <div class="form-grid">
        <x-field name="name" label="Nom complet" :value="$customer->name" required class="full" />
        <x-field name="phone" label="Téléphone" type="tel" :value="$customer->phone" help="Sert à retrouver rapidement le client et à éviter les doublons." />
        <x-field name="phone_alt" label="Autre téléphone" type="tel" :value="$customer->phone_alt" />
        <x-field name="email" label="E-mail" type="email" :value="$customer->email" />
        <x-field name="city" label="Ville" :value="$customer->city" />
        <x-field name="address" label="Adresse" :value="$customer->address" class="full" />
        <x-field name="notes" label="Notes" type="textarea" :value="$customer->notes" class="full" />
    </div>
    <div class="form-actions">
        <a class="btn btn-secondary" href="{{ $customer->exists ? route('customers.show', $customer) : route('customers.index') }}">Annuler</a>
        <button class="btn" type="submit">Enregistrer</button>
    </div>
</form>
@endsection
