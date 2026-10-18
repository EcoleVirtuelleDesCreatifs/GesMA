<div class="table-wrap">
    <table class="table responsive">
        <thead><tr>
            <th>Date</th>
            @if($showProduct ?? true)<th>Produit</th>@endif
            <th>Magasin</th><th>Type</th><th class="right">Qté</th><th class="right">Avant → après</th><th>Motif</th><th>Par</th>
        </tr></thead>
        <tbody>
        @forelse($movements as $m)
            <tr>
                <td data-label="Date" class="nowrap">{{ $m->created_at->format('d/m/Y H:i') }}</td>
                @if($showProduct ?? true)
                    <td data-label="Produit"><a href="{{ route('products.show', $m->product_id) }}">{{ $m->product?->name }}</a></td>
                @endif
                <td data-label="Magasin">{{ $m->store?->name }}</td>
                <td data-label="Type"><span class="badge {{ $m->quantity >= 0 ? 'badge-success' : 'badge-danger' }}">{{ $m->typeLabel() }}</span></td>
                <td data-label="Qté" class="right num"><strong>{{ $m->quantity > 0 ? '+' : '' }}{{ $m->quantity }}</strong></td>
                <td data-label="Avant → après" class="right num nowrap">{{ $m->quantity_before }} → {{ $m->quantity_after }}</td>
                <td data-label="Motif">{{ $m->reason ?? '—' }}</td>
                <td data-label="Par">{{ $m->user?->name ?? 'Système' }}</td>
            </tr>
        @empty
            <tr><td colspan="8" class="empty">Aucun mouvement.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
