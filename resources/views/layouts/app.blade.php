@php
    $user = auth()->user();
    $nav = [
        ['title' => null, 'items' => [
            ['route' => 'dashboard', 'label' => 'Tableau de bord', 'icon' => 'home', 'can' => 'dashboard', 'active' => 'dashboard'],
        ]],
        ['title' => 'Activité', 'items' => [
            ['route' => 'orders.index', 'label' => 'Commandes', 'icon' => 'cart', 'can' => 'orders.view', 'active' => 'orders.*'],
            ['route' => 'sales.index', 'label' => 'Ventes', 'icon' => 'chart', 'can' => 'sales.view', 'active' => 'sales.*'],
            ['route' => 'deliveries.index', 'label' => 'Livraisons', 'icon' => 'truck', 'can' => 'deliveries.view', 'active' => 'deliveries.*'],
            ['route' => 'customers.index', 'label' => 'Clients', 'icon' => 'users', 'can' => 'customers.view', 'active' => 'customers.*'],
        ]],
        ['title' => 'Catalogue', 'items' => [
            ['route' => 'products.index', 'label' => 'Produits', 'icon' => 'box', 'can' => 'products.view', 'active' => 'products.*|categories.*'],
            ['route' => 'stock.index', 'label' => 'Stocks', 'icon' => 'layers', 'can' => 'stock.view', 'active' => 'stock.*'],
            ['route' => 'transfers.index', 'label' => 'Transferts', 'icon' => 'transfer', 'can' => 'stock.view', 'active' => 'transfers.*'],
        ]],
        ['title' => 'Documents & finances', 'items' => [
            ['route' => 'quotes.index', 'label' => 'Devis', 'icon' => 'file', 'can' => 'quotes.manage', 'active' => 'quotes.*'],
            ['route' => 'invoices.index', 'label' => 'Factures', 'icon' => 'receipt', 'can' => 'invoices.manage', 'active' => 'invoices.*'],
            ['route' => 'expenses.index', 'label' => 'Dépenses', 'icon' => 'wallet', 'can' => ['expenses.view', 'expenses.manage'], 'active' => 'expenses.*'],
            ['route' => 'reports.index', 'label' => 'Rapports', 'icon' => 'report', 'can' => 'reports.view', 'active' => 'reports.*'],
        ]],
        ['title' => 'Administration', 'items' => [
            ['route' => 'stores.index', 'label' => 'Magasins', 'icon' => 'store', 'can' => 'admin', 'active' => 'stores.*'],
            ['route' => 'users.index', 'label' => 'Utilisateurs', 'icon' => 'user-cog', 'can' => 'admin', 'active' => 'users.*'],
            ['route' => 'expense-categories.index', 'label' => 'Catégories de dépenses', 'icon' => 'tag', 'can' => 'admin', 'active' => 'expense-categories.*'],
            ['route' => 'api-clients.index', 'label' => 'API site web', 'icon' => 'key', 'can' => 'admin', 'active' => 'api-clients.*'],
            ['route' => 'activity.index', 'label' => 'Historique sensible', 'icon' => 'history', 'can' => 'admin', 'active' => 'activity.*'],
        ]],
        ['title' => 'Hors connexion', 'items' => [
            ['route' => 'sync.index', 'label' => 'Synchronisation', 'icon' => 'sync', 'can' => null, 'active' => 'sync.*'],
            ['route' => 'offline.app', 'label' => 'Mode hors connexion', 'icon' => 'wifi-off', 'can' => null, 'active' => 'offline.*'],
        ]],
    ];
    $allowed = function ($can) use ($user) {
        if ($can === null) return true;
        foreach ((array) $can as $p) { if ($user->hasPermission($p)) return true; }
        return false;
    };
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="{{ config('gesma.colors.primary') }}">
    <meta name="gesma-user" content="{{ $user->id }}">
    <title>@yield('title', 'GesMA') — GesMA</title>
    <link rel="manifest" href="{{ route('pwa.manifest') }}">
    <link rel="icon" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/icon-192.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ @filemtime(public_path('css/app.css')) }}">
</head>
<body>
<div class="offline-banner" role="alert">
    Vous êtes hors connexion. Les pages en ligne ne sont pas disponibles —
    <a href="{{ route('offline.app') }}">ouvrir le mode hors connexion</a> pour continuer à enregistrer commandes, ventes, dépenses et livraisons.
</div>
<div class="app">
    <aside class="sidebar" id="sidebar">
        <div class="brand">
            <img src="{{ asset('images/logo.svg') }}" alt="">
            <div><strong>GesMA</strong><span>Mobilier Addict</span></div>
        </div>
        <nav>
            @foreach ($nav as $section)
                @php $items = array_filter($section['items'], fn ($i) => $allowed($i['can'])); @endphp
                @if ($items)
                    @if ($section['title'])<div class="nav-title">{{ $section['title'] }}</div>@endif
                    @foreach ($items as $item)
                        <a href="{{ route($item['route']) }}" @class(['active' => request()->routeIs(...explode('|', $item['active']))])>
                            <x-icon :name="$item['icon']" /> {{ $item['label'] }}
                            @if ($item['route'] === 'sync.index')<span class="count hidden" data-sync-nav-count></span>@endif
                        </a>
                    @endforeach
                @endif
            @endforeach
        </nav>
        <div class="user-box">
            <a href="{{ route('profile.edit') }}" style="color:#fff">{{ $user->name }}</a>
            <div class="role">{{ $user->role->label() }}@if($user->store) · {{ $user->store->name }}@endif</div>
            <form method="post" action="{{ route('logout') }}" data-logout-form style="margin-top:.5rem">
                @csrf
                <button class="btn btn-sm btn-secondary" type="submit"><x-icon name="logout" /> Déconnexion</button>
            </form>
        </div>
    </aside>
    <div class="backdrop" data-nav-close></div>
    <div class="main">
        <header class="topbar">
            <button class="menu-toggle" type="button" data-nav-toggle aria-label="Menu"><x-icon name="menu" width="22" height="22" /></button>
            @can('orders.view')
                <form class="search" action="{{ route('orders.index') }}" method="get" role="search">
                    <input type="search" name="q" placeholder="Rechercher une commande, un client, un téléphone…" value="{{ request()->routeIs('orders.index') ? request('q') : '' }}">
                </form>
            @endcan
            <div class="spacer"></div>
            <a href="{{ route('sync.index') }}" class="sync-indicator" data-sync-indicator title="État de la connexion et de la synchronisation">
                <span class="dot"></span><span data-sync-label>En ligne</span>
                <span class="pill hidden" data-sync-pending></span>
                <span class="pill failed hidden" data-sync-failed></span>
            </a>
        </header>
        <main class="content">
            @include('partials.flash')
            @yield('content')
        </main>
    </div>
</div>
<script src="{{ asset('js/offline-store.js') }}?v={{ @filemtime(public_path('js/offline-store.js')) }}"></script>
<script src="{{ asset('js/app.js') }}?v={{ @filemtime(public_path('js/app.js')) }}"></script>
@stack('scripts')
</body>
</html>
