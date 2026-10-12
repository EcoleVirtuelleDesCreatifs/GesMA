@if (auth()->user()->isDirection() && isset($stores) && $stores->count() > 1)
    <div class="field">
        <label for="sf_store">Magasin</label>
        <select name="store_id" id="sf_store">
            <option value="">Tous les magasins</option>
            @foreach ($stores as $s)<option value="{{ $s->id }}" @selected(request('store_id') == $s->id)>{{ $s->name }}</option>@endforeach
        </select>
    </div>
@endif
