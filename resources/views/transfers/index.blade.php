@extends('layouts.app')
@section('title', 'Transferts de stock')
@section('content')
<div class="page-header">
    <div><h1>Transferts entre magasins</h1></div>
    <div class="actions">@can('stock.transfer')<a class="btn" href="{{ route('transfers.create') }}"><x-icon name="plus" /> Nouveau transfert</a>@endcan</div>
</div>
<div class="card flush">
    <div class="table-wrap"><table class="table">
        <thead><tr><th>N°</th><th>Date</th><th>De</th><th>Vers</th><th class="num">Articles</th><th class="hide-mobile">Par</th></tr></thead>
        <tbody>
        @forelse ($transfers as $t)
            <tr>
                <td><a class="mono" href="{{ route('transfers.show', $t) }}">{{ $t->number }}</a></td>
                <td>{{ $t->transferred_at->format('d/m/Y H:i') }}</td>
                <td>{{ $t->fromStore->name }}</td>
                <td>{{ $t->toStore->name }}</td>
                <td class="num">{{ $t->items_sum_quantity }}</td>
                <td class="hide-mobile">{{ $t->user?->name }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="empty">Aucun transfert.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
{{ $transfers->links() }}
@endsection
