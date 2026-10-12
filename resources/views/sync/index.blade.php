@extends('layouts.app')
@section('title', 'Synchronisation')
@section('content')
<div class="page-header">
    <div><h1>Synchronisation et mode hors connexion</h1><p class="subtitle">État de cet appareil et des opérations enregistrées sans Internet</p></div>
    <div class="actions">
        <button class="btn" type="button" data-sync-now><x-icon name="sync" /> Synchroniser maintenant</button>
        <a class="btn btn-secondary" href="{{ route('offline.app') }}"><x-icon name="wifi-off" /> Ouvrir le mode hors connexion</a>
    </div>
</div>

<div class="stats" data-sync-panel>
    <div class="stat success"><div class="label">Connexion</div><div class="value" data-st-connection>—</div></div>
    <div class="stat"><div class="label">Dernière synchronisation</div><div class="value" style="font-size:1.05rem" data-st-last>—</div></div>
    <div class="stat warning"><div class="label">En attente sur l'appareil</div><div class="value" data-st-pending>0</div></div>
    <div class="stat danger"><div class="label">Échecs / conflits</div><div class="value" data-st-failed>0</div></div>
</div>
<div class="card">
    <p style="margin:0"><strong>Dernier message :</strong> <span data-sync-panel><span data-st-error>—</span></span></p>
    <p class="muted small" style="margin:.5rem 0 0">
        Les données nécessaires (produits, stocks, clients, livraisons en cours) sont enregistrées de façon chiffrée sur cet appareil et effacées à la déconnexion.
        Sans Internet, utilisez le <a href="{{ route('offline.app') }}">mode hors connexion</a> pour créer des commandes et ventes, saisir des dépenses et suivre les livraisons.
        Au retour de la connexion, les opérations sont envoyées automatiquement ; chaque opération n'est appliquée qu'une seule fois.
    </p>
</div>

@if ($canResolve)
    <div class="card flush">
        <div class="card-header"><h2>Opérations à vérifier ({{ $conflicts->count() }})</h2></div>
        <p class="muted small" style="padding:0 1.2rem">Exemple : un stock affiché hors connexion a changé pendant la coupure. Appliquer l'opération l'enregistre malgré l'écart (le stock peut devenir négatif et devra être régularisé) ; la rejeter l'ignore définitivement.</p>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Reçue le</th><th>Saisie par</th><th>Opération</th><th>Problème</th><th></th></tr></thead>
            <tbody>
            @forelse ($conflicts as $op)
                <tr>
                    <td class="nowrap">{{ $op->created_at->format('d/m/Y H:i') }}<div class="muted small">saisie {{ $op->client_created_at?->format('d/m/Y H:i') }}</div></td>
                    <td>{{ $op->user?->name }}<div class="muted small">{{ $op->user?->store?->name }}</div></td>
                    <td>{{ $op->typeLabel() }} {!! badge($op->status) !!}
                        @if (in_array($op->type, ['order.create', 'sale.create']))
                            <div class="muted small">{{ count($op->payload['items'] ?? []) }} produit(s){{ isset($op->payload['customer']['name']) ? ' · '.$op->payload['customer']['name'] : '' }}</div>
                        @elseif ($op->type === 'expense.create')
                            <div class="muted small">{{ $op->payload['description'] ?? '' }} · {{ money($op->payload['amount'] ?? 0) }}</div>
                        @endif
                    </td>
                    <td class="small text-danger">{{ $op->message }}</td>
                    <td class="nowrap text-right">
                        <form method="post" action="{{ route('sync.resolve', $op) }}" class="inline-form" data-confirm="Appliquer cette opération malgré l'écart ?">@csrf<input type="hidden" name="action" value="apply"><button class="btn btn-sm btn-success" type="submit">Appliquer</button></form>
                        <form method="post" action="{{ route('sync.resolve', $op) }}" class="inline-form" data-confirm="Rejeter définitivement cette opération ?">@csrf<input type="hidden" name="action" value="reject"><button class="btn btn-sm btn-secondary" type="submit">Rejeter</button></form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">Aucun conflit à vérifier.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>
@endif

<div class="card flush">
    <div class="card-header"><h2>Mes opérations hors connexion reçues par le serveur</h2></div>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Reçue le</th><th>Opération</th><th>Statut</th><th>Message</th></tr></thead>
        <tbody>
        @forelse ($operations as $op)
            <tr><td class="nowrap">{{ $op->created_at->format('d/m/Y H:i') }}</td><td>{{ $op->typeLabel() }}</td><td>{!! badge($op->status) !!}</td><td class="small">{{ $op->message }}</td></tr>
        @empty
            <tr><td colspan="4" class="empty">Aucune opération hors connexion pour le moment.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
@endsection
