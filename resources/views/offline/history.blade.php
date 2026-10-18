@extends('layouts.app')
@section('title', 'Historique de synchronisation')
@section('content')
<div class="page-head">
    <div><h1>Historique de synchronisation</h1><small class="muted">Opérations saisies hors connexion et reçues par le serveur.</small></div>
    <div class="actions"><a class="btn" href="{{ route('offline') }}#file">File d’attente de cet appareil</a></div>
</div>
<div class="card">
    <div class="table-wrap">
        <table class="table responsive">
            <thead><tr><th>Saisie sur l’appareil</th><th>Reçue le</th><th>Type</th><th>Résultat</th><th>Message</th></tr></thead>
            <tbody>
            @forelse($operations as $op)
                <tr>
                    <td data-label="Saisie">{{ $op->recorded_at?->format('d/m/Y H:i') ?? '—' }}</td>
                    <td data-label="Reçue le">{{ $op->created_at->format('d/m/Y H:i') }}</td>
                    <td data-label="Type">{{ ['order' => 'Commande / vente', 'expense' => 'Dépense', 'delivery_status' => 'Livraison'][$op->type] ?? $op->type }}</td>
                    <td data-label="Résultat"><span class="badge {{ status_badge($op->status) }}">{{ ['appliquee' => 'Appliquée', 'conflit' => 'À vérifier', 'rejetee' => 'Refusée'][$op->status] ?? $op->status }}</span></td>
                    <td data-label="Message">
                        {{ $op->result['message'] ?? '' }}
                        @if(!empty($op->result['data']['order_id']))<a href="{{ route('orders.show', $op->result['data']['order_id']) }}">Voir</a>@endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">Aucune opération synchronisée.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $operations->links() }}
</div>
@endsection
