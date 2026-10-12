@extends('layouts.app')
@section('title', 'Devis '.$quote->number)
@section('content')
@php $locked = $quote->order_id || $quote->status === \App\Enums\QuoteStatus::Accepted; @endphp
<div class="page-header">
    <div>
        <h1>Devis <span class="mono">{{ $quote->number }}</span> {!! badge($quote->status) !!}</h1>
        <p class="subtitle">{{ $quote->issued_at->format('d/m/Y') }}@if($quote->valid_until) · valable jusqu'au {{ $quote->valid_until->format('d/m/Y') }}@endif · {{ $quote->store->name }} · {{ $quote->user?->name }}</p>
    </div>
    <div class="actions">
        <a class="btn" href="{{ route('quotes.pdf', $quote) }}"><x-icon name="download" /> PDF</a>
        <a class="btn btn-secondary" href="{{ route('quotes.pdf', ['quote' => $quote, 'inline' => 1]) }}" target="_blank"><x-icon name="print" /> Aperçu</a>
        @unless ($locked)<a class="btn btn-secondary" href="{{ route('quotes.edit', $quote) }}"><x-icon name="edit" /> Modifier</a>@endunless
    </div>
</div>
<div class="grid grid-2-1">
    <div>
        @include('partials.document-lines', ['document' => $quote])
        @if ($quote->notes)<div class="card"><h2>Notes</h2><p style="margin:0">{!! nl2br(e($quote->notes)) !!}</p></div>@endif
    </div>
    <div>
        <div class="card">
            <h2>Client</h2>
            <p style="margin:0"><a class="strong" href="{{ route('customers.show', $quote->customer) }}">{{ $quote->customer->name }}</a><br><span class="muted">{{ $quote->customer->phone }} {{ $quote->customer->city }}</span></p>
        </div>
        <div class="card">
            <h2>Suite à donner</h2>
            @if ($quote->order)
                <p>Commande créée : <a class="mono" href="{{ route('orders.show', $quote->order) }}">{{ $quote->order->number }}</a></p>
            @else
                @can('orders.manage')
                    <form method="post" action="{{ route('quotes.order', $quote) }}" data-confirm="Créer la commande ? Le stock sera vérifié et réservé.">
                        @csrf
                        @if (! $quote->delivery_fee)<label class="checkbox" style="margin-bottom:.5rem"><input type="checkbox" name="requires_delivery" value="1"> Avec livraison</label>@endif
                        <button class="btn btn-block" type="submit"><x-icon name="cart" /> Transformer en commande</button>
                    </form>
                @endcan
            @endif
            @foreach ($quote->invoices as $inv)
                <p style="margin:.6rem 0 0">Facture : <a class="mono" href="{{ route('invoices.show', $inv) }}">{{ $inv->number }}</a> {!! badge($inv->status) !!}</p>
            @endforeach
            @if ($quote->invoices->isEmpty())
                @can('invoices.manage')
                    <form method="post" action="{{ route('quotes.invoice', $quote) }}" style="margin-top:.6rem">@csrf<button class="btn btn-secondary btn-block" type="submit"><x-icon name="receipt" /> Générer la facture</button></form>
                @endcan
            @endif
        </div>
        <div class="card">
            <h2>Statut</h2>
            <form method="post" action="{{ route('quotes.status', $quote) }}" style="display:flex;gap:.5rem">
                @csrf
                <select name="status">@foreach (\App\Enums\QuoteStatus::options() as $v => $l)<option value="{{ $v }}" @selected($quote->status->value === $v)>{{ $l }}</option>@endforeach</select>
                <button class="btn btn-secondary" type="submit">OK</button>
            </form>
        </div>
    </div>
</div>
@endsection
