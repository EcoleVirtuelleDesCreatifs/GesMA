{{-- Filtres communs : période, magasin, ville. --}}
@php $user = auth()->user(); @endphp
<form method="get" class="filters" data-period-filter>
    <div class="field">
        <label for="pf_period">Période</label>
        <select name="period" id="pf_period" data-period-select>
            @foreach (\App\Support\Period::OPTIONS as $key => $label)
                <option value="{{ $key }}" @selected($period->key === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="field" data-custom-dates @if($period->key !== 'custom') hidden @endif>
        <label for="pf_from">Du</label>
        <input type="date" name="from" id="pf_from" value="{{ $period->from->toDateString() }}">
    </div>
    <div class="field" data-custom-dates @if($period->key !== 'custom') hidden @endif>
        <label for="pf_to">Au</label>
        <input type="date" name="to" id="pf_to" value="{{ $period->to->toDateString() }}">
    </div>
    @if ($user->isDirection())
        @isset($cities)
            @if ($cities->count() > 1)
                <div class="field">
                    <label for="pf_city">Ville</label>
                    <select name="city" id="pf_city">
                        <option value="">Toutes les villes</option>
                        @foreach ($cities as $c)<option value="{{ $c }}" @selected(($city ?? null) === $c)>{{ $c }}</option>@endforeach
                    </select>
                </div>
            @endif
        @endisset
        <div class="field">
            <label for="pf_store">Magasin</label>
            <select name="store_id" id="pf_store">
                <option value="">Tous les magasins</option>
                @foreach ($stores as $s)<option value="{{ $s->id }}" @selected(($storeId ?? null) == $s->id)>{{ $s->name }}</option>@endforeach
            </select>
        </div>
    @endif
    {{ $slot ?? '' }}
    <button class="btn btn-secondary" type="submit">Filtrer</button>
</form>
