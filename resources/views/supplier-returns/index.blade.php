@extends('layouts.app')
@section('title', 'Retours fournisseurs')
@section('content')
<div class="page-head">
    <h1>Retours fournisseurs</h1>
    <div class="actions">@can('supplier_returns.manage')<a class="btn btn-primary" href="{{ route('supplier-returns.create') }}">+ Déclarer un retour</a>@endcan</div>
</div>

<div class="card">
    <div class="table-wrap"><table class="table responsive">
        <thead><tr><th>N°</th><th>Fournisseur</th><th>Date</th><th>Produits</th><th>Décision</th><th>Statut</th><th></th></tr></thead>
        <tbody>
        @forelse($returns as $r)
            <tr>
                <td data-label="N°"><a href="{{ route('supplier-returns.show', $r) }}"><strong>{{ $r->number }}</strong></a></td>
                <td data-label="Fournisseur">{{ $r->supplier->name }}</td>
                <td data-label="Date" class="nowrap">{{ $r->returned_at->format('d/m/Y') }}</td>
                <td data-label="Produits">{{ $r->items->count() }} article(s) — {{ $r->items->sum('quantity') }} unité(s)</td>
                <td data-label="Décision">{{ $r->decisionLabel() }}</td>
                <td data-label="Statut"><span class="badge badge-info">{{ $r->statusLabel() }}</span></td>
                <td data-label="" class="actions"><a class="btn btn-sm" href="{{ route('supplier-returns.show', $r) }}">Voir</a></td>
            </tr>
        @empty
            <tr><td colspan="7" class="empty">Aucun retour déclaré.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    {{ $returns->links() }}
</div>
@endsection
