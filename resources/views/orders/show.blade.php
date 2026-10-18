@extends('layouts.app')
@section('title', 'Commande '.$order->number)
@section('content')
<div class="page-head">
    <div>
        <h1>Commande {{ $order->number }} <span class="badge {{ status_badge($order->status) }}">{{ $order->statusLabel() }}</span></h1>
        <small class="muted">{{ $order->ordered_at->format('d/m/Y à H:i') }} · {{ $order->store->name }} · {{ $order->sourceLabel() }}
            @if($order->external_reference) (réf. {{ $order->external_reference }}) @endif
            @if($order->user) · saisie par {{ $order->user->name }} @endif
        </small>
    </div>
    <div class="actions">
        @if($order->delivery)<a class="btn" href="{{ route('deliveries.edit', $order->delivery) }}">Livraison</a>@endif
        @php($invoice = $order->documents->firstWhere('type', 'facture'))
        @if($invoice)
            <a class="btn" href="{{ route('documents.pdf', $invoice) }}">Facture PDF</a>
        @elseif(!in_array($order->status, ['annulee', 'retournee']))
            @can('documents.manage')
                <form method="POST" action="{{ route('orders.invoice', $order) }}" class="inline-form">@csrf<button class="btn">Générer la facture</button></form>
            @endcan
        @endif
    </div>
</div>

<div class="grid grid-main">
    <div>
        <div class="card">
            <div class="card-head"><h2>Produits</h2></div>
            <div class="table-wrap">
                <table class="table responsive">
                    <thead><tr><th>Produit</th><th class="right">Qté</th><th class="right">Prix unitaire</th><th class="right">Total</th></tr></thead>
                    <tbody>
                    @foreach($order->items as $item)
                        <tr>
                            <td data-label="Produit">
                                @if($item->product_id)<a href="{{ route('products.show', $item->product_id) }}">{{ $item->product_name }}</a>@else {{ $item->product_name }} @endif
                                <br><small class="muted">{{ $item->product_reference }}</small>
                            </td>
                            <td data-label="Qté" class="right num">{{ $item->quantity }}</td>
                            <td data-label="Prix unitaire" class="right num">@money($item->unit_price)</td>
                            <td data-label="Total" class="right num">@money($item->total)</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="totals mt">
                <div><span>Sous-total</span><span class="num">@money($order->subtotal)</span></div>
                @if((float) $order->discount > 0)<div><span>Remise</span><span class="num">− @money($order->discount)</span></div>@endif
                @if((float) $order->delivery_fee > 0)<div><span>Livraison</span><span class="num">@money($order->delivery_fee)</span></div>@endif
                <div class="grand"><span>Total</span><span class="num">@money($order->total)</span></div>
                <div><span>Encaissé</span><span class="num">@money($order->amount_paid)</span></div>
                <div><strong>Reste à payer</strong><strong class="num" style="color: {{ $order->balance() > 0 ? 'var(--danger)' : 'var(--success)' }}">@money($order->balance())</strong></div>
            </div>
            @if($order->notes)<p class="mt"><strong>Notes :</strong> {{ $order->notes }}</p>@endif
        </div>

        @if($transitions->isNotEmpty())
            @can('orders.status')
            <div class="card">
                <div class="card-head"><h2>Faire évoluer la commande</h2></div>
                <form method="POST" action="{{ route('orders.status', $order) }}" class="form-grid">
                    @csrf @method('PUT')
                    <div class="field">
                        <label for="status">Nouveau statut</label>
                        <select name="status" id="status" required>
                            @foreach($transitions as $s)<option value="{{ $s }}">{{ config("gesma.order_statuses.$s") }}</option>@endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="reason">Motif / commentaire</label>
                        <input type="text" name="reason" id="reason" placeholder="Obligatoire pour annulation, retour, non livrée">
                    </div>
                    <div class="full form-actions" style="margin-top:0">
                        <button class="btn btn-primary" onclick="return this.form.status.value !== 'annulee' || confirm('Confirmer l’annulation ? Les produits seront remis en stock.')">Mettre à jour</button>
                    </div>
                </form>
                <p class="muted" style="font-size:.82rem">Une annulation ou un retour remet automatiquement les produits en stock et est enregistré dans l’historique.</p>
            </div>
            @endcan
        @endif
    </div>

    <div>
        <div class="card">
            <h2>Client</h2>
            @if($order->customer)
                <p><a href="{{ route('customers.show', $order->customer) }}"><strong>{{ $order->customer->name }}</strong></a><br>
                    @if($order->customer->phone)<a href="tel:{{ $order->customer->phone }}">{{ $order->customer->phone }}</a><br>@endif
                    <small class="muted">{{ $order->customer->address }} {{ $order->customer->city }}</small></p>
            @else
                <p class="muted">Client comptoir (sans fiche).</p>
            @endif
        </div>

        @if($order->delivery)
            <div class="card">
                <div class="card-head"><h2>Livraison</h2><span class="badge {{ status_badge($order->delivery->status) }}">{{ $order->delivery->statusLabel() }}</span></div>
                <dl class="dl">
                    <dt>Adresse</dt><dd>{{ $order->delivery->address }} {{ $order->delivery->city }}</dd>
                    <dt>Prévue le</dt><dd>{{ $order->delivery->scheduled_date?->format('d/m/Y') ?? '—' }}</dd>
                    <dt>Livreur</dt><dd>{{ $order->delivery->driverLabel() }}</dd>
                    <dt>Frais</dt><dd>@money($order->delivery->fee)</dd>
                    @if($order->delivery->failure_reason)<dt>Motif</dt><dd>{{ $order->delivery->failure_reason }}</dd>@endif
                </dl>
            </div>
        @endif

        @if($order->balance() > 0 && !in_array($order->status, ['annulee', 'retournee']))
            @can('orders.create')
            <div class="card">
                <h2>Enregistrer un paiement</h2>
                <form method="POST" action="{{ route('orders.payment', $order) }}">
                    @csrf
                    <div class="field mb"><label for="amount">Montant</label><input type="number" min="1" max="{{ (int) $order->balance() }}" name="amount" id="amount" value="{{ (int) $order->balance() }}" required></div>
                    <div class="field mb">
                        <label for="payment_method">Mode</label>
                        <select name="payment_method" id="payment_method">
                            @foreach(config('gesma.payment_methods') as $k => $l)<option value="{{ $k }}" @selected($order->payment_method === $k)>{{ $l }}</option>@endforeach
                        </select>
                    </div>
                    <button class="btn btn-success btn-block">Enregistrer le paiement</button>
                </form>
            </div>
            @endcan
        @endif

        <div class="card">
            <h2>Historique</h2>
            <ul class="timeline">
                @foreach($order->statusHistory as $h)
                    <li>
                        <strong>{{ config("gesma.order_statuses.{$h->to_status}", $h->to_status) }}</strong>
                        <small class="muted">— {{ $h->created_at->format('d/m/Y H:i') }}{{ $h->user ? ' · '.$h->user->name : '' }}</small>
                        @if($h->reason)<br><small>{{ $h->reason }}</small>@endif
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</div>
@endsection
