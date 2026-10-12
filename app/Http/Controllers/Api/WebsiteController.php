<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Models\ApiClient;
use App\Models\Order;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Store;
use App\Services\AuditLogger;
use App\Services\OrderService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * API d'échange avec le site web Mobilier Addict.
 * Les commandes du site sont identifiées par leur référence externe : un même
 * envoi répété ne crée jamais de doublon.
 */
class WebsiteController extends Controller
{
    public function ping(Request $request): JsonResponse
    {
        return response()->json(['status' => 'ok', 'client' => $this->client($request)->name, 'time' => now()->toIso8601String()]);
    }

    public function stores(): JsonResponse
    {
        return response()->json(['data' => Store::active()->orderBy('name')->get(['id', 'code', 'name', 'city', 'address', 'phone'])]);
    }

    public function products(Request $request): JsonResponse
    {
        $products = Product::with(['category', 'stocks.store'])
            ->where('sync_with_website', true)
            ->when($request->input('updated_since'), fn ($q, $d) => $q->where('updated_at', '>=', $d))
            ->orderBy('id')
            ->paginate(min(200, $request->integer('per_page', 100)));

        return response()->json([
            'data' => $products->getCollection()->map(fn ($p) => $this->productPayload($p)),
            'meta' => ['current_page' => $products->currentPage(), 'last_page' => $products->lastPage(), 'total' => $products->total()],
        ]);
    }

    public function product(string $reference): JsonResponse
    {
        $product = Product::with(['category', 'stocks.store'])->where('reference', $reference)->where('sync_with_website', true)->firstOrFail();

        return response()->json(['data' => $this->productPayload($product)]);
    }

