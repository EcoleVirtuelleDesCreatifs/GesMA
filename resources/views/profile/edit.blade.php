@extends('layouts.app')
@section('title', 'Mon profil')
@section('content')
<div class="page-header"><div><h1>Mon profil</h1><p class="subtitle">{{ $user->role->label() }}@if($user->store) · {{ $user->store->name }}@endif</p></div></div>
<form class="card" method="post" action="{{ route('profile.update') }}" style="max-width:640px">
    @csrf @method('PUT')
    <div class="form-grid">
        <x-field name="name" label="Nom" :value="$user->name" required />
        <x-field name="phone" label="Téléphone" :value="$user->phone" />
        <div class="field full"><label>E-mail</label><div>{{ $user->email }}</div></div>
    </div>
    <fieldset>
        <legend>Changer de mot de passe</legend>
        <x-field name="current_password" label="Mot de passe actuel" type="password" autocomplete="current-password" />
        <div class="form-grid">
            <x-field name="password" label="Nouveau mot de passe" type="password" autocomplete="new-password" />
            <x-field name="password_confirmation" label="Confirmation" type="password" autocomplete="new-password" />
        </div>
    </fieldset>
    <div class="form-actions"><button class="btn" type="submit">Enregistrer</button></div>
</form>
@endsection
