<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    @include('pdf._style')
</head>
<body>
@php($company = config('gesma.company'))
<div class="footer">
    {{ $company['slogan'] }}<br>
    {{ $company['products_line'] }}<br>
    Tél. {{ $company['phone'] }} — {{ $company['website'] }}<br>
    {{ $company['name'] }} — {{ $title }} — généré le {{ now()->format('d/m/Y à H:i') }} par {{ $generatedBy->name }} (GesMA)
</div>

<div class="header">
    <table><tr>
        <td class="logo-cell">@include('pdf._logo')</td>
        <td>
            <div class="muted" style="font-size:9px">GesMA — rapport de gestion</div>
        </td>
        <td class="right">
            <h1>{{ $title }}</h1>
            <div class="muted">{{ $store?->name ?? ($generatedBy->isDirection() ? 'Tous les magasins' : $generatedBy->store?->name) }}</div>
            @if($type !== 'stocks')<div class="muted">Période : {{ $period->label() }}</div>@else<div class="muted">Situation au {{ now()->format('d/m/Y H:i') }}</div>@endif
        </td>
    </tr></table>
</div>

@if($type === 'ventes')
    <table class="kpis"><tr>
        <td><div class="muted">Chiffre d’affaires</div><div class="v">{{ money($stats['revenue']) }}</div><div class="muted">{{ $stats['sales_count'] }} vente(s)</div></td>
        <td><div class="muted">Encaissé</div><div class="v">{{ money($stats['collected']) }}</div></td>
        <td><div class="muted">Dépenses</div><div class="v">{{ money($stats['expenses']) }}</div></td>
        <td><div class="muted">Résultat</div><div class="v">{{ money($stats['result']) }}</div></td>
    </tr></table>

    <h2>Commandes par statut</h2>
    <table class="items">
        <thead><tr><th>Statut</th><th class="right">Nombre</th><th class="right">Montant</th></tr></thead>
        <tbody>
        @foreach($stats['by_status'] as $s)
            <tr><td>{{ $s['label'] }}</td><td class="right">{{ $s['count'] }}</td><td class="right">{{ money($s['amount']) }}</td></tr>
        @endforeach
        </tbody>
        <tfoot><tr><td>Total commandes</td><td class="right">{{ $stats['orders_count'] }}</td><td></td></tr></tfoot>
    </table>
    <p class="muted">Les commandes annulées et retournées ne sont pas comptées dans le chiffre d’affaires.</p>

    <h2>Résultats par magasin</h2>
    <table class="items">
        <thead><tr><th>Magasin</th><th>Ville</th><th class="right">Ventes</th><th class="right">CA</th><th class="right">Dépenses</th><th class="right">Résultat</th><th class="right">Annulées</th><th class="right">Retournées</th></tr></thead>
        <tbody>
        @foreach($stats['by_store'] as $r)
            <tr><td>{{ $r->store->name }}</td><td>{{ $r->city }}</td><td class="right">{{ $r->sales_count }}</td><td class="right">{{ money($r->revenue) }}</td><td class="right">{{ money($r->expenses) }}</td><td class="right">{{ money($r->result) }}</td><td class="right">{{ $r->cancelled }}</td><td class="right">{{ $r->returned }}</td></tr>
        @endforeach
        </tbody>
        <tfoot><tr><td colspan="2">Total</td><td class="right">{{ $stats['by_store']->sum('sales_count') }}</td><td class="right">{{ money($stats['by_store']->sum('revenue')) }}</td><td class="right">{{ money($stats['by_store']->sum('expenses')) }}</td><td class="right">{{ money($stats['by_store']->sum('result')) }}</td><td class="right">{{ $stats['by_store']->sum('cancelled') }}</td><td class="right">{{ $stats['by_store']->sum('returned') }}</td></tr></tfoot>
    </table>

    @if($stats['by_city']->count() > 1)
        <h2>Résultats par ville</h2>
        <table class="items">
            <thead><tr><th>Ville</th><th class="right">Ventes</th><th class="right">CA</th><th class="right">Dépenses</th><th class="right">Résultat</th></tr></thead>
            <tbody>@foreach($stats['by_city'] as $r)<tr><td>{{ $r->city }}</td><td class="right">{{ $r->sales_count }}</td><td class="right">{{ money($r->revenue) }}</td><td class="right">{{ money($r->expenses) }}</td><td class="right">{{ money($r->result) }}</td></tr>@endforeach</tbody>
        </table>
    @endif

    <h2>Produits les plus vendus</h2>
    <table class="items">
        <thead><tr><th>Produit</th><th>Réf.</th><th class="right">Quantité</th><th class="right">Montant</th></tr></thead>
        <tbody>
        @forelse($stats['top_products'] as $p)
            <tr><td>{{ $p->product_name }}</td><td>{{ $p->product_reference }}</td><td class="right">{{ $p->quantity }}</td><td class="right">{{ money($p->amount) }}</td></tr>
        @empty <tr><td colspan="4" class="center muted">Aucune vente.</td></tr> @endforelse
        </tbody>
    </table>

    <h2>Meilleurs clients</h2>
    <table class="items">
        <thead><tr><th>Client</th><th>Téléphone</th><th class="right">Commandes</th><th class="right">Montant</th></tr></thead>
        <tbody>
        @forelse($stats['top_customers'] as $c)
            <tr><td>{{ $c->customer->name }}</td><td>{{ $c->customer->phone }}</td><td class="right">{{ $c->orders_count }}</td><td class="right">{{ money($c->amount) }}</td></tr>
        @empty <tr><td colspan="4" class="center muted">Aucun client.</td></tr> @endforelse
        </tbody>
    </table>
