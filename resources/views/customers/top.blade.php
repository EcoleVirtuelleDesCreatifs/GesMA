@extends('layouts.app')
@section('title', 'Meilleurs clients')
@section('content')
<div class="page-header">
    <div><h1>Meilleurs clients</h1><p class="subtitle">{{ $period->label() }} — hors commandes annulées et retournées</p></div>
    <div class="actions"><a class="btn btn-secondary" href="{{ route('customers.index') }}">Tous les clients</a></div>
</div>
@include('partials.period-filter')
<div class="grid grid-2">
    @foreach (['Achats les plus importants' => $byAmount, 'Commandent le plus souvent' => $byFrequency] as $title => $rows)
        <div class="card flush">
            <div class="card-header"><h2>{{ $title }}</h2></div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>#</th><th>Client</th><th class="num">Commandes</th><th class="num">Montant</th></tr></thead>
                    <tbody>
                    @forelse ($rows->values() as $i => $r)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td><a href="{{ route('customers.show', $r->customer) }}">{{ $r->customer->name }}</a><div class="muted small">{{ $r->customer->phone }} {{ $r->customer->city }}</div></td>
                            <td class="num">{{ $r->orders_count }}</td>
                            <td class="num">{{ money($r->total_spent) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="empty">Aucune donnée sur la période.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach
</div>
@endsection
