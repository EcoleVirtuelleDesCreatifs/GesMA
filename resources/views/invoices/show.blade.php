@extends('layouts.app')
@section('title', 'Facture '.$invoice->number)
@section('content')
@php $cancelled = $invoice->status === \App\Enums\InvoiceStatus::Cancelled; @endphp
<div class="page-header">
    <div>
        <h1>Facture <span class="mono">{{ $invoice->number }}</span> {!! badge($invoice->status) !!}</h1>
        <p class="subtitle">{{ $invoice->issued_at->format('d/m/Y') }}@if($invoice->due_at) · échéance {{ $invoice->due_at->format('d/m/Y') }}@endif · {{ $invoice->store->name }}
            @if($invoice->order) · Commande <a class="mono" href="{{ route('orders.show', $invoice->order) }}">{{ $invoice->order->number }}</a>@endif
            @if($invoice->quote) · Devis <a class="mono" href="{{ route('quotes.show', $invoice->quote) }}">{{ $invoice->quote->number }}</a>@endif
        </p>
    </div>
    <div class="actions">
        <a class="btn" href="{{ route('invoices.pdf', $invoice) }}"><x-icon name="download" /> PDF</a>
        <a class="btn btn-secondary" href="{{ route('invoices.pdf', ['invoice' => $invoice, 'inline' => 1]) }}" target="_blank"><x-icon name="print" /> Aperçu</a>
    </div>
</div>
<div class="grid grid-2-1">
    <div>
        @component('partials.document-lines', ['document' => $invoice])
            <div class="row"><span>Payé</span><span>{{ money($invoice->amount_paid) }}</span></div>
            @if (! $cancelled && $invoice->balanceDue())<div class="row strong text-warning"><span>Reste à payer</span><span>{{ money($invoice->balanceDue()) }}</span></div>@endif
        @endcomponent
        @if ($invoice->notes)<div class="card"><h2>Notes</h2><p style="margin:0">{!! nl2br(e($invoice->notes)) !!}</p></div>@endif
    </div>
    <div>
        <div class="card">
            <h2>Client</h2>
            <p style="margin:0"><a class="strong" href="{{ route('customers.show', $invoice->customer) }}">{{ $invoice->customer->name }}</a><br><span class="muted">{{ $invoice->customer->phone }} {{ $invoice->customer->address }} {{ $invoice->customer->city }}</span></p>
        </div>
        @if (! $cancelled && $invoice->balanceDue() > 0)
            <div class="card">
                <h2>Enregistrer un paiement</h2>
                <form method="post" action="{{ route('invoices.payment', $invoice) }}">
                    @csrf
                    <x-field name="amount" label="Montant (F CFA)" type="number" min="1" :value="$invoice->balanceDue()" required />
                    <button class="btn btn-success btn-block" type="submit">Encaisser</button>
                </form>
            </div>
        @endif
        @unless ($cancelled)
            <details class="panel"><summary>Annuler la facture</summary>
                <div>
                    <form method="post" action="{{ route('invoices.cancel', $invoice) }}" data-confirm="Annuler définitivement cette facture ?">
                        @csrf
                        <x-field name="reason" label="Motif" required />
                        <button class="btn btn-danger btn-block" type="submit">Annuler la facture</button>
                    </form>
                </div>
            </details>
        @endunless
    </div>
</div>
@endsection
