<?php

namespace App\Http\Controllers;

use App\Enums\StockMovementType;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\StatsService;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StockController extends Controller
{
    /** Vue des quantités : un produit par ligne, un magasin par colonne. */
    public function index(Request $request)
    {
        $user = $this->user();
        $stores = $user->accessibleStores();
        $storeId = $this->selectedStoreId($request);
        $visibleStores = $storeId ? $stores->where('id', $storeId) : $stores;

        $products = Product::with(['category', 'stocks'])
            ->search($request->input('q'))
            ->when($request->input('category_id'), fn ($q, $c) => $q->where('category_id', $c))
            ->orderBy('name')
            ->paginate(30)->withQueryString();

        return view('stock.index', [
            'products' => $products,
            'stores' => $stores,
            'visibleStores' => $visibleStores->values(),
            'categories' => Category::orderBy('name')->get(),
            'storeId' => $storeId,
        ]);
    }

    public function alerts(Request $request, StatsService $stats)
    {
        $user = $this->user();

        return view('stock.alerts', [
            'stocks' => $stats->lowStock($stats->storeIds($user, $this->selectedStoreId($request)), 500),
            'stores' => $user->accessibleStores(),
            'storeId' => $this->selectedStoreId($request),
        ]);
    }

    public function movements(Request $request)
    {
        $user = $this->user();

        return view('stock.movements', [
            'movements' => StockMovement::with(['product', 'store', 'user'])
                ->visibleTo($user)
                ->forStore($this->selectedStoreId($request))
                ->when($request->input('type'), fn ($q, $t) => $q->where('type', $t))
                ->when($request->input('product_id'), fn ($q, $p) => $q->where('product_id', $p))
                ->when($request->input('from'), fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                ->when($request->input('to'), fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
                ->latest('id')->paginate(40)->withQueryString(),
            'stores' => $user->accessibleStores(),
            'storeId' => $this->selectedStoreId($request),
        ]);
    }

    /** Entrées (réception fournisseur, retour) et sorties (casse, usage interne…). */
    public function create(Request $request)
    {
        return view('stock.create', [
            'stores' => $this->user()->accessibleStores(),
            'products' => Product::orderBy('name')->get(['id', 'name', 'reference']),
            'selectedProduct' => $request->integer('product_id') ?: null,
        ]);
    }

    public function store(Request $request, StockService $stock)
    {
        $data = $request->validate([
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
            'type' => ['required', Rule::in([StockMovementType::In->value, StockMovementType::Out->value])],
            'reason' => ['required', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);
        $storeId = $this->storeIdForCreation($request);
        $type = StockMovementType::from($data['type']);

        DB::transaction(function () use ($data, $storeId, $type, $stock) {
            foreach ($data['items'] as $item) {
                $delta = $type === StockMovementType::In ? (int) $item['quantity'] : -(int) $item['quantity'];
                $stock->move((int) $item['product_id'], $storeId, $delta, $type, $data['reason'], null, $this->user());
            }
        });

        return redirect()->route('stock.movements', ['store_id' => $storeId])->with('success', ($type === StockMovementType::In ? 'Entrée' : 'Sortie').' de stock enregistrée.');
    }

    /** Inventaire : saisie des quantités réellement constatées. */
    public function correction(Request $request)
    {
        $user = $this->user();
        $stores = $user->accessibleStores();
        $storeId = $this->selectedStoreId($request) ?? $stores->first()?->id;

        return view('stock.correction', [
            'stores' => $stores,
            'storeId' => $storeId,
            'products' => Product::with(['stocks' => fn ($q) => $q->where('store_id', $storeId)])
                ->search($request->input('q'))
                ->when($request->input('category_id'), fn ($q, $c) => $q->where('category_id', $c))
                ->orderBy('name')->get(),
            'categories' => Category::orderBy('name')->get(),
        ]);
    }

    public function correct(Request $request, StockService $stock)
    {
        $data = $request->validate([
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'reason' => ['required', 'string', 'max:255'],
            'counts' => ['required', 'array'],
            'counts.*' => ['nullable', 'integer', 'min:0'],
        ]);
        $this->authorizeStore((int) $data['store_id']);

        $changes = 0;
        DB::transaction(function () use ($data, $stock, &$changes) {
            $products = Product::whereIn('id', array_keys($data['counts']))->get()->keyBy('id');
            foreach ($data['counts'] as $productId => $counted) {
                if ($counted === null || ! isset($products[$productId])) {
                    continue;
                }
                if ($stock->correct($products[$productId], (int) $data['store_id'], (int) $counted, $data['reason'], $this->user())) {
                    $changes++;
                }
            }
        });

        return redirect()->route('stock.movements', ['store_id' => $data['store_id'], 'type' => 'adjustment'])
            ->with('success', $changes ? "$changes correction(s) de stock enregistrée(s)." : 'Aucun écart constaté.');
    }
}
