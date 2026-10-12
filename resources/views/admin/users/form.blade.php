@extends('layouts.app')
@section('title', $user->exists ? 'Modifier l\'utilisateur' : 'Nouvel utilisateur')
@section('content')
@php $self = $user->is(auth()->user()); @endphp
<div class="page-header"><h1>{{ $user->exists ? 'Modifier '.$user->name : 'Nouvel utilisateur' }}</h1></div>
<form class="card" method="post" action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}" style="max-width:760px" autocomplete="off">
    @csrf
    @if ($user->exists) @method('PUT') @endif
    <div class="form-grid">
        <x-field name="name" label="Nom complet" :value="$user->name" required />
        <x-field name="email" label="E-mail (identifiant)" type="email" :value="$user->email" required />
        <x-field name="phone" label="Téléphone" :value="$user->phone" />
        <x-field name="role" label="Profil" type="select" :options="\App\Enums\Role::options()" :value="$user->role" required :disabled="$self" />
        @if ($self)<input type="hidden" name="role" value="{{ $user->role->value }}">@endif
        <x-field name="store_id" label="Magasin" type="select" :options="$stores->pluck('name', 'id')" :value="$user->store_id" placeholder="— Tous (direction) —" help="Obligatoire sauf pour la direction." />
        <div class="field"><label>&nbsp;</label><label class="checkbox"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active)) @disabled($self)> Compte actif</label>@if($self)<input type="hidden" name="is_active" value="1">@endif</div>
        <x-field name="password" label="{{ $user->exists ? 'Nouveau mot de passe' : 'Mot de passe' }}" type="password" :required="! $user->exists" autocomplete="new-password" help="8 caractères minimum, avec lettres et chiffres." />
        <x-field name="password_confirmation" label="Confirmation" type="password" :required="! $user->exists" autocomplete="new-password" />
    </div>
    <div class="form-actions"><a class="btn btn-secondary" href="{{ route('users.index') }}">Annuler</a><button class="btn" type="submit">Enregistrer</button></div>
</form>
@endsection
