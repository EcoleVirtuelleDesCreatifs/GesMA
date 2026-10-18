@extends('layouts.app')
@section('title', 'Livraison '.$delivery->order->number)
@section('content')
<div class="page-head">
    <div>
        <h1>Livraison — commande <a href="{{ route('orders.show', $delivery->order) }}">{{ $delivery->order->number }}</a></h1>
        <span class="badge {{ status_badge($delivery->status) }}">{{ $delivery->statusLabel() }}</span>
        @if($delivery->failure_reason)<small class="muted"> · {{ $delivery->failure_reason }}</small>@endif
    </div>
</div>

<div class="grid grid-main">
    <div>
        @can('deliveries.manage')
        @if($allowedStatuses->isNotEmpty())
            <div class="card">
                <div class="card-head"><h2>Mettre à jour le suivi</h2></div>
                <form method="POST" action="{{ route('deliveries.status', $delivery) }}">
                    @csrf @method('PUT')
                    <div class="radio-cards mb">
                        @foreach($allowedStatuses as $s)
                            <label><input type="radio" name="status" value="{{ $s }}" required @checked($loop->first)>
                                <span><strong>{{ config("gesma.delivery_statuses.$s") }}</strong>
                                <small class="muted">{{ ['en_route' => 'Le livreur part avec la commande', 'livree' => 'Remise au client', 'echec' => 'Client absent, adresse introuvable…', 'retour' => 'Produits revenus en magasin', 'annulee' => 'Commande annulée'][$s] ?? '' }}</small></span>
                            </label>
                        @endforeach
                    </div>
                    <div class="field mb">
                        <label for="reason">Motif (obligatoire en cas d’échec, de retour ou d’annulation)</label>
                        <input type="text" name="reason" id="reason" value="{{ old('reason') }}" maxlength="255">
                    </div>
                    <button class="btn btn-primary">Enregistrer</button>
                </form>
            </div>
        @endif
        @endcan

        <div class="card">
            <div class="card-head"><h2>Informations de livraison</h2></div>
            <form method="POST" action="{{ route('deliveries.update', $delivery) }}">
                @csrf @method('PUT')
                @cannot('deliveries.manage')<fieldset disabled style="border:0;padding:0;margin:0">@endcannot
                <div class="form-grid">
                    <div class="field full"><label for="address">Adresse *</label><input type="text" id="address" name="address" value="{{ old('address', $delivery->address) }}" required></div>
                    <div class="field"><label for="city">Ville</label><input type="text" id="city" name="city" value="{{ old('city', $delivery->city) }}"></div>
                    <div class="field"><label for="scheduled_date">Date prévue</label><input type="date" id="scheduled_date" name="scheduled_date" value="{{ old('scheduled_date', $delivery->scheduled_date?->toDateString()) }}"></div>
                    <div class="field"><label for="contact_name">Contact</label><input type="text" id="contact_name" name="contact_name" value="{{ old('contact_name', $delivery->contact_name) }}"></div>
                    <div class="field"><label for="contact_phone">Téléphone</label><input type="tel" id="contact_phone" name="contact_phone" value="{{ old('contact_phone', $delivery->contact_phone) }}"></div>
                    <div class="field">
                        <label for="driver_id">Livreur / responsable</label>
                        <select id="driver_id" name="driver_id">
                            <option value="">— À désigner —</option>
                            @foreach($drivers as $d)<option value="{{ $d->id }}" @selected(old('driver_id', $delivery->driver_id) == $d->id)>{{ $d->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="field"><label for="driver_name">Livreur externe</label><input type="text" id="driver_name" name="driver_name" value="{{ old('driver_name', $delivery->driver_name) }}"></div>
                    <div class="field"><label for="fee">Frais de livraison</label><input type="number" min="0" id="fee" name="fee" value="{{ old('fee', (int) $delivery->fee) }}"></div>
                    <div class="field full"><label for="notes">Notes</label><textarea id="notes" name="notes">{{ old('notes', $delivery->notes) }}</textarea></div>
                </div>
                @can('deliveries.manage')
                    <div class="form-actions"><button class="btn">Enregistrer les informations</button></div>
                @else
                    </fieldset>
                @endcan
            </form>
        </div>
    </div>
    <div>
        <div class="card">
            <h2>Commande</h2>
            <p><strong>{{ $delivery->order->customer?->name ?? $delivery->contact_name }}</strong><br>
                @if($delivery->contact_phone)<a href="tel:{{ $delivery->contact_phone }}">{{ $delivery->contact_phone }}</a>@endif</p>
            <ul class="list">
                @foreach($delivery->order->items as $item)
                    <li><span>{{ $item->quantity }} × {{ $item->product_name }}</span></li>
                @endforeach
            </ul>
            <div class="totals mt">
                <div><span>Total</span><strong class="num">@money($delivery->order->total)</strong></div>
                <div><span>Reste à encaisser</span><strong class="num" style="color: var(--danger)">@money($delivery->order->balance())</strong></div>
            </div>
        </div>
        <div class="card">
            <h2>Suivi</h2>
            <dl class="dl">
                <dt>Départ</dt><dd>{{ $delivery->departed_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                <dt>Terminée</dt><dd>{{ $delivery->completed_at?->format('d/m/Y H:i') ?? '—' }}</dd>
            </dl>
        </div>
    </div>
</div>
@endsection
