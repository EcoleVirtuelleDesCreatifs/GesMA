<div class="card flush">
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Désignation</th><th class="num">Qté</th><th class="num">Prix unitaire</th><th class="num">Total</th></tr></thead>
        <tbody>
        @foreach ($document->lines as $l)
            <tr><td>{{ $l->designation }}@if($l->reference)<div class="muted small mono">{{ $l->reference }}</div>@endif</td><td class="num">{{ $l->quantity }}</td><td class="num">{{ money($l->unit_price) }}</td><td class="num">{{ money($l->line_total) }}</td></tr>
        @endforeach
        </tbody>
    </table></div>
    <div style="padding:1rem 1.2rem">
        <div class="totals">
            <div class="row"><span>Sous-total</span><span>{{ money($document->subtotal) }}</span></div>
            @if ($document->discount)<div class="row"><span>Remise</span><span>− {{ money($document->discount) }}</span></div>@endif
            @if ($document->delivery_fee)<div class="row"><span>Livraison</span><span>{{ money($document->delivery_fee) }}</span></div>@endif
            <div class="row grand"><span>Total</span><span>{{ money($document->total) }}</span></div>
            {{ $slot ?? '' }}
        </div>
    </div>
</div>
