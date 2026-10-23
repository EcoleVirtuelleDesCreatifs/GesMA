@extends('layouts.app')
@section('title', $supplier->name)
@section('content')
<div class="page-head">
    <h1>{{ $supplier->name }} <small class="muted">{{ $supplier->code }}</small></h1>
    <div class="actions">
        @can('suppliers.manage')
            <a class="btn" href="{{ route('suppliers.edit', $supplier) }}">Modifier</a>
            @if($supplier->is_active)
                <form method="POST" action="{{ route('suppliers.destroy', $supplier) }}" class="inline-form" onsubmit="return confirm('Archiver {{ $supplier->name }} ? L’historique est conservé.')">@csrf @method('DELETE')<button class="btn btn-danger">Archiver</button></form>
            @else
                <form method="POST" action="{{ route('suppliers.restore', $supplier) }}" class="inline-form">@csrf @method('PUT')<button class="btn btn-success">Réactiver</button></form>
            @endif
        @endcan
        @can('purchases.manage')<a class="btn btn-primary" href="{{ route('purchases.create', ['supplier_id' => $supplier->id]) }}">+ Commande d'achat</a>@endcan
    </div>
</div>

<div class="grid grid-4 mb">
    <div class="kpi brand"><div class="label">Dette en cours</div><div class="value">@money($debt)</div></div>
    <div class="kpi"><div class="label">Commandes</div><div class="value">{{ $supplier->purchaseOrders()->count() }}</div></div>
    <div class="kpi"><div class="label">Factures</div><div class="value">{{ $supplier->invoices()->count() }}</div></div>
    <div class="kpi"><div class="label">Produits associés</div><div class="value">{{ $supplier->products->count() }}</div></div>
</div>

<div class="grid grid-2 mb">
    <div class="card">
        <div class="card-head"><h2>Informations</h2><span class="badge {{ $supplier->is_active ? 'badge-success' : 'badge-muted' }}">{{ $supplier->is_active ? 'Actif' : 'Archivé' }}</span></div>
        <ul class="list">
            <li><span class="muted">Type</span><span>{{ $supplier->typeLabel() }}</span></li>
            <li><span class="muted">Contact</span><span>{{ $supplier->contact_name ?? '—' }}</span></li>
            <li><span class="muted">Téléphone</span><span>{{ $supplier->phone ?? '—' }}</span></li>
            <li><span class="muted">WhatsApp</span><span>{{ $supplier->whatsapp ?? '—' }}</span></li>
            <li><span class="muted">E-mail</span><span>{{ $supplier->email ?? '—' }}</span></li>
            <li><span class="muted">Adresse</span><span>{{ collect([$supplier->address, $supplier->city, $supplier->country])->filter()->implode(', ') ?: '—' }}</span></li>
            <li><span class="muted">N° fiscal</span><span>{{ $supplier->tax_id ?? '—' }}</span></li>
            <li><span class="muted">Conditions de règlement</span><span>{{ $supplier->payment_terms ?? '—' }}</span></li>
            <li><span class="muted">Délai de livraison</span><span>{{ $supplier->lead_time_days !== null ? $supplier->lead_time_days.' jours' : '—' }}</span></li>
        </ul>
        @if($supplier->notes)<p class="muted">{{ $supplier->notes }}</p>@endif
    </div>

    <div class="card">
        <div class="card-head"><h2>Produits associés</h2></div>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Produit</th><th class="right">Prix d'achat</th><th class="right">Qté min.</th><th class="right">Délai</th><th>Dernier achat</th><th></th></tr></thead>
            <tbody>
            @forelse($supplier->products as $p)
                <tr>
                    <td>{{ $p->name }}<br><small class="muted">{{ $p->reference }}</small></td>
                    <td class="right num">@money($p->pivot->purchase_price)</td>
                    <td class="right">{{ $p->pivot->min_quantity }}</td>
                    <td class="right">{{ $p->pivot->lead_time_days !== null ? $p->pivot->lead_time_days.' j' : '—' }}</td>
                    <td>{{ $p->pivot->last_purchased_at ? \Illuminate\Support\Carbon::parse($p->pivot->last_purchased_at)->format('d/m/Y') : '—' }}</td>
                    <td class="actions">
                        @can('suppliers.manage')
                            <form method="POST" action="{{ route('suppliers.products.detach', [$supplier, $p]) }}" class="inline-form" onsubmit="return confirm('Retirer ce produit du fournisseur ?')">@csrf @method('DELETE')<button class="btn btn-sm btn-danger">Retirer</button></form>
                        @endcan
                    </td>
                </tr>
                @can('suppliers.manage')
                <tr>
                    <td colspan="6">
                        <form method="POST" action="{{ route('suppliers.products.update', [$supplier, $p]) }}" class="filters" style="margin:0;padding:.3rem 0">
                            @csrf @method('PUT')
                            <input type="number" step="1" name="purchase_price" value="{{ (float) $p->pivot->purchase_price }}" style="width:110px" title="Prix d'achat">
                            <input type="number" name="min_quantity" value="{{ $p->pivot->min_quantity }}" style="width:70px" title="Qté min.">
                            <input type="number" name="lead_time_days" value="{{ $p->pivot->lead_time_days }}" style="width:70px" title="Délai (j)" placeholder="Délai">
                            <input type="hidden" name="is_active" value="0"><label class="muted" style="font-size:.8rem"><input type="checkbox" name="is_active" value="1" @checked($p->pivot->is_active)> actif</label>
                            <button class="btn btn-sm">Mettre à jour</button>
                        </form>
                    </td>
                </tr>
                @endcan
            @empty
                <tr><td colspan="6" class="empty">Aucun produit associé.</td></tr>
            @endforelse
            </tbody>
        </table></div>

        @can('suppliers.manage')
        <form method="POST" action="{{ route('suppliers.products.attach', $supplier) }}" class="filters" style="margin-top:.6rem">
            @csrf
            <div class="field"><select name="product_id" required>
                <option value="">+ Associer un produit…</option>
                @foreach($allProducts as $p)
                    @unless($supplier->products->contains($p->id))<option value="{{ $p->id }}">{{ $p->reference }} — {{ $p->name }}</option>@endunless
                @endforeach
            </select></div>
            <div class="field"><input type="number" step="1" name="purchase_price" placeholder="Prix d'achat" required style="width:120px"></div>
            <div class="field"><input type="number" name="min_quantity" placeholder="Qté min." value="1" style="width:80px"></div>
            <button class="btn btn-sm btn-primary">Associer</button>
        </form>
        @endcan
    </div>
