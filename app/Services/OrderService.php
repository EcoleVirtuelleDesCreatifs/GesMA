<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\Sequence;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(private StockService $stock)
    {
    }

    /**
     * Crée une commande (ou une vente directe) et sort les produits du stock.
     *
     * $data = [
     *   store_id, customer_id | customer => [name, phone, email, address, city, client_uuid],
     *   items => [[product_id, quantity, unit_price?], ...],
     *   discount, payment_method, amount_paid, notes, direct_sale (bool),
     *   delivery => [address, city, contact_name, contact_phone, scheduled_date, driver_id, driver_name, fee] | null,
     *   client_uuid, external_reference, ordered_at
     * ]
     *
     * @param  bool  $allowNegativeStock  vrai pour les opérations déjà réalisées (hors connexion, site web) :
     *                                    la commande est enregistrée et l'écart est signalé.
     * @return array{0: Order, 1: array} [commande, ruptures constatées]
     */
    public function create(array $data, ?User $user, string $source = 'gesma', bool $allowNegativeStock = false, bool $usePayloadPrices = false): array
    {
        if (empty($data['items'])) {
            throw new BusinessException('La commande doit contenir au moins un produit.');
        }

        return DB::transaction(function () use ($data, $user, $source, $allowNegativeStock, $usePayloadPrices) {
            $storeId = (int) $data['store_id'];
            $customer = $this->resolveCustomer($data, $storeId);

            $directSale = (bool) ($data['direct_sale'] ?? false);
            $delivery = $directSale ? null : ($data['delivery'] ?? null);
            $requiresDelivery = ! $directSale && ! empty($delivery);

            // Regroupe les lignes identiques et prépare les prix
            $lines = [];
            foreach ($data['items'] as $item) {
                $productId = (int) $item['product_id'];
                $qty = (int) $item['quantity'];
                if ($qty <= 0) {
                    continue;
                }
                $product = Product::find($productId);
                if (! $product) {
                    throw new BusinessException("Produit introuvable (#{$productId}).");
                }
                $price = $usePayloadPrices && isset($item['unit_price']) ? (float) $item['unit_price'] : (float) $product->price;
                $key = $productId.'|'.$price;
                $lines[$key] ??= ['product' => $product, 'quantity' => 0, 'unit_price' => $price];
                $lines[$key]['quantity'] += $qty;
            }
            if (! $lines) {
                throw new BusinessException('La commande doit contenir au moins un produit.');
            }

            $subtotal = array_sum(array_map(fn ($l) => $l['quantity'] * $l['unit_price'], $lines));
            $discount = min(max(0, (float) ($data['discount'] ?? 0)), $subtotal);
            $fee = $requiresDelivery ? max(0, (float) ($delivery['fee'] ?? 0)) : 0;
            $total = $subtotal - $discount + $fee;
            $orderedAt = isset($data['ordered_at']) ? Carbon::parse($data['ordered_at']) : now();

            $order = Order::create([
                'number' => Sequence::next('CMD'),
                'customer_id' => $customer?->id,
                'store_id' => $storeId,
                'user_id' => $user?->id,
                'source' => $source,
                'external_reference' => $data['external_reference'] ?? null,
                'client_uuid' => $data['client_uuid'] ?? null,
                'status' => $directSale ? 'livree' : 'en_preparation',
                'requires_delivery' => $requiresDelivery,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'delivery_fee' => $fee,
                'total' => $total,
                'amount_paid' => min(max(0, (float) ($data['amount_paid'] ?? ($directSale ? $total : 0))), $total),
                'payment_method' => $data['payment_method'] ?? null,
                'notes' => $data['notes'] ?? null,
                'ordered_at' => $orderedAt,
                'delivered_at' => $directSale ? $orderedAt : null,
            ]);

            $shortages = [];
            foreach ($lines as $line) {
                $product = $line['product'];
                $order->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_reference' => $product->reference,
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'total' => $line['quantity'] * $line['unit_price'],
                ]);

                $movement = $this->stock->move(
                    $product->id, $storeId, -$line['quantity'], 'vente',
                    "Commande {$order->number}", $order, $user, $allowNegativeStock
                );
                if ($movement->quantity_after < 0) {
                    $shortages[] = [
                        'product_id' => $product->id,
                        'product' => $product->name,
                        'reference' => $product->reference,
                        'demande' => $line['quantity'],
                        'disponible' => $movement->quantity_before,
                    ];
                }
            }

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'from_status' => null,
                'to_status' => $order->status,
                'reason' => $directSale ? 'Vente directe en magasin' : 'Création de la commande',
                'user_id' => $user?->id,
            ]);

            if ($requiresDelivery) {
                Delivery::create([
                    'order_id' => $order->id,
                    'store_id' => $storeId,
                    'status' => 'preparation',
                    'address' => $delivery['address'] ?? $customer?->address,
                    'city' => $delivery['city'] ?? $customer?->city,
                    'contact_name' => $delivery['contact_name'] ?? $customer?->name,
                    'contact_phone' => $delivery['contact_phone'] ?? $customer?->phone,
                    'scheduled_date' => $delivery['scheduled_date'] ?? null,
                    'driver_id' => $delivery['driver_id'] ?? null,
                    'driver_name' => $delivery['driver_name'] ?? null,
                    'fee' => $fee,
                    'notes' => $delivery['notes'] ?? null,
                ]);
            }

            return [$order->load('items', 'customer', 'delivery'), $shortages];
        });
    }

    private function resolveCustomer(array $data, int $storeId): ?Customer
    {
        if (! empty($data['customer_id'])) {
            return Customer::findOrFail($data['customer_id']);
        }

        $c = $data['customer'] ?? null;
        if (! $c || empty($c['name'])) {
            return null;
        }

        // Client créé hors connexion : identifiant de l'appareil
        if (! empty($c['client_uuid'])) {
            $existing = Customer::where('client_uuid', $c['client_uuid'])->first();
            if ($existing) {
                return $existing;
            }
        }

        // Évite les doublons : même téléphone ou même e-mail
        if (! empty($c['phone'])) {
            $existing = Customer::where('phone', $c['phone'])->first();
        }
        if (empty($existing) && ! empty($c['email'])) {
            $existing = Customer::where('email', $c['email'])->first();
        }
        if (! empty($existing)) {
            $existing->fill(array_filter([
                'address' => $c['address'] ?? null,
                'city' => $c['city'] ?? null,
                'email' => $existing->email ?: ($c['email'] ?? null),
            ]))->save();

            return $existing;
        }

        return Customer::create([
            'name' => $c['name'],
            'phone' => $c['phone'] ?? null,
            'email' => $c['email'] ?? null,
            'address' => $c['address'] ?? null,
            'city' => $c['city'] ?? null,
            'store_id' => $storeId,
            'client_uuid' => $c['client_uuid'] ?? null,
        ]);
    }

    /**
     * Change le statut d'une commande, remet en stock en cas d'annulation ou de retour
     * et met à jour la livraison associée.
     */
    public function changeStatus(Order $order, string $status, ?User $user, ?string $reason = null): Order
    {
        if ($order->status === $status) {
            return $order;
        }
        if (! array_key_exists($status, config('gesma.order_statuses'))) {
            throw new BusinessException('Statut inconnu.');
        }
        if (! $order->canTransitionTo($status)) {
            throw new BusinessException(sprintf(
                'Impossible de passer la commande %s de « %s » à « %s ».',
                $order->number, $order->statusLabel(), config("gesma.order_statuses.{$status}")
            ));
        }
        if (in_array($status, ['annulee', 'retournee', 'non_livree'], true) && blank($reason)) {
            throw new BusinessException('Merci d’indiquer un motif.');
        }

        return DB::transaction(function () use ($order, $status, $user, $reason) {
            $from = $order->status;
            $now = now();

            if (in_array($status, ['annulee', 'retournee'], true)) {
                foreach ($order->items as $item) {
                    if ($item->product_id) {
                        $this->stock->move(
                            $item->product_id, $order->store_id, $item->quantity, 'retour',
                            ($status === 'annulee' ? 'Annulation ' : 'Retour ').$order->number.' : '.$reason,
                            $order, $user
                        );
                    }
                }
            }

            $order->status = $status;
            $order->status_reason = $reason;
            match ($status) {
                'livree' => $order->delivered_at = $now,
                'annulee' => $order->cancelled_at = $now,
                'retournee' => $order->returned_at = $now,
                default => null,
            };
            $order->save();

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'from_status' => $from,
                'to_status' => $status,
                'reason' => $reason,
                'user_id' => $user?->id,
            ]);

            if ($delivery = $order->delivery) {
                $deliveryStatus = array_search($status, config('gesma.delivery_to_order_status'), true);
                if ($deliveryStatus) {
                    $delivery->status = $deliveryStatus;
                    if ($deliveryStatus === 'en_route') {
                        $delivery->departed_at = $now;
                        $delivery->failure_reason = null;
                    }
                    if ($deliveryStatus === 'livree') {
                        $delivery->completed_at = $now;
                    }
                    if (in_array($deliveryStatus, ['echec', 'retour', 'annulee'], true)) {
                        $delivery->failure_reason = $reason;
                    }
                    $delivery->save();
                }
            }

            if ($status === 'annulee') {
                ActivityLog::record('commande.annulation', "Annulation de la commande {$order->number}", $order, [
                    'ancien_statut' => $from, 'motif' => $reason, 'montant' => (float) $order->total,
                ]);
            }
            if ($status === 'retournee') {
                ActivityLog::record('commande.retour', "Retour de la commande {$order->number}", $order, [
                    'ancien_statut' => $from, 'motif' => $reason, 'montant' => (float) $order->total,
                ]);
            }

            return $order->refresh();
        });
    }

    /** Met à jour une livraison via son statut (préparation, départ, livrée, échec, retour) */
    public function changeDeliveryStatus(Delivery $delivery, string $deliveryStatus, ?User $user, ?string $reason = null): Delivery
    {
        $orderStatus = config("gesma.delivery_to_order_status.{$deliveryStatus}");
        if (! $orderStatus) {
            throw new BusinessException('Statut de livraison inconnu.');
        }
        $this->changeStatus($delivery->order, $orderStatus, $user, $reason);

        return $delivery->refresh();
    }

    /** Statuts de livraison accessibles depuis le statut actuel */
    public function allowedDeliveryStatuses(Delivery $delivery): array
    {
        $map = config('gesma.delivery_to_order_status');
        $allowed = $delivery->order->allowedTransitions();

        return array_values(array_filter(array_keys($map), fn ($s) => in_array($map[$s], $allowed, true)));
    }

    public function recordPayment(Order $order, float $amount, ?string $method): Order
    {
        if ($amount <= 0) {
            throw new BusinessException('Le montant doit être positif.');
        }
        $order->amount_paid = min((float) $order->total, (float) $order->amount_paid + $amount);
        if ($method) {
            $order->payment_method = $method;
        }
        $order->save();

        return $order;
    }
}
