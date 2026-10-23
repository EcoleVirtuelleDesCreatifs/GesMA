@extends('layouts.app')
@section('title', "Commandes d'achat")
@section('content')
<div class="page-head">
    <h1>Commandes d'achat</h1>
    <div class="actions">@can('purchases.manage')<a class="btn btn-primary" href="{{ route('purchases.create') }}">+ Nouvelle commande</a>@endcan</div>
</div>

<form method="GET" class="filters card">
    <div class="field"><label>Recherche</label><input type="search" name="q" value="{{ request('q') }}" placeholder="N° ou fournisseur" autofocus></div>
    <div class="field"><label>Statut</label>
        <select name="status"><option value="">Tous</option>
            @foreach($statuses as $k => $l)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>@endforeach
        </select>
    </div>
    <div class="field"><label>Fournisseur</label>
        <select name="supplier_id"><option value="">Tous</option>
            @foreach($suppliers as $s)<option value="{{ $s->id }}" @selected((int) request('supplier_id') === $s->id)>{{ $s->name }}</option>@endforeach
        </select>
    </div>
    <button class="btn btn-sm">Filtrer</button>
</form>

<div class="card">
    <div class="table-wrap"><table class="table responsive">
        <thead><tr><th>N°</th><th>Fournisseur</th><th>Magasin</th><th>Date</th><th>Livraison prévue</th><th class="right">Total</th><th>Statut</th><th></th></tr></thead>
        <tbody>
        @forelse($orders as $o)
            @php($tone = ['brouillon'=>'muted','en_attente'=>'warning','validee'=>'info','envoyee'=>'primary','partielle'=>'warning','receptionnee'=>'success','annulee'=>'danger'][$o->status] ?? 'muted')
            <tr>
                <td data-label="N°" class="nowrap"><a href="{{ route('purchases.show', $o) }}"><strong>{{ $o->number }}</strong></a></td>
                <td data-label="Fournisseur">{{ $o->supplier->name }}</td>
                <td data-label="Magasin">{{ $o->store->name }}</td>
                <td data-label="Date" class="nowrap">{{ $o->ordered_at?->format('d/m/Y') ?? '—' }}</td>
                <td data-label="Livraison prévue" class="nowrap">{{ $o->expected_at?->format('d/m/Y') ?? '—' }}@if($o->isLate()) <span class="badge badge-danger">Retard</span>@endif</td>
                <td data-label="Total" class="right num">@money($o->total)</td>
                <td data-label="Statut"><span class="badge badge-{{ $tone }}">{{ $o->statusLabel() }}</span></td>
                <td data-label="" class="actions"><a class="btn btn-sm" href="{{ route('purchases.show', $o) }}">Voir</a> <a class="btn btn-sm" href="{{ route('purchases.pdf', $o) }}">PDF</a></td>
            </tr>
        @empty
            <tr><td colspan="8" class="empty">Aucune commande d'achat.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    {{ $orders->links() }}
</div>
@endsection