</div>

<div class="grid grid-2 mb">
    <div class="card">
        <div class="card-head"><h2>Commandes d'achat</h2></div>
        <ul class="list">
            @forelse($orders as $o)
                <li><a href="{{ route('purchases.show', $o) }}">{{ $o->number }} <small class="muted">{{ $o->ordered_at?->format('d/m/Y') }}</small></a><span><span class="badge badge-info">{{ $o->statusLabel() }}</span> <strong class="num">@money($o->total)</strong></span></li>
            @empty <p class="empty">Aucune commande.</p> @endforelse
        </ul>
    </div>
    <div class="card">
        <div class="card-head"><h2>Réceptions</h2></div>
        <ul class="list">
            @forelse($receipts as $r)
                <li><a href="{{ route('receipts.show', $r) }}">{{ $r->number }} <small class="muted">{{ $r->created_at->format('d/m/Y') }} · {{ $r->store->name }}</small></a><span class="badge badge-success">Validée</span></li>
            @empty <p class="empty">Aucune réception.</p> @endforelse
        </ul>
    </div>
</div>

<div class="grid grid-2 mb">
    <div class="card">
        <div class="card-head"><h2>Factures</h2>@can('supplier_invoices.manage')<a class="btn btn-sm" href="{{ route('supplier-invoices.create', ['supplier_id' => $supplier->id]) }}">+ Facture</a>@endcan</div>
        <ul class="list">
            @forelse($invoices as $i)
                <li><a href="{{ route('supplier-invoices.show', $i) }}">{{ $i->number }} <small class="muted">{{ $i->invoiced_at->format('d/m/Y') }}@if($i->due_at) · éch. {{ $i->due_at->format('d/m/Y') }}@endif</small></a><span>@money($i->total) — <span class="badge {{ $i->balance() <= 0 ? 'badge-success' : ($i->isDue() ? 'badge-danger' : 'badge-warning') }}">{{ $i->statusLabel() }}</span></span></li>
            @empty <p class="empty">Aucune facture.</p> @endforelse
        </ul>
    </div>
    <div class="card">
        <div class="card-head"><h2>Paiements</h2></div>
        <ul class="list">
            @forelse($payments as $p)
                <li><span>{{ $p->paid_at->format('d/m/Y') }} — {{ $p->invoice->number }} <small class="muted">{{ $p->methodLabel() }}</small></span><strong class="num">@money($p->amount)</strong></li>
            @empty <p class="empty">Aucun paiement.</p> @endforelse
        </ul>
    </div>
</div>

<div class="grid grid-2">
    <div class="card">
        <div class="card-head"><h2>Retours</h2></div>
        <ul class="list">
            @forelse($returns as $r)
                <li><a href="{{ route('supplier-returns.show', $r) }}">{{ $r->number }} <small class="muted">{{ $r->returned_at->format('d/m/Y') }}</small></a><span class="badge badge-info">{{ $r->statusLabel() }}</span></li>
            @empty <p class="empty">Aucun retour.</p> @endforelse
        </ul>
    </div>
    <div class="card">
        <div class="card-head"><h2>Documents</h2></div>
        <ul class="list">
            @forelse($supplier->documents as $d)
                <li><a href="{{ route('suppliers.documents.download', $d) }}">{{ $d->name }}</a><small class="muted">{{ $d->created_at->format('d/m/Y') }} · {{ $d->user?->name }}</small></li>
            @empty <p class="empty">Aucun document.</p> @endforelse
        </ul>
        @can('suppliers.manage')
        <form method="POST" action="{{ route('suppliers.documents.attach', $supplier) }}" enctype="multipart/form-data" class="filters" style="margin-top:.6rem">
            @csrf
            <div class="field"><input name="name" placeholder="Nom du document" required></div>
            <div class="field"><input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png" required></div>
            <button class="btn btn-sm">Joindre</button>
        </form>
        @endcan
    </div>
</div>
@endsection
