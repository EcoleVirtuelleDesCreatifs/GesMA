@extends('layouts.app')
@section('title', 'Historique des actions sensibles')
@section('content')
<div class="page-header"><div><h1>Historique des actions sensibles</h1><p class="subtitle">Corrections de stock, modifications de prix, annulations, conflits de synchronisation, gestion des comptes…</p></div></div>
<form method="get" class="filters">
    <div class="field"><label for="action">Action</label><select name="action" id="action"><option value="">Toutes</option>@foreach ($actions as $k => $l)<option value="{{ $k }}" @selected(request('action') === $k)>{{ $l }}</option>@endforeach</select></div>
    <div class="field"><label for="user_id">Utilisateur</label><select name="user_id" id="user_id"><option value="">Tous</option>@foreach ($users as $u)<option value="{{ $u->id }}" @selected(request('user_id') == $u->id)>{{ $u->name }}</option>@endforeach</select></div>
    <div class="field"><label for="store_id">Magasin</label><select name="store_id" id="store_id"><option value="">Tous</option>@foreach ($stores as $s)<option value="{{ $s->id }}" @selected(request('store_id') == $s->id)>{{ $s->name }}</option>@endforeach</select></div>
    <div class="field"><label for="from">Du</label><input type="date" name="from" id="from" value="{{ request('from') }}"></div>
    <div class="field"><label for="to">Au</label><input type="date" name="to" id="to" value="{{ request('to') }}"></div>
    <button class="btn btn-secondary" type="submit">Filtrer</button>
</form>
<div class="card flush">
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Date</th><th>Utilisateur</th><th>Action</th><th>Détail</th><th class="hide-mobile">Magasin</th><th class="hide-mobile">IP</th></tr></thead>
        <tbody>
        @forelse ($logs as $log)
            <tr>
                <td class="nowrap">{{ $log->created_at->format('d/m/Y H:i') }}</td>
                <td>{{ $log->user?->name ?? 'Système / site web' }}</td>
                <td><span class="badge badge-primary">{{ $actions[$log->action] ?? $log->action }}</span></td>
                <td class="small">{{ $log->description }}</td>
                <td class="hide-mobile">{{ $log->store?->name }}</td>
                <td class="hide-mobile mono small">{{ $log->ip_address }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="empty">Aucune action enregistrée.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
{{ $logs->links() }}
@endsection
