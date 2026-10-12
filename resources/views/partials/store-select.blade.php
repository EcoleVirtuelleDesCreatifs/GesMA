{{-- Choix du magasin à la création : imposé pour les équipes, au choix pour la direction. --}}
@php $user = auth()->user(); @endphp
@if ($user->isDirection())
    <x-field name="store_id" label="Magasin" type="select" :options="$stores->pluck('name', 'id')" :value="$value ?? null" placeholder="— Choisir —" required />
@else
    <input type="hidden" name="store_id" value="{{ $user->store_id }}">
    <div class="field"><label>Magasin</label><div>{{ $user->store?->name }}</div></div>
@endif
