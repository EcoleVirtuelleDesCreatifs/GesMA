@extends('layouts.app')
@section('title', $supplier->exists ? 'Modifier '.$supplier->name : 'Nouveau fournisseur')
@section('content')
<div class="page-head"><h1>{{ $supplier->exists ? 'Modifier '.$supplier->name : 'Nouveau fournisseur' }}</h1></div>

<form method="POST" action="{{ $supplier->exists ? route('suppliers.update', $supplier) : route('suppliers.store') }}" class="card">
    @csrf @if($supplier->exists)@method('PUT')@endif

    <div class="grid grid-2">
        <div class="field"><label>Nom commercial / raison sociale *</label><input name="name" value="{{ old('name', $supplier->name) }}" required></div>
        <div class="field"><label>Type de fournisseur</label>
            <select name="type"><option value="">—</option>
                @foreach(config('gesma.supplier_types') as $k => $l)<option value="{{ $k }}" @selected(old('type', $supplier->type) === $k)>{{ $l }}</option>@endforeach
            </select>
        </div>
        <div class="field"><label>Responsable / contact</label><input name="contact_name" value="{{ old('contact_name', $supplier->contact_name) }}"></div>
        <div class="field"><label>Téléphone</label><input name="phone" value="{{ old('phone', $supplier->phone) }}"></div>
        <div class="field"><label>WhatsApp</label><input name="whatsapp" value="{{ old('whatsapp', $supplier->whatsapp) }}"></div>
        <div class="field"><label>E-mail</label><input type="email" name="email" value="{{ old('email', $supplier->email) }}"></div>
        <div class="field"><label>Adresse</label><input name="address" value="{{ old('address', $supplier->address) }}"></div>
        <div class="field"><label>Ville</label><input name="city" value="{{ old('city', $supplier->city) }}"></div>
        <div class="field"><label>Pays</label><input name="country" value="{{ old('country', $supplier->country) }}"></div>
        <div class="field"><label>Identifiant fiscal (N° CC)</label><input name="tax_id" value="{{ old('tax_id', $supplier->tax_id) }}"></div>
        <div class="field"><label>Conditions de règlement</label><input name="payment_terms" value="{{ old('payment_terms', $supplier->payment_terms) }}" placeholder="Ex : 30 jours, acompte 50 %"></div>
        <div class="field"><label>Délai de livraison (jours)</label><input type="number" min="0" name="lead_time_days" value="{{ old('lead_time_days', $supplier->lead_time_days) }}"></div>
        <div class="field"><label>Statut</label>
            <select name="is_active">
                <option value="1" @selected(old('is_active', $supplier->is_active))>Actif</option>
                <option value="0" @selected(! old('is_active', $supplier->is_active))>Inactif</option>
            </select>
        </div>
    </div>
    <div class="field"><label>Notes internes</label><textarea name="notes" rows="3">{{ old('notes', $supplier->notes) }}</textarea></div>

    <div class="actions mt">
        <button class="btn btn-primary">{{ $supplier->exists ? 'Enregistrer' : 'Créer le fournisseur' }}</button>
        <a class="btn" href="{{ $supplier->exists ? route('suppliers.show', $supplier) : route('suppliers.index') }}">Annuler</a>
    </div>
</form>
@endsection
