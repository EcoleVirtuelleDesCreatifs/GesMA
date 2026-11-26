<div class="card flush">
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Désignation</th><th class="num">Qté</th><th class="num">Px unit. HT</th><th class="num">Remise</th><th class="num">Px unit. net</th><th class="num">Montant HT</th></tr></thead>
        <tbody>
        @foreach ($document->lines as $l)
            <tr><td>{{ $l->designation }}@if($l->reference)<div class="muted small mono">{{ $l->reference }}</div>@endif</td><td class="num">{{ $l->quantity }}</td><td class="num">{{ money($l->unit_price) }}</td><td class="num">{{ $l->discount_rate > 0 ? rtrim(rtrim(number_format($l->discount_rate, 2, ',', ' '), '0'), ',').' %' : '—' }}</td><td class="num">{{ money($l->netUnitPrice()) }}</td><td class="num">{{ money($l->line_total) }}</td></tr>
        @endforeach
        </tbody>
    </table></div>
    <div style="padding:1rem 1.2rem">
        <div class="totals">
            <div class="row"><span>Total HT brut</span><span>{{ money($document->grossTotal()) }}</span></div>
            @if ($document->totalDiscount())<div class="row"><span>Total remise</span><span>− {{ money($document->totalDiscount()) }}</span></div>@endif
            <div class="row"><span>Total HT net</span><span>{{ money($document->netTotal()) }}</span></div>
            @if ($document->delivery_fee)<div class="row"><span>Livraison</span><span>{{ money($document->delivery_fee) }}</span></div>@endif
            <div class="row grand"><span>Total TTC</span><span>{{ money($document->total) }}</span></div>
            {{ $slot ?? '' }}
        </div>
    </div>
</div>
