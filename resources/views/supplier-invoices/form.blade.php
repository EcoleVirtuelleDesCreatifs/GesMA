@extends('layouts.app')
@section('title', 'Nouvelle facture fournisseur')
@section('content')
<div class="page-head"><h1>Enregistrer une facture fournisseur</h1></div>

<form method="POST" action="{{ route('supplier-invoices.store') }}" enctype="multipart/form-data" class="card">
    @csrf
    <div class="grid grid-3">
        <div class="field"><label>Fournisseur *</label>
            <select name="supplier_id" required><option value="">—</option>
                @foreach($suppliers as $s)<option value="{{ $s->id }}" @selected((int) old('supplier_id', request('supplier_id')) === $s->id)>{{ $s->name }}</option>@endforeach
            </select>
        </div>
        <div class="field"><label>N° de facture *</label><input name="number" value="{{ old('number') }}" required></div>
        <div class="field"><label>Commande associée</label>
            <select name="purchase_order_id"><option value="">—</option>
                @foreach($orders as $o)<option value="{{ $o->id }}" @selected((int) old('purchase_order_id') === $o->id)>{{ $o->number }} — {{ $o->supplier->name }} ({{ money($o->total) }})</option>@endforeach
            </select>
        </div>
        <div class="field"><label>Date de facturation *</label><input type="date" name="invoiced_at" value="{{ old('invoiced_at', today()->toDateString()) }}" required></div>
        <div class="field"><label>Date d'échéance</label><input type="date" name="due_at" value="{{ old('due_at') }}"></div>
        <div class="field"><label>Montant total (F) *</label><input type="number" min="0" step="1" name="total" value="{{ old('total') }}" required></div>
        @if(auth()->user()->isDirection())
        <div class="field"><label>Magasin payeur</label>
            <select name="store_id"><option value="">—</option>
                @foreach($stores as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
            </select>
        </div>
        @endif
        <div class="field"><label>Pièce justificative (PDF/image)</label><input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png"></div>
    </div>
    <div class="field"><label>Observations</label><textarea name="notes" rows="2">{{ old('notes') }}</textarea></div>

    <div class="actions mt">
        <button class="btn btn-primary">Enregistrer</button>
        <a class="btn" href="{{ route('supplier-invoices.index') }}">Annuler</a>
    </div>
</form>
@endsection
