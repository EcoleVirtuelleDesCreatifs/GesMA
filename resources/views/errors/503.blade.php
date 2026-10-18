<!DOCTYPE html>
<html lang="fr">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Maintenance · GesMA</title><link rel="stylesheet" href="/css/app.css"></head>
<body>
<div class="auth">
    <div class="card center">
        <div class="logo"><img src="/icons/icon.svg" alt=""><h1>Maintenance</h1></div>
        <p>{{ $exception->getMessage() ?: 'GesMA est momentanément en maintenance. Les magasins peuvent continuer en mode hors connexion.' }}</p>
        <p class="mt"><a class="btn btn-primary" href="{{ url('/') }}">Retour à l’accueil</a> <a class="btn" href="javascript:history.back()">Page précédente</a></p>
    </div>
</div>
</body>
</html>
