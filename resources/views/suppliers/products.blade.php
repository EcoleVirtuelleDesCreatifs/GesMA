@extends('layouts.app')
@section('title', 'Produits fournisseurs')
@section('content')
<div class="page-head"><h1>Produits fournisseurs</h1></div>

<form method="GET" class="filters card">
    <div class="field"><label>Recherche</label><input type="search" name="q" value="{{ request('q') }}" placeholder="Produit ou référence" autofocus></div>
    <div class="field"><label>Fournisseur</label>
        <select name="supplier_id"><option value="">Tous</option>
            @foreach($suppliers as $s)<option value="{{ $s->id }}" @selected((int) request('supplier_id') === $s->id)>{{ $s->name }}</option>@endforeach
        </select>
    </div>
    <button class="btn btn-sm">Filtrer</button>
</form>

<div class="card">
    <div class="table-wrap"><table class="table responsive">
        <thead><tr><th>Référence</th><th>Produit</th><th>Fournisseur</th><th class="right">Prix d'achat</th><th class="right">Qté min.</th><th class="right">Délai</th><th>Dernier achat</th><th>Statut</th></tr></thead>
        <tbody>
        @forelse($rows as $r)
            <tr>
                <td data-label="Référence">{{ $r->product_reference }}</td>
                <td data-label="Produit">{{ $r->product_name }}</td>
                <td data-label="Fournisseur"><a href="{{ route('suppliers.show', $r->supplier_id) }}">{{ $r->supplier_name }}</a> <small class="muted">{{ $r->supplier_code }}</small></td>
                <td data-label="Prix d'achat" class="right num">@money($r->purchase_price)</td>
                <td data-label="Qté min." class="right">{{ $r->min_quantity }}</td>
                <td data-label="Délai" class="right">{{ $r->lead_time_days !== null ? $r->lead_time_days.' j' : '—' }}</td>
                <td data-label="Dernier achat">{{ $r->last_purchased_at ? \Illuminate\Support\Carbon::parse($r->last_purchased_at)->format('d/m/Y') : '—' }}</td>
                <td data-label="Statut"><span class="badge {{ $r->is_active ? 'badge-success' : 'badge-muted' }}">{{ $r->is_active ? 'Actif' : 'Inactif' }}</span></td>
            </tr>
        @empty
            <tr><td colspan="8" class="empty">Aucune association produit-fournisseur.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    {{ $rows->links() }}
</div>
@endsection
