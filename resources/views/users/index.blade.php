@extends('layouts.app')
@section('title', 'Utilisateurs')
@section('content')
<div class="page-head">
    <h1>Utilisateurs</h1>
    <div class="actions"><a class="btn btn-primary" href="{{ route('users.create') }}">+ Nouveau compte</a></div>
</div>
<form method="GET" class="filters card">
    <div class="field">
        <label for="role">Profil</label>
        <select name="role" id="role"><option value="">Tous</option>@foreach(config('gesma.roles') as $k => $l)<option value="{{ $k }}" @selected(request('role') === $k)>{{ $l }}</option>@endforeach</select>
    </div>
    <div class="field">
        <label for="store_id">Magasin</label>
        <select name="store_id" id="store_id"><option value="">Tous</option>@foreach($stores as $s)<option value="{{ $s->id }}" @selected(request('store_id') == $s->id)>{{ $s->name }}</option>@endforeach</select>
    </div>
    <button class="btn btn-sm">Filtrer</button>
</form>
<div class="card">
    <div class="table-wrap">
        <table class="table responsive">
            <thead><tr><th>Nom</th><th>E-mail</th><th>Profil</th><th>Magasin</th><th>Dernière connexion</th><th>État</th><th></th></tr></thead>
            <tbody>
            @foreach($users as $u)
                <tr>
                    <td data-label="Nom"><strong>{{ $u->name }}</strong>@if($u->phone)<br><small class="muted">{{ $u->phone }}</small>@endif</td>
                    <td data-label="E-mail">{{ $u->email }}</td>
                    <td data-label="Profil"><span class="badge badge-primary">{{ $u->roleLabel() }}</span></td>
                    <td data-label="Magasin">{{ $u->store?->name ?? 'Tous' }}</td>
                    <td data-label="Dernière connexion">{{ $u->last_login_at?->format('d/m/Y H:i') ?? 'Jamais' }}</td>
                    <td data-label="État"><span class="badge {{ $u->is_active ? 'badge-success' : 'badge-muted' }}">{{ $u->is_active ? 'Actif' : 'Désactivé' }}</span></td>
                    <td data-label="" class="actions"><a class="btn btn-sm" href="{{ route('users.edit', $u) }}">Modifier</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    {{ $users->links() }}
</div>
<div class="card">
    <h2>Droits par profil</h2>
    <p class="muted" style="font-size:.85rem">Définis dans <code>config/gesma.php</code> (à valider avant la mise en service). La Direction dispose de tous les droits.</p>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Droit</th>@foreach(config('gesma.roles') as $l)<th class="center">{{ $l }}</th>@endforeach</tr></thead>
            <tbody>
            @foreach(config('gesma.permissions') as $perm => $roles)
                <tr>
                    <td><code>{{ $perm }}</code></td>
                    @foreach(array_keys(config('gesma.roles')) as $role)
                        <td class="center">{{ $role === 'direction' || in_array($role, $roles, true) ? '✓' : '—' }}</td>
                    @endforeach
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
