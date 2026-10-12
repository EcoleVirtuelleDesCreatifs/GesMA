@extends('layouts.app')
@section('title', 'Rapports')
@section('content')
<div class="page-header"><div><h1>Rapports</h1><p class="subtitle">Rapports PDF aux couleurs de Mobilier Addict et exports tableur (CSV)</p></div></div>
@php
    $reports = [
        'ventes' => ['Rapport des ventes', "Chiffre d'affaires, résultats par magasin et par ville, produits les plus vendus, meilleurs clients, annulations et retours.", 'chart'],
        'commandes' => ['Rapport des commandes', 'Liste détaillée des commandes de la période avec leur statut, origine et montants.', 'cart'],
        'stocks' => ['État des stocks', 'Quantités par produit et par magasin, valeur du stock et alertes de stock faible.', 'layers'],
        'depenses' => ['Rapport des dépenses', 'Dépenses par catégorie et par magasin, comparées aux ventes.', 'wallet'],
    ];
@endphp
<div class="card">
    <h2>Paramètres</h2>
    @include('partials.period-filter')
</div>
<div class="grid grid-2">
    @foreach ($reports as $type => [$title, $desc, $icon])
        <div class="card">
            <h2 style="display:flex;gap:.5rem;align-items:center"><x-icon :name="$icon" /> {{ $title }}</h2>
            <p class="muted">{{ $desc }}</p>
            <div style="display:flex;gap:.5rem;flex-wrap:wrap">
                <a class="btn" data-report-link href="{{ route('reports.pdf', $type) }}"><x-icon name="download" /> PDF</a>
                <a class="btn btn-secondary" data-report-link href="{{ route('reports.pdf', $type) }}?inline=1" target="_blank">Aperçu</a>
                <a class="btn btn-secondary" data-report-link href="{{ route('reports.csv', $type) }}">Excel (CSV)</a>
            </div>
        </div>
    @endforeach
</div>
@push('scripts')
<script>
    // Les liens de rapport reprennent les filtres choisis.
    document.querySelectorAll('[data-report-link]').forEach(function (a) {
        a.addEventListener('click', function () {
            var form = document.querySelector('[data-period-filter]');
            var params = new URLSearchParams(new FormData(form));
            var url = new URL(a.href, location.href);
            params.forEach(function (v, k) { if (v) url.searchParams.set(k, v); });
            a.href = url.toString();
        });
    });
</script>
@endpush
@endsection
