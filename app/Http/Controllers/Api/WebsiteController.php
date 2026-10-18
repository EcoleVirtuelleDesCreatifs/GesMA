<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Store;
use App\Models\SyncConflict;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

/**
 * API d'échange avec le site web Mobilier Addict (section 12).
 * Authentification : en-tête X-API-KEY.
 */
class WebsiteController extends Controller
{
    /** GET /api/v1/products?updated_since=2026-10-01T00:00:00Z */
    public function products(Request $request)
    {
        $request->validate(['updated_since' => ['nullable', 'date'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:500']]);

        $products = Product::where('sync_to_website', true)
            ->with('category:id,name', 'stocks:id,product_id,store_id,quantity')
            ->when($request->filled('updated_since'), fn ($q) => $q->where(fn ($w) => $w
                ->where('updated_at', '>=', $request->date('updated_since'))
                ->orWhereHas('stocks', fn ($s) => $s->where('updated_at', '>=', $request->date('updated_since')))))
            ->orderBy('id')
            ->paginate($request->integer('per_page', 100));

        return response()->json([
            'data' => $products->getCollection()->map(fn (Product $p) => $this->productPayload($p)),
            'meta' => ['current_page' => $products->currentPage(), 'last_page' => $products->lastPage(), 'total' => $products->total()],
        ]);
    }

    /** GET /api/v1/stocks : disponibilité par produit et par magasin */
    public function stocks()
    {
        $stores = Store::active()->get(['id', 'code', 'name', 'city'])->keyBy('id');
        $stocks = Stock::whereIn('store_id', $stores->keys())
            ->whereHas('product', fn ($q) => $q->where('sync_to_website', true))
            ->with('product:id,reference')
            ->get()
            ->groupBy(fn ($s) => $s->product->reference)
            ->map(fn ($rows) => [
                'total' => max(0, (int) $rows->sum('quantity')),
                'stores' => $rows->map(fn ($s) => ['store' => $stores[$s->store_id]->code, 'quantity' => max(0, $s->quantity)])->values(),
            ]);

        return response()->json(['generated_at' => now()->toIso8601String(), 'data' => $stocks]);
    }

    /**
     * POST /api/v1/orders : enregistre une commande passée sur le site.
     * Idempotent : la même référence de commande du site ne crée jamais de doublon.
     */
    public function storeOrder(Request $request, OrderService $service)
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:100'],
            'store_code' => ['nullable', 'string', Rule::exists('stores', 'code')],
            'ordered_at' => ['nullable', 'date'],
            'customer.name' => ['required', 'string', 'max:255'],
            'customer.phone' => ['nullable', 'string', 'max:30'],
            'customer.email' => ['nullable', 'email'],
            'customer.address' => ['nullable', 'string', 'max:255'],
            'customer.city' => ['nullable', 'string', 'max:100'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_reference' => ['required', 'string', Rule::exists('products', 'reference')],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', Rule::in(array_keys(config('gesma.payment_methods')))],
            'delivery' => ['nullable', 'array'],
            'delivery.address' => ['nullable', 'string', 'max:255'],
            'delivery.city' => ['nullable', 'string', 'max:100'],
            'delivery.scheduled_date' => ['nullable', 'date'],
            'delivery.fee' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        // Verrou pour éviter deux créations simultanées de la même référence
        return Cache::lock('website-order-'.$data['reference'], 15)->block(10, function () use ($data, $service) {
            $existing = Order::where('source', 'site')->where('external_reference', $data['reference'])->first();
            if ($existing) {
                return response()->json(['duplicate' => true, 'data' => $this->orderPayload($existing)], 200);
            }

            $store = Store::where('code', $data['store_code'] ?? config('gesma.website.default_store'))->first()
                ?? Store::active()->orderBy('id')->firstOrFail();

            $products = Product::whereIn('reference', collect($data['items'])->pluck('product_reference'))->get()->keyBy('reference');

            $payload = [
                'store_id' => $store->id,
                'external_reference' => $data['reference'],
                'ordered_at' => $data['ordered_at'] ?? null,
                'customer' => $data['customer'],
                'items' => collect($data['items'])->map(fn ($i) => [
                    'product_id' => $products[$i['product_reference']]->id,
                    'quantity' => $i['quantity'],
                    'unit_price' => $i['unit_price'] ?? null,
                ])->all(),
                'discount' => $data['discount'] ?? 0,
                'amount_paid' => $data['amount_paid'] ?? 0,
                'payment_method' => $data['payment_method'] ?? null,
                'notes' => trim('Commande du site '.$data['reference'].'. '.($data['notes'] ?? '')),
                'delivery' => $data['delivery'] ?? null,
            ];
            // Sans adresse de livraison : retrait en magasin
            if (empty($payload['delivery']['address'])) {
                $payload['delivery'] = null;
            }
            foreach ($payload['items'] as &$item) {
                if ($item['unit_price'] === null) {
                    unset($item['unit_price']);
                }
            }
            unset($item);

            [$order, $shortages] = $service->create($payload, null, 'site', allowNegativeStock: true, usePayloadPrices: true);

            if ($shortages) {
                SyncConflict::create([
                    'store_id' => $order->store_id,
                    'type' => 'site_stock_insuffisant',
                    'message' => "Commande du site {$data['reference']} ({$order->number}) : stock insuffisant.",
                    'details' => ['ruptures' => $shortages],
                    'subject_type' => $order->getMorphClass(),
                    'subject_id' => $order->id,
                ]);
            }

            return response()->json(['duplicate' => false, 'stock_warning' => (bool) $shortages, 'data' => $this->orderPayload($order)], 201);
        });
    }

    /** GET /api/v1/orders/{reference} : suivi d'une commande du site */
    public function showOrder(string $reference)
    {
        $order = Order::where('source', 'site')->where('external_reference', $reference)->firstOrFail();

        return response()->json(['data' => $this->orderPayload($order)]);
    }

    private function productPayload(Product $p): array
    {
        return [
            'reference' => $p->reference,
            'name' => $p->name,
            'category' => $p->category?->name,
            'description' => $p->description,
            'price' => (float) $p->price,
            'is_available' => $p->is_available,
            'stock_total' => max(0, (int) $p->stocks->sum('quantity')),
            'updated_at' => $p->updated_at?->toIso8601String(),
        ];
    }

    private function orderPayload(Order $order): array
    {
        $order->loadMissing('items', 'store', 'delivery');

        return [
            'gesma_number' => $order->number,
            'reference' => $order->external_reference,
            'status' => $order->status,
            'status_label' => $order->statusLabel(),
            'store' => $order->store->code,
            'total' => (float) $order->total,
            'amount_paid' => (float) $order->amount_paid,
            'delivery_status' => $order->delivery?->status,
            'items' => $order->items->map(fn ($i) => [
                'reference' => $i->product_reference,
                'name' => $i->product_name,
                'quantity' => $i->quantity,
                'unit_price' => (float) $i->unit_price,
            ]),
            'updated_at' => $order->updated_at?->toIso8601String(),
        ];
    }
}
