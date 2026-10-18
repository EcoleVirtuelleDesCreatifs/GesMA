@php($period = $period ?? null)
@php($current = $period?->key ?? request('period', $default ?? ''))
<div class="field">
    <label>Période</label>
    <select name="period" data-period-select>
        @if(!empty($allowAll))<option value="">Toutes les dates</option>@endif
        @foreach(\App\Support\Period::OPTIONS as $key => $label)
            <option value="{{ $key }}" @selected($current === $key)>{{ $label }}</option>
        @endforeach
    </select>
</div>
<div class="field {{ $current === 'custom' ? '' : 'hidden' }}" data-custom-period>
    <label>Du</label>
    <input type="date" name="from" value="{{ request('from', $period?->from->toDateString() ?? now()->startOfMonth()->toDateString()) }}">
</div>
<div class="field {{ $current === 'custom' ? '' : 'hidden' }}" data-custom-period>
    <label>Au</label>
    <input type="date" name="to" value="{{ request('to', $period?->to->toDateString() ?? now()->toDateString()) }}">
</div>
