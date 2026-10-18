@extends('layouts.app')
@section('title', $product->name)
@section('content')
<div class="page-head">
    <div>
        <h1>{{ $product->name }}</h1>
        <small class="muted">{{ $product->reference }} · {{ $product->category?->name ?? 'Sans catégorie' }}</small>
    </div>
    <div class="actions">
        @can('stock.move')
            <a class="btn" href="{{ route('stocks.movements.create', ['product_id' => $product->id, 'mode' => 'entree']) }}">+ Entrée de stock</a>
            <a class="btn" href="{{ route('stocks.movements.create', ['product_id' => $product->id, 'mode' => 'sortie']) }}">− Sortie</a>
        @endcan
        @can('stock.adjust')
            <a class="btn" href="{{ route('stocks.movements.create', ['product_id' => $product->id, 'mode' => 'correction']) }}">Corriger</a>
        @endcan
        @can('stock.transfer')
            <a class="btn" href="{{ route('stocks.transfers.create', ['product_id' => $product->id]) }}">Transférer</a>
        @endcan
        @can('products.manage')
            <a class="btn btn-primary" href="{{ route('products.edit', $product) }}">Modifier</a>
        @endcan
    </div>
</div>

<div class="grid grid-main">
    <div>
        <div class="card">
            <div class="card-head"><h2>Stock par magasin</h2><strong>Total : {{ $stocks->sum('quantity') }}</strong></div>
            <div class="table-wrap">
                <table class="table responsive">
                    <thead><tr><th>Magasin</th><th class="right">Quantité</th><th>Seuil d’alerte</th><th></th></tr></thead>
                    <tbody>
                    @foreach($stocks as $s)
                        <tr class="{{ $s->isLow() ? 'row-low' : '' }}">
                            <td data-label="Magasin">{{ $s->store->name }}</td>
                            <td data-label="Quantité" class="right num"><strong>{{ $s->quantity }}</strong></td>
                            <td data-label="Seuil">
                                @can('stock.adjust')
                                    <form method="POST" action="{{ route('stocks.threshold', $s) }}" style="display:flex;gap:.3rem;max-width:160px">
                                        @csrf @method('PUT')
                                        <input type="number" name="alert_threshold" min="0" value="{{ $s->alert_threshold }}">
                                        <button class="btn btn-sm">OK</button>
                                    </form>
                                @else
                                    {{ $s->alert_threshold }}
                                @endcan
                            </td>
                            <td data-label="">@if($s->isLow())<span class="badge badge-warning">Stock faible</span>@endif</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h2>Derniers mouvements</h2><a href="{{ route('stocks.movements', ['product_id' => $product->id]) }}">Tout voir</a></div>
            @include('stocks._movements-table', ['movements' => $movements, 'showProduct' => false])
        </div>
    </div>
    <div>
        <div class="card">
            <h2>Fiche produit</h2>
            <dl class="dl">
                <dt>Prix</dt><dd>@money($product->price)</dd>
                <dt>État</dt><dd><span class="badge {{ $product->is_available ? 'badge-success' : 'badge-muted' }}">{{ $product->is_available ? 'Disponible' : 'Indisponible' }}</span></dd>
                <dt>Site web</dt><dd>{{ $product->sync_to_website ? 'Publié' : 'Non publié' }}</dd>
                <dt>Créé le</dt><dd>{{ $product->created_at?->format('d/m/Y') }}</dd>
            </dl>
            @if($product->description)<p class="mt">{{ $product->description }}</p>@endif
        </div>
        @can('products.manage')
            <div class="card">
                <form method="POST" action="{{ route('products.destroy', $product) }}" onsubmit="return confirm('Supprimer ce produit ? S’il a déjà été vendu, il sera seulement marqué indisponible.')">
                    @csrf @method('DELETE')
                    <button class="btn btn-danger btn-sm">Supprimer le produit</button>
                </form>
            </div>
        @endcan
    </div>
</div>
@endsection
