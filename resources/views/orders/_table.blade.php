<div class="table-wrap">
    <table class="table responsive">
        <thead><tr>
            <th>N°</th><th>Date</th>
            @if($showCustomer ?? true)<th>Client</th>@endif
            @if($showStore ?? auth()->user()->isDirection())<th>Magasin</th>@endif
            <th>Origine</th><th class="right">Montant</th><th>Paiement</th><th>Statut</th>
        </tr></thead>
        <tbody>
        @forelse($orders as $o)
            <tr>
                <td data-label="N°" class="nowrap"><a href="{{ route('orders.show', $o) }}"><strong>{{ $o->number }}</strong></a></td>
                <td data-label="Date" class="nowrap">{{ $o->ordered_at->format('d/m/Y H:i') }}</td>
                @if($showCustomer ?? true)
                    <td data-label="Client">{{ $o->customer?->name ?? 'Client comptoir' }}</td>
                @endif
                @if($showStore ?? auth()->user()->isDirection())
                    <td data-label="Magasin">{{ $o->store?->city }}</td>
                @endif
                <td data-label="Origine"><small>{{ $o->sourceLabel() }}</small>@if($o->external_reference)<br><small class="muted">{{ $o->external_reference }}</small>@endif</td>
                <td data-label="Montant" class="right num">@money($o->total)</td>
                <td data-label="Paiement">
                    @if($o->balance() <= 0)<span class="badge badge-success">Payée</span>
                    @elseif((float) $o->amount_paid > 0)<span class="badge badge-warning">Partiel</span>
                    @else<span class="badge badge-muted">À payer</span>@endif
                </td>
                <td data-label="Statut"><span class="badge {{ status_badge($o->status) }}">{{ $o->statusLabel() }}</span></td>
            </tr>
        @empty
            <tr><td colspan="8" class="empty">Aucune commande.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
