@extends('layouts.app')
@section('title', 'Conflits de synchronisation')
@section('content')
<div class="page-head">
    <div>
        <h1>Conflits à vérifier</h1>
        <small class="muted">Écarts constatés lors de la synchronisation des saisies hors connexion ou des commandes du site.</small>
    </div>
    <div class="actions">
        <a class="btn {{ $showResolved ? '' : 'btn-primary' }}" href="{{ route('conflicts.index') }}">À vérifier</a>
        <a class="btn {{ $showResolved ? 'btn-primary' : '' }}" href="{{ route('conflicts.index', ['resolved' => 1]) }}">Déjà vérifiés</a>
    </div>
</div>
@if(auth()->user()->isDirection())
    <form method="GET" class="filters">@if($showResolved)<input type="hidden" name="resolved" value="1">@endif @include('partials.store-filter')</form>
@endif

@forelse($conflicts as $c)
    <div class="card">
        <div class="card-head">
            <div>
                <span class="badge badge-warning">{{ $c->typeLabel() }}</span>
                <small class="muted">{{ $c->created_at->format('d/m/Y H:i') }} · {{ $c->store?->name }}{{ $c->user ? ' · saisi par '.$c->user->name : '' }}</small>
            </div>
            @if($c->subject instanceof \App\Models\Order)
                <a class="btn btn-sm" href="{{ route('orders.show', $c->subject) }}">Voir la commande {{ $c->subject->number }}</a>
            @endif
        </div>
        <p><strong>{{ $c->message }}</strong></p>
        @if(!empty($c->details['ruptures']))
            <div class="table-wrap"><table class="table">
                <thead><tr><th>Produit</th><th class="right">Demandé</th><th class="right">Disponible au moment de la synchro</th></tr></thead>
                <tbody>
                @foreach($c->details['ruptures'] as $r)
                    <tr><td>{{ $r['product'] }} <small class="muted">{{ $r['reference'] ?? '' }}</small></td><td class="right">{{ $r['demande'] }}</td><td class="right">{{ $r['disponible'] }}</td></tr>
                @endforeach
                </tbody>
            </table></div>
            <p class="muted" style="font-size:.85rem">Le stock est devenu négatif : vérifiez le stock physique, faites une entrée, un transfert ou une correction, ou annulez la commande.</p>
        @endif
        @if($c->resolved_at)
            <div class="alert alert-success" style="margin:0">Vérifié le {{ $c->resolved_at->format('d/m/Y H:i') }} par {{ $c->resolver?->name }} : {{ $c->resolution_note }}</div>
        @else
            <form method="POST" action="{{ route('conflicts.resolve', $c) }}" style="display:flex;gap:.5rem;flex-wrap:wrap">
                @csrf @method('PUT')
                <input type="text" name="resolution_note" placeholder="Vérification effectuée (ex. : stock corrigé après inventaire)" style="flex:1;min-width:220px" required>
                <button class="btn btn-success">Marquer comme vérifié</button>
            </form>
        @endif
    </div>
@empty
    <div class="card empty">Aucun conflit {{ $showResolved ? 'vérifié' : 'à vérifier' }}. 👍</div>
@endforelse
{{ $conflicts->links() }}
@endsection
