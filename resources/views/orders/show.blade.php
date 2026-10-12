@extends('layouts.app')
@section('title', 'Commande '.$order->number)
@section('content')
@php
    $user = auth()->user();
    $transitions = $order->status->allowedTransitions();
    $canChange = $user->hasPermission('orders.manage') || $user->hasPermission('deliveries.manage');
@endphp
<div class="page-header">
    <div>
        <h1>{{ $order->type === \App\Enums\OrderType::Sale ? 'Vente' : 'Commande' }} <span class="mono">{{ $order->number }}</span> {!! badge($order->status) !!}</h1>
        <p class="subtitle">
            {{ $order->ordered_at->format('d/m/Y à H:i') }} · {{ $order->store->name }} · Origine : {{ $order->source->label() }}
            @if ($order->external_reference) · Réf. site : <span class="mono">{{ $order->external_reference }}</span>@endif
            @if ($order->user) · Saisie par {{ $order->user->name }}@endif
        </p>
    </div>
    <div class="actions">
        @can('invoices.manage')
            @if ($order->customer_id && $order->status->holdsStock())
                @php $invoice = $order->invoices->firstWhere('status', '!=', \App\Enums\InvoiceStatus::Cancelled); @endphp
                @if ($invoice)
                    <a class="btn btn-secondary" href="{{ route('invoices.show', $invoice) }}"><x-icon name="receipt" /> Facture {{ $invoice->number }}</a>
                @else
                    <form method="post" action="{{ route('invoices.from-order', $order) }}" class="inline-form">@csrf<button class="btn btn-secondary" type="submit"><x-icon name="receipt" /> Créer la facture</button></form>
                @endif
            @endif
        @endcan
        <button class="btn btn-secondary" type="button" onclick="window.print()"><x-icon name="print" /> Imprimer</button>
    </div>
</div>

