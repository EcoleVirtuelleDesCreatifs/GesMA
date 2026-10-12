@extends('layouts.app')
@section('title', $store->exists ? 'Modifier le magasin' : 'Nouveau magasin')
@section('content')
<div class="page-header"><h1>{{ $store->exists ? 'Modifier '.$store->name : 'Nouveau magasin' }}</h1></div>
<form class="card" method="post" action="{{ $store->exists ? route('stores.update', $store) : route('stores.store') }}" style="max-width:760px">
    @csrf
    @if ($store->exists) @method('PUT') @endif
    <div class="form-grid">
        <x-field name="name" label="Nom du magasin" :value="$store->name" required placeholder="Mobilier Addict Bouaké" />
        <x-field name="code" label="Code court" :value="$store->code" required maxlength="10" help="Ex. : BKE. Utilisé par le site web et dans les exports." />
        <x-field name="city" label="Ville" :value="$store->city" required />
        <x-field name="phone" label="Téléphone" :value="$store->phone" />
        <x-field name="address" label="Adresse" :value="$store->address" class="full" />
        <x-field name="email" label="E-mail" type="email" :value="$store->email" />
        <div class="field"><label>&nbsp;</label><label class="checkbox"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $store->is_active))> Magasin actif</label></div>
    </div>
    <div class="form-actions"><a class="btn btn-secondary" href="{{ route('stores.index') }}">Annuler</a><button class="btn" type="submit">Enregistrer</button></div>
</form>
@endsection