    /** Disponibilités par produit et par magasin. */
    public function stock(Request $request): JsonResponse
    {
        $stocks = Stock::with(['product:id,reference', 'store:id,code'])
            ->whereHas('product', fn ($q) => $q->where('sync_with_website', true))
            ->when($request->input('store'), fn ($q, $code) => $q->whereHas('store', fn ($s) => $s->where('code', $code)))
            ->get();

        return response()->json([
            'data' => $stocks->groupBy('product.reference')->map(fn ($rows, $ref) => [
                'reference' => $ref,
                'total' => max(0, $rows->sum('quantity')),
                'stores' => $rows->mapWithKeys(fn ($s) => [$s->store->code => max(0, $s->quantity)]),
            ])->values(),
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    public function storeOrder(Request $request, OrderService $orders): JsonResponse
    {
        $data = $request->validate([
            'external_reference' => ['required', 'string', 'max:100'],
            'store_code' => ['nullable', 'string', 'exists:stores,code'],
            'customer' => ['required', 'array'],
            'customer.name' => ['required', 'string', 'max:255'],
            'customer.phone' => ['required', 'string', 'max:30'],
            'customer.email' => ['nullable', 'email', 'max:255'],
            'customer.address' => ['nullable', 'string', 'max:255'],
            'customer.city' => ['nullable', 'string', 'max:100'],
            'customer.website_customer_id' => ['nullable', 'string', 'max:100'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.reference' => ['required', 'string', 'exists:products,reference'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'integer', 'min:0'],
            'discount' => ['nullable', 'integer', 'min:0'],
            'amount_paid' => ['nullable', 'integer', 'min:0'],
            'payment_method' => ['nullable', 'string', 'max:30'],
            'delivery' => ['nullable', 'array'],
            'delivery.address' => ['nullable', 'string', 'max:255'],
            'delivery.city' => ['nullable', 'string', 'max:100'],
            'delivery.contact_name' => ['nullable', 'string', 'max:255'],
            'delivery.contact_phone' => ['nullable', 'string', 'max:30'],
            'delivery.scheduled_date' => ['nullable', 'date'],
            'delivery.fee' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'ordered_at' => ['nullable', 'date'],
        ]);

        // Contrôle des doublons : la référence du site identifie la commande de manière unique.
        if ($existing = $this->findWebsiteOrder($data['external_reference'])) {
            return response()->json(['duplicate' => true, 'data' => $this->orderPayload($existing)], 200);
        }

        $store = ! empty($data['store_code'])
            ? Store::where('code', $data['store_code'])->first()
            : ($this->client($request)->defaultStore ?? Store::where('code', config('gesma.website.default_store_code'))->first() ?? Store::active()->orderBy('id')->first());
        abort_unless($store, 422, 'Aucun magasin disponible pour cette commande.');

        $products = Product::whereIn('reference', array_column($data['items'], 'reference'))->get()->keyBy('reference');
        $payload = [
            'type' => OrderType::Order->value,
            'source' => OrderSource::Website->value,
            'external_reference' => $data['external_reference'],
            'store_id' => $store->id,
            'customer' => $data['customer'],
            'items' => array_map(fn ($i) => [
                'product_id' => $products[$i['reference']]->id,
                'quantity' => $i['quantity'],
                'unit_price' => $i['unit_price'] ?? null,
            ], $data['items']),
            'discount' => $data['discount'] ?? 0,
            'amount_paid' => $data['amount_paid'] ?? 0,
            'payment_method' => $data['payment_method'] ?? null,
            'requires_delivery' => ! empty($data['delivery']['address']),
            'delivery' => $data['delivery'] ?? [],
            'notes' => $data['notes'] ?? null,
            'ordered_at' => $data['ordered_at'] ?? null,
        ];

        try {
            $order = DB::transaction(fn () => $orders->create($payload, null));
            $stockWarning = null;
        } catch (InsufficientStockException $e) {
            if (! config('gesma.website.accept_orders_without_stock')) {
                return response()->json(['message' => $e->getMessage(), 'shortages' => $e->shortages], 409);
            }
            // La commande est acceptée mais signalée : le magasin doit réapprovisionner ou contacter le client.
            $order = DB::transaction(fn () => $orders->create($payload, null, true));
            $stockWarning = $e->getMessage();
            AuditLogger::log('website.stock_conflict', 'Commande site '.$order->number.' acceptée avec stock insuffisant : '.$e->getMessage(), $order, ['shortages' => $e->shortages], null, $store->id);
        } catch (UniqueConstraintViolationException) {
            // Deux envois simultanés de la même commande : on renvoie celle déjà créée.
            return response()->json(['duplicate' => true, 'data' => $this->orderPayload($this->findWebsiteOrder($data['external_reference']))], 200);
        }

        return response()->json(['duplicate' => false, 'stock_warning' => $stockWarning, 'data' => $this->orderPayload($order)], 201);
    }

    public function showOrder(string $externalReference): JsonResponse
    {
        $order = $this->findWebsiteOrder($externalReference);
        abort_unless($order, 404, 'Commande introuvable.');

        return response()->json(['data' => $this->orderPayload($order)]);
    }

    public function cancelOrder(Request $request, string $externalReference, OrderService $orders): JsonResponse
    {
        $order = $this->findWebsiteOrder($externalReference);
        abort_unless($order, 404, 'Commande introuvable.');
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        if ($order->status !== OrderStatus::Cancelled) {
            $orders->changeStatus($order, OrderStatus::Cancelled, null, 'Annulée depuis le site : '.$data['reason']);
        }

        return response()->json(['data' => $this->orderPayload($order->refresh())]);
    }

    private function findWebsiteOrder(string $externalReference): ?Order
    {
        return Order::with(['items', 'delivery', 'store', 'customer'])
            ->where('source', OrderSource::Website->value)
            ->where('external_reference', $externalReference)->first();
    }

    private function client(Request $request): ApiClient
    {
        return $request->attributes->get('api_client');
    }

    private function productPayload(Product $product): array
    {
        return [
            'reference' => $product->reference,
            'name' => $product->name,
            'category' => $product->category?->name,
            'description' => $product->description,
            'price' => $product->price,
            'status' => $product->status->value,
            'available' => $product->status->value === 'available' && $product->stocks->sum('quantity') > 0,
            'stock_total' => max(0, $product->stocks->sum('quantity')),
            'stock_by_store' => $product->stocks->mapWithKeys(fn ($s) => [$s->store->code => max(0, $s->quantity)]),
            'image_url' => $product->image_path ? asset('storage/'.$product->image_path) : null,
            'updated_at' => $product->updated_at?->toIso8601String(),
        ];
    }

    private function orderPayload(Order $order): array
    {
        $order->loadMissing(['items', 'delivery', 'store']);

        return [
            'number' => $order->number,
            'external_reference' => $order->external_reference,
            'status' => $order->status->value,
            'status_label' => $order->status->label(),
            'store' => $order->store->code,
            'total' => $order->total,
            'amount_paid' => $order->amount_paid,
            'delivery_status' => $order->delivery?->status->value,
            'items' => $order->items->map(fn ($i) => ['reference' => $i->product_reference, 'name' => $i->product_name, 'quantity' => $i->quantity, 'unit_price' => $i->unit_price]),
            'ordered_at' => $order->ordered_at->toIso8601String(),
            'updated_at' => $order->updated_at->toIso8601String(),
        ];
    }
}
