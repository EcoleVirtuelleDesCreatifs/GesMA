<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\Store;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StockController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $storeId = $this->storeFilter($request);

        $stocks = Stock::visibleTo($user, $storeId)
            ->join('products', 'products.id', '=', 'stocks.product_id')
            ->select('stocks.*')
            ->with('product.category', 'store')
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('products.name', 'like', '%'.$request->input('q').'%')
                ->orWhere('products.reference', 'like', '%'.$request->input('q').'%')))
            ->when($request->filled('category_id'), fn ($q) => $q->where('products.category_id', $request->integer('category_id')))
            ->when($request->boolean('low'), fn ($q) => $q->low())
            ->orderBy('products.name')
            ->paginate(40)->withQueryString();

        return view('stocks.index', [
            'stocks' => $stocks,
            'stores' => $this->selectableStores($request),
            'storeId' => $storeId,
            'categories' => Category::orderBy('name')->get(),
        ]);
    }

    public function movements(Request $request)
    {
        $user = $request->user();
        $storeId = $this->storeFilter($request);

        $movements = StockMovement::visibleTo($user, $storeId)
            ->with('product', 'store', 'user')
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->input('type')))
            ->when($request->filled('product_id'), fn ($q) => $q->where('product_id', $request->integer('product_id')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->input('to')))
            ->latest()
            ->paginate(50)->withQueryString();

        return view('stocks.movements', [
            'movements' => $movements,
            'stores' => $this->selectableStores($request),
            'storeId' => $storeId,
        ]);
    }

    public function createMovement(Request $request)
    {
        return view('stocks.movement-form', [
            'stores' => $this->selectableStores($request),
            'products' => Product::orderBy('name')->get(['id', 'name', 'reference']),
            'selectedProduct' => $request->integer('product_id'),
            'selectedStore' => $request->integer('store_id') ?: $request->user()->store_id,
            'mode' => $request->input('mode', 'entree'),
        ]);
    }

    public function storeMovement(Request $request, StockService $service)
    {
        $data = $request->validate([
            'mode' => ['required', Rule::in(['entree', 'sortie', 'correction'])],
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:0'],
            'reason' => [Rule::requiredIf(fn () => $request->input('mode') !== 'entree'), 'nullable', 'string', 'max:255'],
        ]);
        $storeId = $this->storeForCreation($request);
        $store = Store::findOrFail($storeId);
        $product = Product::findOrFail($data['product_id']);
        $user = $request->user();

        if ($data['mode'] === 'correction') {
            abort_unless($user->hasPermission('stock.adjust'), 403);
            $movement = $service->correct($product, $store, (int) $data['quantity'], $data['reason'], $user);
            $message = $movement ? "Stock corrigé : {$movement->quantity_before} → {$movement->quantity_after}." : 'Aucune différence : stock inchangé.';
        } else {
            if ((int) $data['quantity'] < 1) {
                return back()->withInput()->withErrors(['quantity' => 'La quantité doit être au moins 1.']);
            }
            $delta = $data['mode'] === 'entree' ? (int) $data['quantity'] : -(int) $data['quantity'];
            $movement = $service->move($product->id, $store->id, $delta, $data['mode'], $data['reason'] ?? null, null, $user);
            if ($data['mode'] === 'sortie') {
                ActivityLog::record('stock.mouvement', sprintf('Sortie de %d × « %s » (%s) : %s', $data['quantity'], $product->name, $store->name, $data['reason']), $product, [], $store->id);
            }
            $message = "Mouvement enregistré. Nouveau stock : {$movement->quantity_after}.";
        }

        return redirect()->route('products.show', $product)->with('success', $message);
    }

    public function updateThreshold(Request $request, Stock $stock)
    {
        $this->authorizeStore($request, $stock);
        $data = $request->validate(['alert_threshold' => ['required', 'integer', 'min:0', 'max:100000']]);
        $stock->update($data);

        return back()->with('success', 'Seuil d’alerte mis à jour.');
    }

    public function transfers(Request $request)
    {
        $user = $request->user();
        $ids = $user->accessibleStoreIds();
        $transfers = StockTransfer::with('product', 'fromStore', 'toStore', 'user')
            ->where(fn ($q) => $q->whereIn('from_store_id', $ids)->orWhereIn('to_store_id', $ids))
            ->latest()->paginate(30);

        return view('stocks.transfers', compact('transfers'));
    }

    public function createTransfer(Request $request)
    {
        return view('stocks.transfer-form', [
            'fromStores' => $this->selectableStores($request),
            'toStores' => Store::active()->orderBy('name')->get(),
            'products' => Product::orderBy('name')->with('stocks')->get(),
            'selectedProduct' => $request->integer('product_id'),
        ]);
    }

    public function storeTransfer(Request $request, StockService $service)
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'from_store_id' => ['required', 'exists:stores,id', 'different:to_store_id'],
            'to_store_id' => ['required', 'exists:stores,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], ['from_store_id.different' => 'Choisissez deux magasins différents.']);

        // Seul le magasin de départ doit appartenir à l'utilisateur
        $this->authorizeStore($request, (int) $data['from_store_id']);

        $transfer = $service->transfer(
            Product::findOrFail($data['product_id']),
            Store::findOrFail($data['from_store_id']),
            Store::findOrFail($data['to_store_id']),
            (int) $data['quantity'],
            $data['notes'] ?? null,
            $request->user()
        );

        return redirect()->route('stocks.transfers')->with('success', "Transfert {$transfer->number} enregistré.");
    }
}