@endif

@if($type === 'commandes')
    <table class="items">
        <thead><tr><th>N°</th><th>Date</th><th>Client</th><th>Magasin</th><th>Origine</th><th>Statut</th><th class="right">Total</th><th class="right">Encaissé</th></tr></thead>
        <tbody>
        @forelse($orders as $o)
            <tr>
                <td>{{ $o->number }}</td><td>{{ $o->ordered_at->format('d/m/Y H:i') }}</td><td>{{ $o->customer?->name ?? 'Comptoir' }}</td>
                <td>{{ $o->store->city }}</td><td>{{ $o->sourceLabel() }}</td><td><span class="badge">{{ $o->statusLabel() }}</span></td>
                <td class="right">{{ money($o->total) }}</td><td class="right">{{ money($o->amount_paid) }}</td>
            </tr>
        @empty <tr><td colspan="8" class="center muted">Aucune commande sur la période.</td></tr> @endforelse
        </tbody>
        @php($counted = $orders->whereNotIn('status', \App\Models\Order::EXCLUDED_FROM_SALES))
        <tfoot>
            <tr><td colspan="6">Ventes ({{ $counted->count() }} commandes, hors annulations et retours)</td><td class="right">{{ money($counted->sum('total')) }}</td><td class="right">{{ money($counted->sum('amount_paid')) }}</td></tr>
        </tfoot>
    </table>
    <p class="muted">
        Annulées : {{ $orders->where('status', 'annulee')->count() }} ({{ money($orders->where('status', 'annulee')->sum('total')) }}) —
        Retournées : {{ $orders->where('status', 'retournee')->count() }} ({{ money($orders->where('status', 'retournee')->sum('total')) }})
    </p>
@endif

@if($type === 'stocks')
    <table class="items">
        <thead><tr><th>Réf.</th><th>Produit</th><th>Catégorie</th><th>Magasin</th><th class="right">Quantité</th><th class="right">Seuil</th><th class="right">Prix</th><th class="right">Valeur</th><th>Alerte</th></tr></thead>
        <tbody>
        @foreach($stocks as $s)
            <tr>
                <td>{{ $s->product->reference }}</td><td>{{ $s->product->name }}</td><td>{{ $s->product->category?->name }}</td><td>{{ $s->store->name }}</td>
                <td class="right">{{ $s->quantity }}</td><td class="right">{{ $s->alert_threshold }}</td>
                <td class="right">{{ money($s->product->price) }}</td><td class="right">{{ money(max(0, $s->quantity) * $s->product->price) }}</td>
                <td>{{ $s->quantity <= 0 ? 'Rupture' : ($s->isLow() ? 'Faible' : '') }}</td>
            </tr>
        @endforeach
        </tbody>
        <tfoot><tr><td colspan="4">Total</td><td class="right">{{ $stocks->sum('quantity') }}</td><td></td><td></td><td class="right">{{ money($stocks->sum(fn ($s) => max(0, $s->quantity) * $s->product->price)) }}</td><td>{{ $stocks->filter->isLow()->count() }} alerte(s)</td></tr></tfoot>
    </table>
@endif

@if($type === 'depenses')
    <table class="kpis"><tr>
        <td><div class="muted">Total des dépenses</div><div class="v">{{ money($expenses->sum('amount')) }}</div></td>
        <td><div class="muted">Ventes de la période</div><div class="v">{{ money($revenue) }}</div></td>
        <td><div class="muted">Résultat</div><div class="v">{{ money($revenue - $expenses->sum('amount')) }}</div></td>
        <td><div class="muted">Nombre</div><div class="v">{{ $expenses->count() }}</div></td>
    </tr></table>
    <h2>Par catégorie</h2>
    <table class="items">
        <thead><tr><th>Catégorie</th><th class="right">Nombre</th><th class="right">Montant</th></tr></thead>
        <tbody>@foreach($byCategory as $c)<tr><td>{{ $c->label }}</td><td class="right">{{ $c->n }}</td><td class="right">{{ money($c->total) }}</td></tr>@endforeach</tbody>
    </table>
    <h2>Détail</h2>
    <table class="items">
        <thead><tr><th>Date</th><th>Magasin</th><th>Catégorie</th><th>Description</th><th class="right">Montant</th></tr></thead>
        <tbody>
        @forelse($expenses as $e)
            <tr><td>{{ $e->spent_at->format('d/m/Y') }}</td><td>{{ $e->store->name }}</td><td>{{ $e->categoryLabel() }}</td><td>{{ $e->description }}</td><td class="right">{{ money($e->amount) }}</td></tr>
        @empty <tr><td colspan="5" class="center muted">Aucune dépense.</td></tr> @endforelse
        </tbody>
        <tfoot><tr><td colspan="4">Total</td><td class="right">{{ money($expenses->sum('amount')) }}</td></tr></tfoot>
    </table>
@endif
</body>
</html>
