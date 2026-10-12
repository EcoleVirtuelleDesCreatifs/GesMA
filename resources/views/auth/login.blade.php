<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="{{ config('gesma.colors.primary') }}">
    <title>Connexion — GesMA</title>
    <link rel="manifest" href="{{ route('pwa.manifest') }}">
    <link rel="icon" href="{{ asset('favicon.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ @filemtime(public_path('css/app.css')) }}">
</head>
<body>
<div class="auth-page">
    <div class="auth-card">
        <div class="logo">
            <img src="{{ asset('images/logo.svg') }}" alt="Mobilier Addict">
            <h1>GesMA</h1>
            <p class="muted text-center">Gestion Mobilier Addict</p>
        </div>
        @include('partials.flash', ['hideErrorSummary' => true])
        <form method="post" action="{{ url('/login') }}">
            @csrf
            <x-field name="email" type="email" label="Adresse e-mail" required autofocus autocomplete="username" />
            <x-field name="password" type="password" label="Mot de passe" required autocomplete="current-password" />
            <div class="field"><label class="checkbox"><input type="checkbox" name="remember" value="1"> Rester connecté sur cet appareil</label></div>
            <button class="btn btn-block" type="submit">Se connecter</button>
        </form>
        <p class="muted small text-center" style="margin-top:1rem">
            Pas de connexion Internet ? <a href="{{ route('offline.app') }}">Ouvrir le mode hors connexion</a>
            <br>(appareil déjà configuré uniquement)
        </p>
    </div>
</div>
@if (session('clear_offline'))
    <script src="{{ asset('js/offline-store.js') }}"></script>
    <script>GesmaStore.wipe();</script>
@endif
</body>
</html>
