{{-- Filtre magasin : visible seulement pour la Direction --}}
@if(auth()->user()->isDirection() && $stores->count() > 1)
    <div class="field">
        <label>Magasin</label>
        <select name="store_id" @if($autosubmit ?? true) onchange="this.form.submit()" @endif>
            <option value="">Tous les magasins</option>
            @foreach($stores as $s)
                <option value="{{ $s->id }}" @selected((int) ($storeId ?? 0) === $s->id)>{{ $s->name }}</option>
            @endforeach
        </select>
    </div>
@endif
