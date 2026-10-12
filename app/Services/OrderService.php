<?php

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\StockMovementType;
use App\Exceptions\BusinessRuleException;
use App\Jobs\NotifyWebsiteOfOrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderService
{
    public function __construct(private StockService $stock, private NumberGenerator $numbers) {}

    /**
     * Crée une commande (ou une vente directe), réserve le stock du magasin et prépare la livraison.
     *
     * @param  bool  $allowNegativeStock  utilisé pour les commandes du site web et les conflits forcés
     */
    public function create(array $data, ?User $user, bool $allowNegativeStock = false): Order
    {
        $type = OrderType::from($data['type'] ?? OrderType::Order->value);
        $source = OrderSource::from($data['source'] ?? OrderSource::Gesma->value);
        $storeId = (int) $data['store_id'];

        if (empty($data['items'])) {
            throw new BusinessRuleException('La commande doit contenir au moins un produit.');
        }

        return DB::transaction(function () use ($data, $user, $allowNegativeStock, $type, $source, $storeId) {
            $lines = $this->buildLines($data['items'], $user, $source);

            if (! $allowNegativeStock) {
                $quantities = [];
                foreach ($lines as $line) {
                    $quantities[$line['product_id']] = ($quantities[$line['product_id']] ?? 0) + $line['quantity'];
                }
                $this->stock->assertAvailable($storeId, $quantities);
            }

            $customer = $this->resolveCustomer($data, $storeId);
            $subtotal = array_sum(array_column($lines, 'line_total'));
            $discount = (int) ($data['discount'] ?? 0);
            $requiresDelivery = $type === OrderType::Order && (bool) ($data['requires_delivery'] ?? false);
            $deliveryFee = $requiresDelivery ? (int) ($data['delivery']['fee'] ?? $data['delivery_fee'] ?? 0) : 0;
            $total = max(0, $subtotal - $discount + $deliveryFee);
            $orderedAt = isset($data['ordered_at']) ? Carbon::parse($data['ordered_at']) : now();
            $status = $type === OrderType::Sale ? OrderStatus::Delivered : OrderStatus::Preparing;

            $order = Order::create([
                'uuid' => $data['uuid'] ?? (string) Str::uuid(),
                'number' => $this->numbers->next($type === OrderType::Sale ? NumberGenerator::SALE : NumberGenerator::ORDER),
                'type' => $type,
                'source' => $source,
                'external_reference' => $data['external_reference'] ?? null,
                'customer_id' => $customer?->id,
                'store_id' => $storeId,
                'user_id' => $user?->id,
                'status' => $status,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'delivery_fee' => $deliveryFee,
                'total' => $total,
                'amount_paid' => min($total, (int) ($data['amount_paid'] ?? ($type === OrderType::Sale ? $total : 0))),
                'payment_method' => $data['payment_method'] ?? null,
                'requires_delivery' => $requiresDelivery,
                'delivery_address' => $requiresDelivery ? ($data['delivery']['address'] ?? $customer?->address) : null,
                'delivery_city' => $requiresDelivery ? ($data['delivery']['city'] ?? $customer?->city) : null,
                'notes' => $data['notes'] ?? null,
                'ordered_at' => $orderedAt,
                'delivered_at' => $status === OrderStatus::Delivered ? $orderedAt : null,
            ]);

            foreach ($lines as $line) {
                $order->items()->create(Arr::except($line, ['catalog_price', 'price_overridden']));
                $this->stock->move(
                    $line['product_id'], $storeId, -$line['quantity'], StockMovementType::Sale,
                    'Commande '.$order->number, $order, $user, $allowNegativeStock
                );
            }

            $order->history()->create(['from_status' => null, 'to_status' => $status, 'comment' => 'Création ('.$source->label().')', 'user_id' => $user?->id]);

            if ($requiresDelivery) {
                $delivery = $data['delivery'] ?? [];
                $order->delivery()->create([
                    'store_id' => $storeId,
                    'status' => DeliveryStatus::Pending,
                    'address' => $delivery['address'] ?? $customer?->address ?? 'À préciser',
                    'city' => $delivery['city'] ?? $customer?->city,
                    'contact_name' => $delivery['contact_name'] ?? $customer?->name,
                    'contact_phone' => $delivery['contact_phone'] ?? $customer?->phone,
                    'scheduled_date' => $delivery['scheduled_date'] ?? null,
                    'driver_id' => $delivery['driver_id'] ?? null,
                    'driver_name' => $delivery['driver_name'] ?? null,
                    'fee' => $deliveryFee,
                ]);
            }

            foreach ($lines as $line) {
                if ($line['price_overridden']) {
                    AuditLogger::log('order.price_override', sprintf(
                        'Prix modifié sur %s pour « %s » : %s → %s',
                        $order->number, $line['product_name'], money($line['catalog_price']), money($line['unit_price'])
                    ), $order, ['product_id' => $line['product_id'], 'catalog_price' => $line['catalog_price'], 'unit_price' => $line['unit_price']], $user);
                }
            }

            return $order->load('items', 'customer', 'delivery');
        });
    }

    public function changeStatus(Order $order, OrderStatus $to, ?User $user, ?string $reason = null, bool $fromDelivery = false): Order
    {
        $from = $order->status;
        if ($from === $to) {
            return $order;
        }
        if (! $from->canTransitionTo($to)) {
            throw new BusinessRuleException(sprintf('Impossible de passer la commande de « %s » à « %s ».', $from->label(), $to->label()));
        }
        if (in_array($to, [OrderStatus::Cancelled, OrderStatus::Returned, OrderStatus::NotDelivered], true) && ! $reason) {
            throw new BusinessRuleException('Un motif est obligatoire pour ce changement de statut.');
        }

        return DB::transaction(function () use ($order, $from, $to, $user, $reason, $fromDelivery) {
            $attributes = ['status' => $to, 'status_reason' => $reason ?? $order->status_reason];
            match ($to) {
                OrderStatus::Delivered => $attributes['delivered_at'] = now(),
                OrderStatus::Cancelled => $attributes['cancelled_at'] = now(),
                OrderStatus::Returned => $attributes['returned_at'] = now(),
                default => null,
            };
            $order->update($attributes);

            // Les produits annulés ou retournés réintègrent le stock du magasin.
            if ($from->holdsStock() && ! $to->holdsStock()) {
                $type = $to === OrderStatus::Returned ? StockMovementType::Return : StockMovementType::Cancellation;
                foreach ($order->items as $item) {
                    if ($item->product_id) {
                        $this->stock->move($item->product_id, $order->store_id, $item->quantity, $type, $to->label().' '.$order->number.($reason ? ' — '.$reason : ''), $order, $user);
                    }
                }
            }

            $order->history()->create(['from_status' => $from, 'to_status' => $to, 'comment' => $reason, 'user_id' => $user?->id]);

            if (! $fromDelivery && $order->delivery) {
                $deliveryStatus = match ($to) {
                    OrderStatus::Shipped => DeliveryStatus::InTransit,
                    OrderStatus::Delivered => DeliveryStatus::Delivered,
                    OrderStatus::NotDelivered => DeliveryStatus::Failed,
                    OrderStatus::Returned => DeliveryStatus::Returned,
                    default => null,
                };
                if ($deliveryStatus) {
                    $order->delivery->update(array_filter([
                        'status' => $deliveryStatus,
                        'failure_reason' => $deliveryStatus->requiresReason() ? $reason : null,
                        'departed_at' => $deliveryStatus === DeliveryStatus::InTransit ? now() : null,
                        'delivered_at' => $deliveryStatus === DeliveryStatus::Delivered ? now() : null,
                    ]));
                }
            }

            if ($order->source === OrderSource::Website && config('gesma.website.webhook_url')) {
                NotifyWebsiteOfOrderStatus::dispatch($order->id)->afterCommit();
            }

            if (in_array($to, [OrderStatus::Cancelled, OrderStatus::Returned], true)) {
                AuditLogger::log('order.'.$to->value, sprintf('Commande %s %s : %s', $order->number, mb_strtolower($to->label()), $reason), $order, ['from' => $from->value], $user);
            }

            return $order->refresh();
        });
    }

    /** @return list<array<string, mixed>> */
    private function buildLines(array $items, ?User $user, OrderSource $source): array
    {
        $products = Product::withTrashed()->whereIn('id', array_column($items, 'product_id'))->get()->keyBy('id');
        $canOverride = $source === OrderSource::Website || ($user?->hasPermission('products.price') ?? false);
        $lines = [];

        foreach ($items as $item) {
            $product = $products[$item['product_id']] ?? null;
            if (! $product) {
                throw new BusinessRuleException('Produit introuvable (#'.$item['product_id'].').');
            }
            $quantity = (int) $item['quantity'];
            if ($quantity < 1) {
                throw new BusinessRuleException('Quantité invalide pour « '.$product->name.' ».');
            }
            $requested = isset($item['unit_price']) && $item['unit_price'] !== '' ? (int) $item['unit_price'] : null;
            $unitPrice = ($canOverride && $requested !== null) ? $requested : $product->price;

            $lines[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_reference' => $product->reference,
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'line_total' => $unitPrice * $quantity,
                'catalog_price' => $product->price,
                'price_overridden' => $source !== OrderSource::Website && $unitPrice !== $product->price,
            ];
        }

        return $lines;
    }

    /** Retrouve le client (id, uuid hors connexion, téléphone) ou le crée. */
    public function resolveCustomer(array $data, ?int $storeId = null): ?Customer
    {
        if (! empty($data['customer_id'])) {
            return Customer::findOrFail($data['customer_id']);
        }
        $info = $data['customer'] ?? null;
        if (! $info || empty($info['name'])) {
            return null;
        }

        $phone = Customer::normalizePhone($info['phone'] ?? null);
        $customer = null;
        if (! empty($info['uuid'])) {
            $customer = Customer::where('uuid', $info['uuid'])->first();
        }
        if (! $customer && ! empty($info['website_customer_id'])) {
            $customer = Customer::where('website_customer_id', $info['website_customer_id'])->first();
        }
        if (! $customer && $phone) {
            $customer = Customer::where('phone', $phone)->first();
        }

        if ($customer) {
            $customer->fill(array_filter([
                'email' => $customer->email ? null : ($info['email'] ?? null),
                'address' => $customer->address ? null : ($info['address'] ?? null),
                'city' => $customer->city ? null : ($info['city'] ?? null),
            ]))->save();

            return $customer;
        }

        return Customer::create([
            'uuid' => $info['uuid'] ?? (string) Str::uuid(),
            'name' => $info['name'],
            'phone' => $phone,
            'email' => $info['email'] ?? null,
            'address' => $info['address'] ?? null,
            'city' => $info['city'] ?? null,
            'store_id' => $storeId,
            'website_customer_id' => $info['website_customer_id'] ?? null,
        ]);
    }
}
