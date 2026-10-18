@extends('layouts.app')
@section('title', $user->exists ? 'Modifier le compte' : 'Nouveau compte')
@section('content')
<div class="page-head"><h1>{{ $user->exists ? $user->name : 'Nouveau compte utilisateur' }}</h1></div>
<div class="card" style="max-width: 760px">
    <form method="POST" action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}">
        @csrf
        @if($user->exists) @method('PUT') @endif
        <div class="form-grid">
            <div class="field"><label for="name">Nom *</label><input type="text" name="name" id="name" value="{{ old('name', $user->name) }}" required></div>
            <div class="field"><label for="email">E-mail (identifiant) *</label><input type="email" name="email" id="email" value="{{ old('email', $user->email) }}" required></div>
            <div class="field"><label for="phone">Téléphone</label><input type="tel" name="phone" id="phone" value="{{ old('phone', $user->phone) }}"></div>
            <div class="field">
                <label for="role">Profil *</label>
                <select name="role" id="role" required>
                    @foreach(config('gesma.roles') as $k => $l)<option value="{{ $k }}" @selected(old('role', $user->role) === $k)>{{ $l }}</option>@endforeach
                </select>
            </div>
            <div class="field">
                <label for="store_id">Magasin</label>
                <select name="store_id" id="store_id">
                    <option value="">— Tous (Direction) —</option>
                    @foreach($stores as $s)<option value="{{ $s->id }}" @selected(old('store_id', $user->store_id) == $s->id)>{{ $s->name }}</option>@endforeach
                </select>
                <div class="hint">Obligatoire sauf pour la Direction.</div>
            </div>
            <div class="field">
                <label>&nbsp;</label>
                <input type="hidden" name="is_active" value="0">
                <label class="checkbox"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active))> Compte actif</label>
            </div>
            <div class="field"><label for="password">{{ $user->exists ? 'Nouveau mot de passe' : 'Mot de passe *' }}</label><input type="password" name="password" id="password" autocomplete="new-password" @required(!$user->exists)><div class="hint">{{ $user->exists ? 'Laisser vide pour ne pas changer. ' : '' }}8 caractères min., lettres et chiffres.</div></div>
            <div class="field"><label for="password_confirmation">Confirmation</label><input type="password" name="password_confirmation" id="password_confirmation" autocomplete="new-password"></div>
        </div>
        <div class="form-actions"><a class="btn" href="{{ route('users.index') }}">Annuler</a><button class="btn btn-primary">Enregistrer</button></div>
    </form>
</div>
@endsection
