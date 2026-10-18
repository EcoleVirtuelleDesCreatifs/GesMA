@extends('layouts.app')
@section('title', $store->exists ? 'Modifier le magasin' : 'Nouveau magasin')
@section('content')
<div class="page-head"><h1>{{ $store->exists ? $store->name : 'Nouveau magasin' }}</h1></div>
<div class="card" style="max-width: 720px">
    <form method="POST" action="{{ $store->exists ? route('stores.update', $store) : route('stores.store') }}">
        @csrf
        @if($store->exists) @method('PUT') @endif
        <div class="form-grid">
            <div class="field"><label for="code">Code *</label><input type="text" name="code" id="code" maxlength="10" value="{{ old('code', $store->code) }}" required placeholder="Ex. : YAM"><div class="hint">Court et unique, utilisé par le site web.</div></div>
            <div class="field"><label for="city">Ville *</label><input type="text" name="city" id="city" value="{{ old('city', $store->city) }}" required></div>
            <div class="field full"><label for="name">Nom *</label><input type="text" name="name" id="name" value="{{ old('name', $store->name) }}" required></div>
            <div class="field"><label for="address">Adresse</label><input type="text" name="address" id="address" value="{{ old('address', $store->address) }}"></div>
            <div class="field"><label for="phone">Téléphone</label><input type="tel" name="phone" id="phone" value="{{ old('phone', $store->phone) }}"></div>
            <div class="field full">
                <input type="hidden" name="is_active" value="0">
                <label class="checkbox"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $store->is_active))> Magasin actif</label>
            </div>
        </div>
        @unless($store->exists)<p class="muted" style="font-size:.85rem">Le stock de chaque produit sera initialisé à zéro pour ce magasin.</p>@endunless
        <div class="form-actions"><a class="btn" href="{{ route('stores.index') }}">Annuler</a><button class="btn btn-primary">Enregistrer</button></div>
    </form>
</div>
@endsection
