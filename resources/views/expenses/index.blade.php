@extends('layouts.app')
@section('title', 'Dépenses')
@section('content')
<div class="page-header">
    <div><h1>Dépenses</h1><p class="subtitle">{{ $period->label() }}</p></div>
    <div class="actions">
        @can('reports.view')<a class="btn btn-secondary" href="{{ route('reports.pdf', ['type' => 'depenses'] + $period->query() + array_filter(['store_id' => $storeId])) }}"><x-icon name="download" /> PDF</a>@endcan
        @can('expenses.manage')<a class="btn" href="{{ route('expenses.create') }}"><x-icon name="plus" /> Nouvelle dépense</a>@endcan
    </div>
</div>
@component('partials.period-filter', ['period' => $period, 'stores' => $stores, 'storeId' => $storeId])
    <div class="field"><label for="category_id">Catégorie</label><select name="category_id" id="category_id"><option value="">Toutes</option>@foreach ($categories as $c)<option value="{{ $c->id }}" @selected(request('category_id') == $c->id)>{{ $c->name }}</option>@endforeach</select></div>
@endcomponent

<div class="stats">
    <div class="stat warning"><div class="label">Total des dépenses</div><div class="value">{{ money($total) }}</div></div>
    @if ($revenue !== null)
        <div class="stat"><div class="label">Ventes de la période</div><div class="value">{{ money($revenue) }}</div></div>
        <div class="stat {{ $revenue - $total >= 0 ? 'success' : 'danger' }}"><div class="label">Ventes − dépenses</div><div class="value">{{ money($revenue - $total) }}</div></div>
        <div class="stat info"><div class="label">Part des dépenses</div><div class="value">{{ $revenue ? round(100 * $total / $revenue, 1) : 0 }} %</div></div>
    @endif
</div>

<div class="grid grid-2-1">
    <div class="card flush">
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Date</th><th>Description</th><th>Catégorie</th><th class="hide-mobile">Magasin</th><th class="num">Montant</th><th></th></tr></thead>
            <tbody>
            @forelse ($expenses as $e)
                <tr>
                    <td class="nowrap">{{ $e->spent_at->format('d/m/Y') }}</td>
                    <td>{{ $e->description }}<div class="muted small">{{ $e->user?->name }}@if($e->source === 'offline') · saisie hors connexion @endif</div></td>
                    <td>{{ $e->category->name }}</td>
                    <td class="hide-mobile">{{ $e->store->name }}</td>
                    <td class="num">{{ money($e->amount) }}</td>
                    <td class="text-right nowrap">
                        @if (auth()->user()->hasPermission('expenses.view') || $e->user_id === auth()->id())
                            <a class="btn btn-ghost btn-sm" href="{{ route('expenses.edit', $e) }}"><x-icon name="edit" /></a>
                            <form class="inline-form" method="post" action="{{ route('expenses.destroy', $e) }}" data-confirm="Supprimer cette dépense ?">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm" type="submit"><x-icon name="trash" /></button></form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">Aucune dépense sur la période.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        {{ $expenses->links() }}
    </div>
    <div>
        @if ($byCategory->count())
            <div class="card">
                <h2>Par catégorie</h2>
                @php $max = max(1, $byCategory->max('amount')); @endphp
                <ul class="bar-list">
                    @foreach ($byCategory as $c)
                        <li><div class="bar-label"><span>{{ $c->category }}</span><strong>{{ money($c->amount) }}</strong></div><div class="bar"><span style="width:{{ round(100 * $c->amount / $max) }}%;background:var(--accent)"></span></div></li>
                    @endforeach
                </ul>
            </div>
        @endif
        @if ($byStore->count())
            <div class="card">
                <h2>Par magasin</h2>
                <div class="table-wrap"><table class="table">
                    <thead><tr><th>Magasin</th><th class="num">Dépenses</th><th class="num">Ventes</th></tr></thead>
                    <tbody>@foreach ($byStore as $r)<tr><td>{{ $r->store->name }}</td><td class="num">{{ money($r->expenses) }}</td><td class="num">{{ money($r->revenue) }}</td></tr>@endforeach</tbody>
                </table></div>
            </div>
        @endif
    </div>
</div>
@endsection
