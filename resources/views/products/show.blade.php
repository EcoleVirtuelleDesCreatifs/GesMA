@extends('layouts.app')
@section('title', $product->name)
@section('content')
<div class="page-header">
    <div>
        <h1>{{ $product->name }} {!! badge($product->status) !!}</h1>
        <p class="subtitle"><span class="mono">{{ $product->reference }}</span> · {{ $product->category?->name ?? 'Sans catégorie' }} · {{ $product->sync_with_website ? 'Publié sur le site' : 'Non publié sur le site' }}</p>
    </div>
    <div class="actions">
        @can('stock.manage')<a class="btn btn-secondary" href="{{ route('stock.create', ['product_id' => $product->id]) }}"><x-icon name="layers" /> Entrée / sortie</a>@endcan
        @can('stock.transfer')<a class="btn btn-secondary" href="{{ route('transfers.create') }}"><x-icon name="transfer" /> Transférer</a>@endcan
        @can('products.manage')
            <a class="btn" href="{{ route('products.edit', $product) }}"><x-icon name="edit" /> Modifier</a>
            <form method="post" action="{{ route('products.destroy', $product) }}" class="inline-form" data-confirm="Archiver ce produit ? Il ne sera plus proposé à la vente.">@csrf @method('DELETE')<button class="btn btn-ghost" type="submit"><x-icon name="trash" /> Archiver</button></form>
        @endcan
    </div>
</div>
<div class="grid grid-2-1">
    <div>
        <div class="stats">
            <div class="stat"><div class="label">Prix de vente</div><div class="value">{{ money($product->price) }}</div></div>
            @if ($product->cost_price)<div class="stat info"><div class="label">Marge unitaire</div><div class="value">{{ money($product->price - $product->cost_price) }}</div></div>@endif
            <div class="stat success"><div class="label">Stock total visible</div><div class="value">{{ $stocks->sum('quantity') }}</div></div>
            <div class="stat warning"><div class="label">Seuil d'alerte</div><div class="value">{{ $product->low_stock_threshold }}</div></div>
        </div>
        <div class="card flush">
            <div class="card-header"><h2>Derniers mouvements de stock</h2><a class="small" href="{{ route('stock.movements', ['product_id' => $product->id]) }}">Tout l'historique</a></div>
            <div class="table-wrap"><table class="table">
                <thead><tr><th>Date</th><th>Magasin</th><th>Type</th><th class="num">Quantité</th><th class="num">Solde</th><th class="hide-mobile">Motif</th></tr></thead>
                <tbody>
                @forelse ($movements as $m)
                    <tr>
                        <td class="nowrap">{{ $m->created_at->format('d/m/Y H:i') }}<div class="muted small">{{ $m->user?->name }}</div></td>
                        <td>{{ $m->store->name }}</td>
                        <td>{!! badge($m->type) !!}</td>
                        <td class="num {{ $m->quantity < 0 ? 'text-danger' : 'text-success' }}">{{ $m->quantity > 0 ? '+' : '' }}{{ $m->quantity }}</td>
                        <td class="num">{{ $m->balance_after }}</td>
                        <td class="hide-mobile small">{{ $m->reason }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">Aucun mouvement.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
    </div>
    <div>
        <div class="card">
            <h2>Stock par magasin</h2>
            <div class="table-wrap"><table class="table"><tbody>
                @forelse ($stocks as $s)
                    <tr class="{{ $s->isLow() ? 'row-warning' : '' }}"><td>{{ $s->store->name }}<div class="muted small">{{ $s->store->city }}</div></td><td class="num strong">{{ $s->quantity }}</td></tr>
                @empty
                    <tr><td class="empty">Aucun stock enregistré.</td></tr>
                @endforelse
            </tbody></table></div>
        </div>
        @if ($product->image_path)<div class="card"><img src="{{ asset('storage/'.$product->image_path) }}" alt="" style="width:100%;border-radius:8px"></div>@endif
        @if ($product->description)<div class="card"><h2>Description</h2><p style="margin:0">{!! nl2br(e($product->description)) !!}</p></div>@endif
    </div>
</div>
@endsection
