<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="{{ config('gesma.company.primary_color') }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="GesMA">
    <title>Connexion · GesMA</title>
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="icon" href="{{ asset('icons/icon.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset('icons/icon-192.png') }}">
    <link rel="apple-touch-startup-image" href="{{ asset('icons/icon-512.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<div class="auth">
    <div class="card">
        <div class="logo">
            <img src="{{ asset('icons/icon.svg') }}" alt="">
            <h1>GesMA</h1>
            <small class="muted">{{ config('gesma.company.name') }} — espace de gestion</small>
        </div>
        @include('partials.flash')
        <div class="alert alert-warning hidden" id="login-offline">
            Pas de connexion Internet. Si vous vous êtes déjà connecté sur cet appareil,
            <a href="{{ route('offline') }}">ouvrez la saisie hors connexion</a>.
        </div>
        <form method="POST" action="{{ url('/login') }}">
            @csrf
            <div class="field mb">
                <label for="email">Adresse e-mail</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
            </div>
            <div class="field mb">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>
            <label class="checkbox mb"><input type="checkbox" name="remember" value="1"> Rester connecté sur cet appareil</label>
            <button class="btn btn-primary btn-block" type="submit">Se connecter</button>
        </form>
    </div>
</div>
<script src="{{ asset('js/offline-store.js') }}"></script>
<script>
    @if(session('clear_offline'))
        // Déconnexion : effacement des données locales de l'appareil
        // (les opérations non synchronisées sont conservées et repartiront à la prochaine connexion)
        GesMAStore.clearSnapshots().catch(() => {});
        if ('caches' in window) caches.delete('gesma-pages').catch(() => {});
    @endif
    function updateOnline() { document.getElementById('login-offline').classList.toggle('hidden', navigator.onLine); }
    addEventListener('online', updateOnline); addEventListener('offline', updateOnline); updateOnline();
    if ('serviceWorker' in navigator) navigator.serviceWorker.register(@json(url('sw.js'))).catch(() => {});
</script>
</body>
</html>
