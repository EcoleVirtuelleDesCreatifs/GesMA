@extends('layouts.app')
@section('title', 'Mot de passe')
@section('content')
<div class="page-head"><h1>Changer mon mot de passe</h1></div>
<div class="card" style="max-width:520px">
    <form method="POST" action="{{ route('password.update') }}">
        @csrf @method('PUT')
        <div class="field mb">
            <label for="current_password">Mot de passe actuel</label>
            <input type="password" name="current_password" id="current_password" required>
        </div>
        <div class="field mb">
            <label for="password">Nouveau mot de passe</label>
            <input type="password" name="password" id="password" required>
            <div class="hint">8 caractères minimum, avec des lettres et des chiffres.</div>
        </div>
        <div class="field mb">
            <label for="password_confirmation">Confirmation</label>
            <input type="password" name="password_confirmation" id="password_confirmation" required>
        </div>
        <div class="form-actions"><button class="btn btn-primary">Enregistrer</button></div>
    </form>
</div>
@endsection
