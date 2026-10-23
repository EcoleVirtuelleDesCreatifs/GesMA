@extends('layouts.app')
@section('title', 'Facture '.$invoice->number)
@section('content')
<div class="page-head">
    <h1>Facture {{ $invoice->number }} <span class="badge {{ $balance <= 0 ? 'badge-success' : ($invoice->isDue() ? 'badge-danger' : 'badge-warning') }}">{{ $invoice->statusLabel() }}</span></h1>
    <div class="actions">
        @if($invoice->attachment)<a class="btn" href="{{ route('supplier-invoices.attachment', $invoice) }}">Pièce jointe</a>@endif
        @if($invoice->purchaseOrder)<a class="btn" href="{{ route('purchases.show', $invoice->purchaseOrder) }}">Commande {{ $invoice->purchaseOrder->number }}</a>@endif
    </div>
</div>

<div class="grid grid-2 mb">
    <div class="card">
        <div class="card-head"><h2>Facture</h2></div>
        <ul class="list">
            <li><span class="muted">Fournisseur</span><a href="{{ route('suppliers.show', $invoice->supplier) }}">{{ $invoice->supplier->name }}</a></li>
            <li><span class="muted">Date de facturation</span><span>{{ $invoice->invoiced_at->format('d/m/Y') }}</span></li>
            <li><span class="muted">Échéance</span><span>{{ $invoice->due_at?->format('d/m/Y') ?? '—' }}@if($invoice->isDue()) <span class="badge badge-danger">Dépassée</span>@endif</span></li>
            <li><span class="muted">Montant total</span><strong class="num">@money($invoice->total)</strong></li>
            <li><span class="muted">Déjà réglé</span><span class="num">@money($paid)</span></li>
            <li><strong>Solde restant</strong><strong class="num">@money($balance)</strong></li>
            @if($invoice->notes)<li><span class="muted">Observations</span><span>{{ $invoice->notes }}</span></li>@endif
        </ul>
    </div>
    <div class="card">
        <div class="card-head"><h2>Règlements</h2></div>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Date</th><th>Mode</th><th>Référence</th><th>Par</th><th class="right">Montant</th></tr></thead>
            <tbody>
            @forelse($invoice->payments as $p)
                <tr>
                    <td class="nowrap">{{ $p->paid_at->format('d/m/Y') }}</td>
                    <td>{{ $p->methodLabel() }}</td>
                    <td>{{ $p->reference ?? '—' }}</td>
                    <td>{{ $p->user?->name ?? '—' }}</td>
                    <td class="right num">@money($p->amount)</td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">Aucun règlement.</td></tr>
            @endforelse
            </tbody>
        </table></div>

        @can('supplier_payments.manage')
        @if($balance > 0)
        <form method="POST" action="{{ route('supplier-invoices.pay', $invoice) }}" enctype="multipart/form-data" class="filters" style="margin-top:.8rem">
            @csrf
            <div class="field"><label>Date</label><input type="date" name="paid_at" value="{{ today()->toDateString() }}" required></div>
            <div class="field"><label>Montant (max {{ money($balance) }})</label><input type="number" min="1" step="1" name="amount" value="{{ (int) $balance }}" required></div>
            <div class="field"><label>Mode</label>
                <select name="method">@foreach($methods as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
            </div>
            <div class="field"><label>Référence</label><input name="reference" placeholder="N° chèque, transaction…"></div>
            <div class="field"><label>Justificatif</label><input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png"></div>
            <button class="btn btn-sm btn-primary">Enregistrer le règlement</button>
        </form>
        <small class="muted">Chaque règlement crée automatiquement l'écriture de trésorerie « Achat de marchandises » (pas de double saisie).</small>
        @endif
        @endcan
    </div>
</div>
@endsection
