<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="{{ config('gesma.company.primary_color') }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="GesMA">
    <title>@yield('title', 'Tableau de bord') · GesMA</title>
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/icons/icon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">
    <link rel="apple-touch-startup-image" href="/icons/icon-512.png">
    <link rel="stylesheet" href="/css/app.css?v={{ config('gesma.asset_version', '1') }}">
    @stack('head')
</head>
<body>
@php($u = auth()->user())
<div class="app">
    <aside class="sidebar" id="sidebar">
        <div class="brand">
            <img src="/icons/icon.svg" alt="">
            <div><strong>GesMA</strong><small>{{ config('gesma.company.name') }}</small></div>
        </div>
        <nav class="nav">
            @can('dashboard.view')
                <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">Tableau de bord</a>
            @endcan

            <div class="section">Activité</div>
            @can('orders.view')
                <a href="{{ route('orders.index') }}" class="{{ request()->routeIs('orders.*') ? 'active' : '' }}">Commandes & ventes</a>
            @endcan
            @can('deliveries.view')
                <a href="{{ route('deliveries.index') }}" class="{{ request()->routeIs('deliveries.*') ? 'active' : '' }}">Livraisons</a>
            @endcan
            @can('customers.view')
                <a href="{{ route('customers.index') }}" class="{{ request()->routeIs('customers.*') ? 'active' : '' }}">Clients</a>
            @endcan
            @can('documents.view')
                <a href="{{ route('documents.index') }}" class="{{ request()->routeIs('documents.*') ? 'active' : '' }}">Devis & factures</a>
            @endcan
            @can('expenses.view')
                <a href="{{ route('expenses.index') }}" class="{{ request()->routeIs('expenses.*') ? 'active' : '' }}">Dépenses</a>
            @endcan

            <div class="section">Catalogue</div>
            @can('products.view')
                <a href="{{ route('products.index') }}" class="{{ request()->routeIs('products.*') ? 'active' : '' }}">Produits</a>
            @endcan
            @can('stock.view')
                <a href="{{ route('stocks.index') }}" class="{{ request()->routeIs('stocks.index') ? 'active' : '' }}">
                    Stocks
                    @if(($navCounters['low_stock'] ?? 0) > 0)<span class="count" title="Stocks faibles">{{ $navCounters['low_stock'] }}</span>@endif
                </a>
                <a href="{{ route('stocks.movements') }}" class="{{ request()->routeIs('stocks.movements*') ? 'active' : '' }}">Mouvements de stock</a>
                <a href="{{ route('stocks.transfers') }}" class="{{ request()->routeIs('stocks.transfers*') ? 'active' : '' }}">Transferts</a>
            @endcan

            @canany(['reports.view', 'conflicts.view'])
                <div class="section">Suivi</div>
            @endcanany
            @can('reports.view')
                <a href="{{ route('reports.index') }}" class="{{ request()->routeIs('reports.*') ? 'active' : '' }}">Rapports PDF</a>
            @endcan
            @can('conflicts.view')
                <a href="{{ route('conflicts.index') }}" class="{{ request()->routeIs('conflicts.*') ? 'active' : '' }}">
                    Conflits de synchro
                    @if(($navCounters['conflicts'] ?? 0) > 0)<span class="count danger">{{ $navCounters['conflicts'] }}</span>@endif
                </a>
            @endcan

            <div class="section">Hors connexion</div>
            <a href="{{ route('offline') }}" class="{{ request()->routeIs('offline') ? 'active' : '' }}">Saisie hors connexion</a>
            <a href="{{ route('offline.history') }}" class="{{ request()->routeIs('offline.history') ? 'active' : '' }}">Historique de synchro</a>

            @canany(['stores.manage', 'users.manage', 'activity.view'])
                <div class="section">Administration</div>
            @endcanany
            @can('stores.manage')
                <a href="{{ route('stores.index') }}" class="{{ request()->routeIs('stores.*') ? 'active' : '' }}">Magasins</a>
            @endcan
            @can('users.manage')
                <a href="{{ route('users.index') }}" class="{{ request()->routeIs('users.*') ? 'active' : '' }}">Utilisateurs</a>
            @endcan
            @can('activity.view')
                <a href="{{ route('activity.index') }}" class="{{ request()->routeIs('activity.*') ? 'active' : '' }}">Historique des actions</a>
            @endcan

            <div class="section">Compte</div>
            <a href="{{ route('password.edit') }}">Mot de passe</a>
            <form method="POST" action="{{ route('logout') }}" id="logout-form">
                @csrf
                <a href="#" data-logout>Se déconnecter</a>
            </form>
        </nav>
    </aside>

    <div class="main">
        <div class="offline-banner hidden" id="offline-banner">
            Pas de connexion Internet. Vos saisies sont enregistrées sur cet appareil.
            <a href="{{ route('offline') }}">Ouvrir la saisie hors connexion</a>
        </div>
        <header class="topbar">
            <button class="menu-toggle" type="button" aria-label="Menu" data-menu-toggle>☰</button>
            <strong class="hide-mobile">@yield('title', 'Tableau de bord')</strong>
            <div class="spacer"></div>
            <a class="sync-status" id="sync-status" href="{{ route('offline') }}#file" title="État de la synchronisation">
                <span class="dot"></span>
                <span class="label" data-sync-label>En ligne</span>
                <span class="pending hidden" data-sync-pending title="Opérations en attente">0</span>
                <span class="failed hidden" data-sync-failed title="Opérations en échec">0</span>
            </a>
            <div class="user-chip">
                <strong>{{ $u->name }}</strong>
                <small>{{ $u->roleLabel() }}@if($u->store) · {{ $u->store->city }}@endif</small>
            </div>
        </header>

        <main class="content">
            @include('partials.flash')
            @yield('content')
        </main>
    </div>
</div>

<script>
    window.GesMA = {
        userId: {{ $u->id }},
        dataUrl: @json(route('offline.data')),
        syncUrl: @json(route('sync')),
        offlineUrl: @json(route('offline')),
    };
</script>
<script src="/js/offline-store.js?v={{ config('gesma.asset_version', '1') }}"></script>
<script src="/js/app.js?v={{ config('gesma.asset_version', '1') }}"></script>
@stack('scripts')
</body>
</html>
