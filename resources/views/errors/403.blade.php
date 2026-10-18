<!DOCTYPE html>
<html lang="fr">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Accès refusé · GesMA</title><link rel="stylesheet" href="{{ asset('css/app.css') }}"></head>
<body>
<div class="auth">
    <div class="card center">
        <div class="logo"><img src="{{ asset('icons/icon.svg') }}" alt=""><h1>Accès refusé</h1></div>
        <p>{{ $exception->getMessage() ?: 'Vous n’avez pas accès à cette page ou à cette donnée.' }}</p>
        <p class="mt"><a class="btn btn-primary" href="{{ url('/') }}">Retour à l’accueil</a> <a class="btn" href="javascript:history.back()">Page précédente</a></p>
    </div>
</div>
</body>
</html>
