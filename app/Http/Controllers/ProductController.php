<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Store;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $storeId = $this->storeFilter($request);

        $products = Product::with(['category', 'stocks' => fn ($q) => $q->visibleTo($user, $storeId)])
            ->search($request->input('q'))
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')))
            ->when($request->input('availability') === 'available', fn ($q) => $q->where('is_available', true))
            ->when($request->input('availability') === 'unavailable', fn ($q) => $q->where('is_available', false))
            ->when($request->boolean('low'), fn ($q) => $q->whereHas('stocks', fn ($s) => $s->visibleTo($user, $storeId)->low()))
            ->orderBy('name')
            ->paginate(25)->withQueryString();

        return view('products.index', [
            'products' => $products,
            'categories' => Category::orderBy('name')->get(),
            'stores' => $this->selectableStores($request),
            'storeId' => $storeId,
        ]);
    }

    public function create()
    {
        return view('products.form', ['product' => new Product(['is_available' => true, 'sync_to_website' => true]), 'categories' => Category::orderBy('name')->get()]);
    }

    public function store(Request $request, StockService $stock)
    {
        $data = $this->validated($request);
        $product = DB::transaction(function () use ($data, $stock) {
            $product = Product::create($data);
            $stock->ensureStockRows($product);

            return $product;
        });

        return redirect()->route('products.show', $product)->with('success', 'Produit créé. Pensez à saisir les entrées de stock.');
    }

    public function show(Request $request, Product $product)
    {
        $user = $request->user();
        $product->load('category');
        $stocks = Stock::where('product_id', $product->id)->visibleTo($user)->with('store')->get()->sortBy('store.name');
        $movements = $product->movements()->visibleTo($user)->with('store', 'user')->latest()->limit(30)->get();

        return view('products.show', compact('product', 'stocks', 'movements'));
    }

    public function edit(Product $product)
    {
        return view('products.form', ['product' => $product, 'categories' => Category::orderBy('name')->get()]);
    }

    public function update(Request $request, Product $product)
    {
        $data = $this->validated($request, $product);
        $user = $request->user();

        // La modification de prix est une action sensible
        if ((float) $data['price'] !== (float) $product->price) {
            abort_unless($user->hasPermission('products.price'), 403, 'Seule la Direction peut modifier les prix.');
            ActivityLog::record('produit.prix', sprintf(
                'Prix de « %s » : %s → %s', $product->name, money($product->price), money($data['price'])
            ), $product, ['ancien_prix' => (float) $product->price, 'nouveau_prix' => (float) $data['price']]);
        }

        $product->update($data);

        return redirect()->route('products.show', $product)->with('success', 'Produit mis à jour.');
    }

    public function destroy(Request $request, Product $product)
    {
        if ($product->orderItems()->exists()) {
            $product->update(['is_available' => false]);

            return back()->with('success', 'Ce produit a déjà été vendu : il a été marqué indisponible au lieu d’être supprimé.');
        }
        ActivityLog::record('produit.suppression', "Suppression du produit « {$product->name} » ({$product->reference})", null, $product->only('id', 'reference', 'name', 'price'));
        $product->delete();

        return redirect()->route('products.index')->with('success', 'Produit supprimé.');
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:50', Rule::unique('products')->ignore($product)],
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'new_category' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'price' => ['required', 'numeric', 'min:0'],
            'is_available' => ['boolean'],
            'sync_to_website' => ['boolean'],
        ]);
        if (! empty($data['new_category'])) {
            $data['category_id'] = Category::firstOrCreate(['name' => trim($data['new_category'])])->id;
        }
        unset($data['new_category']);
        $data['is_available'] = $request->boolean('is_available');
        $data['sync_to_website'] = $request->boolean('sync_to_website');

        return $data;
    }
}
