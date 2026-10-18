<!DOCTYPE html>
<html lang="fr">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Session expirée · GesMA</title><link rel="stylesheet" href="{{ asset('css/app.css') }}"></head>
<body>
<div class="auth">
    <div class="card center">
        <div class="logo"><img src="{{ asset('icons/icon.svg') }}" alt=""><h1>Session expirée</h1></div>
        <p>Votre session a expiré. Rechargez la page et recommencez.</p>
        <p class="mt"><a class="btn btn-primary" href="{{ url('/') }}">Retour à l’accueil</a> <a class="btn" href="javascript:history.back()">Page précédente</a></p>
    </div>
</div>
</body>
</html>
