@extends('layouts.app')
@section('title', 'Historique des actions')
@section('content')
<div class="page-head"><h1>Historique des actions sensibles</h1></div>
<form method="GET" class="filters card">
    <div class="field">
        <label for="action">Action</label>
        <select name="action" id="action"><option value="">Toutes</option>@foreach(\App\Models\ActivityLog::ACTIONS as $k => $l)<option value="{{ $k }}" @selected(request('action') === $k)>{{ $l }}</option>@endforeach</select>
    </div>
    <div class="field">
        <label for="user_id">Utilisateur</label>
        <select name="user_id" id="user_id"><option value="">Tous</option>@foreach($users as $u)<option value="{{ $u->id }}" @selected(request('user_id') == $u->id)>{{ $u->name }}</option>@endforeach</select>
    </div>
    <div class="field">
        <label for="store_id">Magasin</label>
        <select name="store_id" id="store_id"><option value="">Tous</option>@foreach($stores as $s)<option value="{{ $s->id }}" @selected(request('store_id') == $s->id)>{{ $s->name }}</option>@endforeach</select>
    </div>
    <div class="field"><label for="from">Du</label><input type="date" name="from" id="from" value="{{ request('from') }}"></div>
    <div class="field"><label for="to">Au</label><input type="date" name="to" id="to" value="{{ request('to') }}"></div>
    <button class="btn btn-sm">Filtrer</button>
</form>
<div class="card">
    <div class="table-wrap">
        <table class="table responsive">
            <thead><tr><th>Date</th><th>Utilisateur</th><th>Magasin</th><th>Action</th><th>Détail</th></tr></thead>
            <tbody>
            @forelse($logs as $log)
                <tr>
                    <td data-label="Date" class="nowrap">{{ $log->created_at->format('d/m/Y H:i') }}</td>
                    <td data-label="Utilisateur">{{ $log->user?->name ?? 'Système' }}</td>
                    <td data-label="Magasin">{{ $log->store?->name ?? '—' }}</td>
                    <td data-label="Action"><span class="badge {{ str_contains($log->action, 'annulation') || str_contains($log->action, 'suppression') ? 'badge-danger' : 'badge-primary' }}">{{ $log->actionLabel() }}</span></td>
                    <td data-label="Détail">
                        {{ $log->description }}
                        @if($log->properties)
                            <details class="collapse"><summary><small>Détails</small></summary><pre style="white-space:pre-wrap;font-size:.75rem;margin:.3rem 0 0">{{ json_encode($log->properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></details>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">Aucune action enregistrée.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $logs->links() }}
</div>
@endsection
