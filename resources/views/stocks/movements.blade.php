@extends('layouts.app')
@section('title', 'Mouvements de stock')
@section('content')
<div class="page-head">
    <h1>Mouvements de stock</h1>
    <div class="actions">@can('stock.move')<a class="btn btn-primary" href="{{ route('stocks.movements.create') }}">+ Nouveau mouvement</a>@endcan</div>
</div>
<form method="GET" class="filters card">
    <div class="field">
        <label for="type">Type</label>
        <select name="type" id="type">
            <option value="">Tous</option>
            @foreach(config('gesma.stock_movement_types') as $k => $l)<option value="{{ $k }}" @selected(request('type') === $k)>{{ $l }}</option>@endforeach
        </select>
    </div>
    <div class="field"><label for="from">Du</label><input type="date" name="from" id="from" value="{{ request('from') }}"></div>
    <div class="field"><label for="to">Au</label><input type="date" name="to" id="to" value="{{ request('to') }}"></div>
    @if(request('product_id'))<input type="hidden" name="product_id" value="{{ request('product_id') }}">@endif
    @include('partials.store-filter')
    <button class="btn btn-sm">Filtrer</button>
</form>
<div class="card">
    @include('stocks._movements-table', ['movements' => $movements])
    {{ $movements->links() }}
</div>
@endsection
