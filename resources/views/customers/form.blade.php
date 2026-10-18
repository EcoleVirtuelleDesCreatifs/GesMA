@extends('layouts.app')
@section('title', $customer->exists ? 'Modifier le client' : 'Nouveau client')
@section('content')
<div class="page-head"><h1>{{ $customer->exists ? $customer->name : 'Nouveau client' }}</h1></div>
<div class="card" style="max-width: 820px">
    <form method="POST" action="{{ $customer->exists ? route('customers.update', $customer) : route('customers.store') }}">
        @csrf
        @if($customer->exists) @method('PUT') @endif
        @if(request('redirect'))<input type="hidden" name="redirect" value="{{ request('redirect') }}">@endif
        <div class="form-grid">
            <div class="field full"><label for="name">Nom complet *</label><input type="text" id="name" name="name" value="{{ old('name', $customer->name) }}" required></div>
            <div class="field"><label for="phone">Téléphone</label><input type="tel" id="phone" name="phone" value="{{ old('phone', $customer->phone) }}"></div>
            <div class="field"><label for="email">E-mail</label><input type="email" id="email" name="email" value="{{ old('email', $customer->email) }}"></div>
            <div class="field"><label for="address">Adresse</label><input type="text" id="address" name="address" value="{{ old('address', $customer->address) }}"></div>
            <div class="field"><label for="city">Ville</label><input type="text" id="city" name="city" value="{{ old('city', $customer->city) }}"></div>
            @if(!$customer->exists && auth()->user()->isDirection())
                <div class="field">
                    <label for="store_id">Magasin de rattachement *</label>
                    <select name="store_id" id="store_id" required>
                        @foreach($stores as $s)<option value="{{ $s->id }}" @selected(old('store_id') == $s->id)>{{ $s->name }}</option>@endforeach
                    </select>
                </div>
            @endif
            <div class="field full"><label for="notes">Notes</label><textarea id="notes" name="notes">{{ old('notes', $customer->notes) }}</textarea></div>
        </div>
        <div class="form-actions">
            <a class="btn" href="{{ $customer->exists ? route('customers.show', $customer) : route('customers.index') }}">Annuler</a>
            <button class="btn btn-primary">Enregistrer</button>
        </div>
    </form>
</div>
@endsection
