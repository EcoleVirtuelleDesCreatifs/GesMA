@extends('layouts.app')
@section('title', $supplier->exists ? 'Modifier '.$supplier->name : 'Nouveau fournisseur')
@section('content')
<div class="page-head"><h1>{{ $supplier->exists ? 'Modifier « '.$supplier->name.' »' : 'Nouveau fournisseur' }}</h1></div>

<div class="card" style="max-width: 860px">
    <form method="POST" action="{{ $supplier->exists ? route('suppliers.update', $supplier) : route('suppliers.store') }}">
        @csrf
        @if($supplier->exists) @method('PUT') @endif
        <div class="form-grid">
            <div class="field">
                <label for="name">Nom commercial / raison sociale *</label>
                <input type="text" name="name" id="name" value="{{ old('name', $supplier->name) }}" required>
            </div>
            <div class="field">
                <label for="type">Type de fournisseur</label>
                <select name="type" id="type"><option value="">—</option>
                    @foreach(config('gesma.supplier_types') as $k => $l)
                        <option value="{{ $k }}" @selected(old('type', $supplier->type) === $k)>{{ $l }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="contact_name">Responsable / contact</label>
                <input type="text" name="contact_name" id="contact_name" value="{{ old('contact_name', $supplier->contact_name) }}">
            </div>
            <div class="field">
                <label for="phone">Téléphone</label>
                <input type="tel" name="phone" id="phone" value="{{ old('phone', $supplier->phone) }}">
            </div>
            <div class="field">
                <label for="whatsapp">WhatsApp</label>
                <input type="tel" name="whatsapp" id="whatsapp" value="{{ old('whatsapp', $supplier->whatsapp) }}">
            </div>
            <div class="field">
                <label for="email">E-mail</label>
                <input type="email" name="email" id="email" value="{{ old('email', $supplier->email) }}">
            </div>
            <div class="field">
                <label for="address">Adresse</label>
                <input type="text" name="address" id="address" value="{{ old('address', $supplier->address) }}">
            </div>
            <div class="field">
                <label for="city">Ville</label>
                <input type="text" name="city" id="city" value="{{ old('city', $supplier->city) }}">
            </div>
            <div class="field">
                <label for="country">Pays</label>
                <input type="text" name="country" id="country" value="{{ old('country', $supplier->country) }}">
            </div>
            <div class="field">
                <label for="tax_id">Identifiant fiscal (N° CC)</label>
                <input type="text" name="tax_id" id="tax_id" value="{{ old('tax_id', $supplier->tax_id) }}">
            </div>
            <div class="field">
                <label for="payment_terms">Conditions de règlement</label>
                <input type="text" name="payment_terms" id="payment_terms" value="{{ old('payment_terms', $supplier->payment_terms) }}" placeholder="Ex : 30 jours, acompte 50 %">
            </div>
            <div class="field">
                <label for="lead_time_days">Délai de livraison (jours)</label>
                <input type="number" name="lead_time_days" id="lead_time_days" min="0" value="{{ old('lead_time_days', $supplier->lead_time_days) }}">
            </div>
            <div class="field">
                <label for="is_active">Statut</label>
                <select name="is_active" id="is_active">
                    <option value="1" @selected(old('is_active', $supplier->is_active ?? true))>Actif</option>
                    <option value="0" @selected(old('is_active') === '0' || ($supplier->exists && ! $supplier->is_active))>Inactif</option>
                </select>
            </div>
            <div class="field full">
                <label for="notes">Notes internes</label>
                <textarea name="notes" id="notes" rows="3">{{ old('notes', $supplier->notes) }}</textarea>
            </div>
        </div>
        <div class="form-actions">
            <a class="btn" href="{{ $supplier->exists ? route('suppliers.show', $supplier) : route('suppliers.index') }}">Annuler</a>
            <button class="btn btn-primary" type="submit">{{ $supplier->exists ? 'Enregistrer' : 'Créer le fournisseur' }}</button>
        </div>
    </form>
</div>
@endsection
