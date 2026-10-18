@extends('layouts.app')
@section('title', 'Rapports')
@section('content')
<div class="page-head"><h1>Rapports PDF</h1></div>
<div class="grid grid-2">
    @foreach($types as $key => $label)
        <div class="card">
            <h2>{{ $label }}</h2>
            <p class="muted" style="font-size:.88rem">
                {{ [
                    'ventes' => 'Chiffre d’affaires, résultats par magasin et par ville, produits et clients les plus importants.',
                    'commandes' => 'Liste détaillée des commandes de la période, avec statuts et encaissements.',
                    'stocks' => 'Quantités par produit et par magasin, valeur du stock et alertes.',
                    'depenses' => 'Dépenses par catégorie et détail, comparées aux ventes.',
                ][$key] }}
            </p>
            <form method="GET" action="{{ route('reports.pdf') }}" class="filters" style="margin:0">
                <input type="hidden" name="type" value="{{ $key }}">
                @if($key !== 'stocks')
                    @include('partials.period-filter', ['period' => null, 'default' => 'month'])
                @endif
                @if($key === 'commandes')
                    <div class="field">
                        <label>Statut</label>
                        <select name="status"><option value="">Tous</option>@foreach(config('gesma.order_statuses') as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
                    </div>
                @endif
                @include('partials.store-filter', ['storeId' => null, 'autosubmit' => false])
                <button class="btn btn-primary btn-sm">Télécharger</button>
            </form>
        </div>
    @endforeach
</div>
@endsection
