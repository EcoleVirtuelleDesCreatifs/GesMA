<?php

namespace App\Http\Controllers;

use App\Enums\ProductStatus;
use App\Enums\StockMovementType;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\AuditLogger;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $user = $this->user();
        $storeId = $this->selectedStoreId($request);
        $products = Product::with(['category', 'stocks'])
            ->search($request->input('q'))
            ->when($request->input('category_id'), fn ($q, $c) => $q->where('category_id', $c))
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderBy('name')
            ->paginate(25)->withQueryString();

        return view('products.index', [
            'products' => $products,
            'categories' => Category::orderBy('name')->get(),
            'stores' => $user->accessibleStores(),
            'storeId' => $storeId,
        ]);
    }

    public function create()
    {
        return view('products.form', [
            'product' => new Product(['status' => ProductStatus::Available, 'low_stock_threshold' => 2]),
            'categories' => Category::orderBy('name')->get(),
            'stores' => $this->user()->accessibleStores(),
        ]);
    }

    public function store(Request $request, StockService $stock)
    {
        $data = $this->validated($request);
        $initial = $request->validate([
            'initial_stock' => ['nullable', 'array'],
            'initial_stock.*' => ['nullable', 'integer', 'min:0'],
        ])['initial_stock'] ?? [];

        $product = DB::transaction(function () use ($data, $initial, $stock, $request) {
            $product = Product::create($data);
            $this->storeImage($request, $product);
            foreach ($initial as $storeId => $qty) {
                if ((int) $qty > 0 && $this->user()->canAccessStore($storeId)) {
                    $stock->move($product->id, (int) $storeId, (int) $qty, StockMovementType::In, 'Stock initial', null, $this->user());
                }
            }

            return $product;
        });

        return redirect()->route('products.show', $product)->with('success', 'Produit créé.');
    }

    public function show(Product $product)
    {
        $user = $this->user();
        $product->load(['category', 'stocks.store']);

        return view('products.show', [
            'product' => $product,
            'stocks' => $product->stocks->filter(fn ($s) => $user->canAccessStore($s->store_id)),
            'movements' => StockMovement::with(['store', 'user'])->where('product_id', $product->id)
                ->visibleTo($user)->latest('id')->limit(30)->get(),
        ]);
    }

    public function edit(Product $product)
    {
        return view('products.form', [
            'product' => $product,
            'categories' => Category::orderBy('name')->get(),
            'stores' => collect(),
        ]);
    }

    public function update(Request $request, Product $product)
    {
        $data = $this->validated($request, $product);
        if ((int) $data['price'] !== $product->price) {
            abort_unless($this->user()->hasPermission('products.price'), 403, "Vous n'êtes pas autorisé à modifier les prix.");
            AuditLogger::log('product.price', sprintf('Prix de « %s » modifié : %s → %s', $product->name, money($product->price), money($data['price'])), $product, ['before' => $product->price, 'after' => (int) $data['price']]);
        }
        $product->update($data);
        $this->storeImage($request, $product);

        return redirect()->route('products.show', $product)->with('success', 'Produit mis à jour.');
    }

    public function destroy(Product $product)
    {
        $product->update(['status' => ProductStatus::Discontinued]);
        $product->delete();
        AuditLogger::log('product.delete', 'Produit archivé : '.$product->name, $product);

        return redirect()->route('products.index')->with('success', 'Produit archivé.');
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:50', Rule::unique('products')->ignore($product?->id)],
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'description' => ['nullable', 'string', 'max:5000'],
            'price' => ['required', 'integer', 'min:0'],
            'cost_price' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', Rule::enum(ProductStatus::class)],
            'low_stock_threshold' => ['required', 'integer', 'min:0'],
            'sync_with_website' => ['nullable', 'boolean'],
            'image' => ['nullable', 'image', 'max:4096'],
        ]);
        $data['sync_with_website'] = $request->boolean('sync_with_website');
        unset($data['image']);

        return $data;
    }

    private function storeImage(Request $request, Product $product): void
    {
        if ($request->hasFile('image')) {
            if ($product->image_path) {
                Storage::disk('public')->delete($product->image_path);
            }
            $product->update(['image_path' => $request->file('image')->store('products', 'public')]);
        }
    }
}
