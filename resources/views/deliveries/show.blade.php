@extends('layouts.app')
@section('title', 'Livraison '.$delivery->order->number)
@section('content')
@php $order = $delivery->order; $canManage = auth()->user()->hasPermission('deliveries.manage'); @endphp
<div class="page-header">
    <div>
        <h1>Livraison <span class="mono">{{ $order->number }}</span> {!! badge($delivery->status) !!}</h1>
        <p class="subtitle">{{ $delivery->store->name }} · Commande {!! badge($order->status) !!}</p>
    </div>
    <div class="actions">
        @can('orders.view')<a class="btn btn-secondary" href="{{ route('orders.show', $order) }}">Voir la commande</a>@endcan
        <button class="btn btn-secondary" onclick="window.print()"><x-icon name="print" /> Bon de livraison</button>
    </div>
</div>

@if ($canManage && count($delivery->status->allowedTransitions()))
    <div class="card">
        <h2>Suivi</h2>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap">
            @foreach ($delivery->status->allowedTransitions() as $t)
                @if (! $t->requiresReason())
                    <form method="post" action="{{ route('deliveries.status', $delivery) }}" class="inline-form">@csrf<input type="hidden" name="status" value="{{ $t->value }}"><button class="btn {{ $t === \App\Enums\DeliveryStatus::Delivered ? 'btn-success' : '' }}" type="submit">{{ $t === \App\Enums\DeliveryStatus::InTransit ? 'Départ (en route)' : $t->label() }}</button></form>
                @endif
            @endforeach
        </div>
        @php $withReason = array_filter($delivery->status->allowedTransitions(), fn ($t) => $t->requiresReason()); @endphp
        @if ($withReason)
            <details class="panel" style="margin-top:1rem"><summary>Signaler un échec ou un retour</summary>
                <div>
                    <form method="post" action="{{ route('deliveries.status', $delivery) }}">
                        @csrf
                        <div class="form-grid">
                            <x-field name="status" label="Situation" type="select" :options="collect($withReason)->mapWithKeys(fn ($t) => [$t->value => $t->label()])->all()" required />
                            <x-field name="reason" label="Motif" required placeholder="Client absent, adresse introuvable, produit refusé…" />
                            <x-field name="notes" label="Observations" type="textarea" class="full" rows="2" />
                        </div>
                        <div class="form-actions"><button class="btn btn-danger" type="submit">Enregistrer</button></div>
                    </form>
                    <p class="muted small" style="margin:0">Un retour remet les produits dans le stock du magasin.</p>
                </div>
            </details>
        @endif
    </div>
@endif

<div class="grid grid-2">
    <div>
        <div class="card">
            <h2>Informations de livraison</h2>
            @if ($canManage)
                <form method="post" action="{{ route('deliveries.update', $delivery) }}">
                    @csrf @method('PUT')
                    <div class="form-grid">
                        <x-field name="address" label="Adresse" :value="$delivery->address" required class="full" />
                        <x-field name="city" label="Ville" :value="$delivery->city" />
                        <x-field name="scheduled_date" label="Date prévue" type="date" :value="$delivery->scheduled_date" />
                        <x-field name="contact_name" label="Contact" :value="$delivery->contact_name" />
                        <x-field name="contact_phone" label="Téléphone" type="tel" :value="$delivery->contact_phone" />
                        <x-field name="driver_id" label="Livreur (équipe)" type="select" :options="$drivers->pluck('name', 'id')" :value="$delivery->driver_id" placeholder="— Non attribué —" />
                        <x-field name="driver_name" label="ou livreur externe" :value="$delivery->driver_name" />
                        <x-field name="fee" label="Frais de livraison (F CFA)" type="number" min="0" :value="$delivery->fee" />
                        <x-field name="notes" label="Notes" type="textarea" :value="$delivery->notes" class="full" rows="2" />
                    </div>
                    <div class="form-actions"><button class="btn" type="submit">Enregistrer</button></div>
                </form>
            @else
                <dl class="details">
                    <dt>Adresse</dt><dd>{{ $delivery->address }} {{ $delivery->city }}</dd>
                    <dt>Contact</dt><dd>{{ $delivery->contact_name }} {{ $delivery->contact_phone }}</dd>
                    <dt>Date prévue</dt><dd>{{ $delivery->scheduled_date?->format('d/m/Y') ?? 'À définir' }}</dd>
                    <dt>Livreur</dt><dd>{{ $delivery->driverLabel() ?? '—' }}</dd>
                    <dt>Frais</dt><dd>{{ money($delivery->fee) }}</dd>
                </dl>
            @endif
        </div>
    </div>
    <div>
        <div class="card">
            <h2>À livrer</h2>
            <div class="table-wrap"><table class="table">
                <tbody>@foreach ($order->items as $i)<tr><td>{{ $i->product_name }}<div class="muted small mono">{{ $i->product_reference }}</div></td><td class="num strong">× {{ $i->quantity }}</td></tr>@endforeach</tbody>
            </table></div>
            <div class="totals" style="margin-top:.75rem">
                <div class="row"><span>Total commande</span><strong>{{ money($order->total) }}</strong></div>
                <div class="row {{ $order->balanceDue() ? 'text-warning strong' : '' }}"><span>Reste à encaisser</span><span>{{ money($order->balanceDue()) }}</span></div>
            </div>
        </div>
        <div class="card">
            <h2>Suivi</h2>
            <dl class="details">
                <dt>Départ</dt><dd>{{ $delivery->departed_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                <dt>Livrée le</dt><dd>{{ $delivery->delivered_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                @if ($delivery->failure_reason)<dt>Motif</dt><dd class="text-danger">{{ $delivery->failure_reason }}</dd>@endif
            </dl>
            <ul class="timeline" style="margin-top:1rem">
                @foreach ($order->history as $h)
                    <li><div>{!! badge($h->to_status) !!} <span class="small">{{ $h->comment }}</span></div><div class="when">{{ $h->created_at?->format('d/m/Y H:i') }} {{ $h->user?->name ? '· '.$h->user->name : '' }}</div></li>
                @endforeach
            </ul>
        </div>
    </div>
</div>
@endsection
