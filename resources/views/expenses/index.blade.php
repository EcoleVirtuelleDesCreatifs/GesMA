@extends('layouts.app')
@section('title', 'Dépenses')
@section('content')
<div class="page-head">
    <h1>Dépenses</h1>
    <div class="actions">
        @can('expenses.manage')<a class="btn btn-primary" href="{{ route('expenses.create') }}">+ Nouvelle dépense</a>@endcan
        @can('reports.view')<a class="btn" href="{{ route('reports.pdf', ['type' => 'depenses', 'store_id' => $storeId] + $period->query()) }}">PDF</a>@endcan
    </div>
</div>

<form method="GET" class="filters card">
    @include('partials.period-filter')
    <div class="field">
        <label for="category">Catégorie</label>
        <select name="category" id="category">
            <option value="">Toutes</option>
            @foreach(config('gesma.expense_categories') as $k => $l)<option value="{{ $k }}" @selected(request('category') === $k)>{{ $l }}</option>@endforeach
        </select>
    </div>
    @include('partials.store-filter')
    <button class="btn btn-sm">Filtrer</button>
</form>

<div class="grid grid-main">
    <div class="card">
        <div class="card-head"><small class="muted">{{ $period->label() }}</small><strong>Total : @money($total)</strong></div>
        <div class="table-wrap">
            <table class="table responsive">
                <thead><tr><th>Date</th><th>Catégorie</th><th>Description</th>@if(auth()->user()->isDirection())<th>Magasin</th>@endif<th class="right">Montant</th><th></th></tr></thead>
                <tbody>
                @forelse($expenses as $e)
                    <tr>
                        <td data-label="Date" class="nowrap">{{ $e->spent_at->format('d/m/Y') }}</td>
                        <td data-label="Catégorie"><span class="badge badge-primary">{{ $e->categoryLabel() }}</span></td>
                        <td data-label="Description">{{ $e->description ?? '—' }}@if($e->client_uuid)<br><small class="muted">saisie hors connexion</small>@endif</td>
                        @if(auth()->user()->isDirection())<td data-label="Magasin">{{ $e->store->city }}</td>@endif
                        <td data-label="Montant" class="right num"><strong>@money($e->amount)</strong></td>
                        <td data-label="" class="actions">@can('expenses.manage')<a class="btn btn-sm" href="{{ route('expenses.edit', $e) }}">Modifier</a>@endcan</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">Aucune dépense sur la période.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $expenses->links() }}
    </div>
    <div>
        <div class="card">
            <h2>Par catégorie</h2>
            @php($maxCat = max(1, $byCategory->max('total')))
            <ul class="list">
                @forelse($byCategory as $c)
                    <li style="display:block">
                        <div style="display:flex;justify-content:space-between"><span>{{ config("gesma.expense_categories.{$c->category}", $c->category) }}</span><strong class="num">@money($c->total)</strong></div>
                        <div class="hbar"><span style="width: {{ round($c->total / $maxCat * 100) }}%"></span></div>
                    </li>
                @empty <li class="muted">—</li> @endforelse
            </ul>
        </div>
        @if(auth()->user()->isDirection() && $byStore->count() > 1)
            <div class="card">
                <h2>Par magasin</h2>
                <ul class="list">
                    @foreach($byStore as $s)<li><span>{{ $s->store?->name }}</span><strong class="num">@money($s->total)</strong></li>@endforeach
                </ul>
            </div>
        @endif
    </div>
</div>
@endsection
