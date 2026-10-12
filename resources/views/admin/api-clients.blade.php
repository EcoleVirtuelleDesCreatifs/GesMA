@extends('layouts.app')
@section('title', 'API site web')
@section('content')
<div class="page-header"><div><h1>Connexion au site web Mobilier Addict</h1><p class="subtitle">Jetons d'accès utilisés par le site pour envoyer les commandes et lire les produits et stocks.</p></div></div>
@if (session('new_token'))
    <div class="card">
        <h2>Nouveau jeton</h2>
        <p class="text-danger strong">Copiez ce jeton et transmettez-le de façon sécurisée au développeur du site. Il ne sera plus affiché.</p>
        <div class="token-box">{{ session('new_token') }}</div>
    </div>
@endif
<div class="grid grid-2-1">
    <div class="card flush">
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Nom</th><th>Jeton</th><th>Magasin par défaut</th><th>Dernière utilisation</th><th>État</th><th></th></tr></thead>
            <tbody>
            @forelse ($clients as $c)
                <tr>
                    <td class="strong">{{ $c->name }}</td>
                    <td class="mono small">{{ $c->token_prefix }}…</td>
                    <td>{{ $c->defaultStore?->name ?? config('gesma.website.default_store_code') }}</td>
                    <td class="small">{{ $c->last_used_at?->format('d/m/Y H:i') ?? 'Jamais' }}</td>
                    <td>{!! $c->is_active ? '<span class="badge badge-success">Actif</span>' : '<span class="badge badge-muted">Désactivé</span>' !!}</td>
                    <td><form method="post" action="{{ route('api-clients.toggle', $c) }}">@csrf<button class="btn btn-secondary btn-sm" type="submit">{{ $c->is_active ? 'Désactiver' : 'Réactiver' }}</button></form></td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">Aucun jeton.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>
    <form class="card" method="post" action="{{ route('api-clients.store') }}">
        @csrf
        <h2>Créer un jeton</h2>
        <x-field name="name" label="Nom" value="Site web Mobilier Addict" required />
        <x-field name="default_store_id" label="Magasin des commandes du site" type="select" :options="$stores->pluck('name', 'id')" placeholder="— Selon la configuration —" />
        <button class="btn btn-block" type="submit">Créer</button>
    </form>
</div>
<div class="card">
    <h2>Points d'accès de l'API</h2>
    <p class="muted">Authentification : en-tête <span class="mono">Authorization: Bearer &lt;jeton&gt;</span>. Documentation complète : <span class="mono">docs/API.md</span>.</p>
    <div class="table-wrap"><table class="table">
        <tbody>
            <tr><td class="mono">GET {{ url('/api/v1/products') }}</td><td>Catalogue (prix, disponibilité, stock par magasin)</td></tr>
            <tr><td class="mono">GET {{ url('/api/v1/stock') }}</td><td>Disponibilités par produit et par magasin</td></tr>
            <tr><td class="mono">POST {{ url('/api/v1/orders') }}</td><td>Envoi d'une commande du site (sans doublon grâce à <span class="mono">external_reference</span>)</td></tr>
            <tr><td class="mono">GET {{ url('/api/v1/orders/{référence}') }}</td><td>Suivi d'une commande du site</td></tr>
            <tr><td class="mono">POST {{ url('/api/v1/orders/{référence}/cancel') }}</td><td>Annulation demandée par le site</td></tr>
        </tbody>
    </table></div>
</div>
@endsection