<div class="grid grid-2-1">
    <div>
        <div class="card flush">
            <div class="card-header"><h2>Produits</h2></div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Produit</th><th class="num">Qté</th><th class="num">Prix unitaire</th><th class="num">Total</th></tr></thead>
                    <tbody>
                    @foreach ($order->items as $item)
                        <tr>
                            <td>@if($item->product)<a href="{{ route('products.show', $item->product) }}">{{ $item->product_name }}</a>@else{{ $item->product_name }}@endif<div class="muted small mono">{{ $item->product_reference }}</div></td>
                            <td class="num">{{ $item->quantity }}</td>
                            <td class="num">{{ money($item->unit_price) }}</td>
                            <td class="num">{{ money($item->line_total) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div style="padding: 1rem 1.2rem">
                <div class="totals">
                    <div class="row"><span>Sous-total</span><span>{{ money($order->subtotal) }}</span></div>
                    @if ($order->discount)<div class="row"><span>Remise</span><span>− {{ money($order->discount) }}</span></div>@endif
                    @if ($order->delivery_fee)<div class="row"><span>Livraison</span><span>{{ money($order->delivery_fee) }}</span></div>@endif
                    <div class="row grand"><span>Total</span><span>{{ money($order->total) }}</span></div>
                    <div class="row"><span>Encaissé</span><span>{{ money($order->amount_paid) }}@if($order->payment_method) <span class="muted small">({{ config('gesma.payment_methods.'.$order->payment_method, $order->payment_method) }})</span>@endif</span></div>
                    @if ($order->balanceDue() > 0)<div class="row strong text-warning"><span>Reste à payer</span><span>{{ money($order->balanceDue()) }}</span></div>@endif
                </div>
            </div>
        </div>

        @if ($order->notes || $order->status_reason)
            <div class="card">
                @if ($order->status_reason)<p><strong>Motif ({{ $order->status->label() }}) :</strong> {{ $order->status_reason }}</p>@endif
                @if ($order->notes)<p style="margin:0"><strong>Notes :</strong> {!! nl2br(e($order->notes)) !!}</p>@endif
            </div>
        @endif

        <div class="card">
            <div class="card-header"><h2>Livraison</h2>@if($order->delivery)<a class="btn btn-secondary btn-sm" href="{{ route('deliveries.show', $order->delivery) }}">Gérer la livraison</a>@endif</div>
            @if ($order->delivery)
                @php $d = $order->delivery; @endphp
                <dl class="details">
                    <dt>Statut</dt><dd>{!! badge($d->status) !!}</dd>
                    <dt>Adresse</dt><dd>{{ $d->address }}{{ $d->city ? ', '.$d->city : '' }}</dd>
                    <dt>Contact</dt><dd>{{ $d->contact_name }} @if($d->contact_phone)<a href="tel:{{ $d->contact_phone }}">{{ $d->contact_phone }}</a>@endif</dd>
                    <dt>Date prévue</dt><dd>{{ $d->scheduled_date?->format('d/m/Y') ?? 'À définir' }}</dd>
                    <dt>Livreur</dt><dd>{{ $d->driverLabel() ?? 'Non attribué' }}</dd>
                    <dt>Frais</dt><dd>{{ money($d->fee) }}</dd>
                    @if ($d->failure_reason)<dt>Motif</dt><dd class="text-danger">{{ $d->failure_reason }}</dd>@endif
                </dl>
            @elseif ($order->type === \App\Enums\OrderType::Order && $order->status === \App\Enums\OrderStatus::Preparing && $user->hasPermission('orders.manage'))
                <p class="muted">Retrait en magasin. Vous pouvez programmer une livraison :</p>
                <details class="panel"><summary>Programmer une livraison</summary>
                    <div>
                        <form method="post" action="{{ route('orders.delivery', $order) }}">
                            @csrf
                            <div class="form-grid">
                                <x-field name="address" label="Adresse" :value="$order->customer?->address" required class="full" />
                                <x-field name="city" label="Ville" :value="$order->customer?->city" />
                                <x-field name="scheduled_date" label="Date prévue" type="date" />
                                <x-field name="contact_name" label="Contact" :value="$order->customer?->name" />
                                <x-field name="contact_phone" label="Téléphone" :value="$order->customer?->phone" />
                                <x-field name="driver_id" label="Livreur" type="select" :options="$drivers->pluck('name', 'id')" placeholder="— À attribuer —" />
                                <x-field name="fee" label="Frais (F CFA)" type="number" min="0" value="0" />
                            </div>
                            <div class="form-actions"><button class="btn" type="submit">Programmer</button></div>
                        </form>
                    </div>
                </details>
            @else
                <p class="muted" style="margin:0">Aucune livraison (retrait en magasin).</p>
            @endif
        </div>
    </div>

    <div>
        <div class="card">
            <h2>Client</h2>
            @if ($order->customer)
                <p style="margin:0"><a class="strong" href="{{ route('customers.show', $order->customer) }}">{{ $order->customer->name }}</a></p>
                <p class="muted" style="margin:0">
                    @if($order->customer->phone)<a href="tel:{{ $order->customer->phone }}">{{ $order->customer->phone }}</a><br>@endif
                    {{ $order->customer->email }}@if($order->customer->email)<br>@endif
                    {{ $order->customer->address }} {{ $order->customer->city }}
                </p>
            @else
                <p class="muted" style="margin:0">Client comptoir (non identifié).</p>
            @endif
        </div>

        @if ($canChange && count($transitions))
            <div class="card">
                <h2>Changer le statut</h2>
                <form method="post" action="{{ route('orders.status', $order) }}" data-confirm="Confirmer le changement de statut ?">
                    @csrf
                    <div class="field">
                        <label for="status">Nouveau statut</label>
                        <select name="status" id="status" required>
                            @foreach ($transitions as $t)
                                @if ($t !== \App\Enums\OrderStatus::Cancelled || $user->hasPermission('orders.cancel'))
                                    <option value="{{ $t->value }}">{{ $t->label() }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                    <x-field name="reason" label="Motif / commentaire" help="Obligatoire pour une annulation, un retour ou une non-livraison." />
                    <button class="btn btn-block" type="submit">Mettre à jour</button>
                </form>
                <p class="muted small" style="margin:.6rem 0 0">Une annulation ou un retour remet automatiquement les produits en stock.</p>
            </div>
        @endif

        @if ($order->balanceDue() > 0 && $order->status->holdsStock() && $user->hasPermission('orders.manage'))
            <div class="card">
                <h2>Enregistrer un paiement</h2>
                <form method="post" action="{{ route('orders.payment', $order) }}">
                    @csrf
                    <x-field name="amount" label="Montant (F CFA)" type="number" min="1" :value="$order->balanceDue()" required />
                    <x-field name="payment_method" label="Mode de paiement" type="select" :options="config('gesma.payment_methods')" :value="$order->payment_method" placeholder="—" />
                    <button class="btn btn-success btn-block" type="submit">Encaisser</button>
                </form>
            </div>
        @endif

        <div class="card">
            <h2>Historique</h2>
            <ul class="timeline">
                @foreach ($order->history as $h)
                    <li>
                        <div>{!! badge($h->to_status) !!} @if($h->comment)<span class="small">{{ $h->comment }}</span>@endif</div>
                        <div class="when">{{ $h->created_at?->format('d/m/Y H:i') }} @if($h->user)· {{ $h->user->name }}@endif</div>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</div>
@endsection
