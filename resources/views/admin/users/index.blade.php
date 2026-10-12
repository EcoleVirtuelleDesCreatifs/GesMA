@extends('layouts.app')
@section('title', 'Utilisateurs')
@section('content')
<div class="page-header">
    <div><h1>Utilisateurs et droits d'accès</h1><p class="subtitle">Chaque membre de l'équipe dispose d'un compte personnel adapté à sa fonction.</p></div>
    <div class="actions"><a class="btn" href="{{ route('users.create') }}"><x-icon name="plus" /> Nouvel utilisateur</a></div>
</div>
<div class="card flush">
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Nom</th><th>E-mail</th><th>Profil</th><th>Magasin</th><th class="hide-mobile">Dernière connexion</th><th>État</th><th></th></tr></thead>
        <tbody>
        @foreach ($users as $u)
            <tr>
                <td class="strong">{{ $u->name }}<div class="muted small">{{ $u->phone }}</div></td>
                <td>{{ $u->email }}</td>
                <td>{!! badge($u->role) !!}</td>
                <td>{{ $u->store?->name ?? 'Tous les magasins' }}</td>
                <td class="hide-mobile small">{{ $u->last_login_at?->format('d/m/Y H:i') ?? 'Jamais' }}</td>
                <td>{!! $u->is_active ? '<span class="badge badge-success">Actif</span>' : '<span class="badge badge-muted">Désactivé</span>' !!}</td>
                <td class="text-right"><a class="btn btn-ghost btn-sm" href="{{ route('users.edit', $u) }}"><x-icon name="edit" /></a></td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
</div>
<div class="card">
    <h2>Profils</h2>
    <dl class="details">
        <dt>{{ \App\Enums\Role::Direction->label() }}</dt><dd>Accès à tous les magasins, aux résultats globaux, à la gestion des utilisateurs et des magasins.</dd>
        <dt>{{ \App\Enums\Role::Manager->label() }}</dt><dd>Opérations et résultats de son magasin : commandes, stocks (corrections, transferts), prix, livraisons, dépenses, rapports, résolution des conflits de synchronisation.</dd>
        <dt>{{ \App\Enums\Role::Sales->label() }}</dt><dd>Clients, commandes, ventes, devis et factures de son magasin ; consultation des produits et stocks.</dd>
        <dt>{{ \App\Enums\Role::Logistics->label() }}</dt><dd>Commandes à préparer ou à livrer et suivi des livraisons de son magasin.</dd>
    </dl>
</div>
@endsection
