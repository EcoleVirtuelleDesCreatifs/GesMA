<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Document;
use App\Models\Order;
use App\Models\Product;
use App\Models\Sequence;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Devis et factures */
class DocumentService
{
    /**
     * $data = [customer_id, store_id, issued_at, valid_until, discount, delivery_fee, amount_paid, notes,
     *          items => [[product_id?, description?, quantity, unit_price], ...]]
     */
    public function create(string $type, array $data, User $user): Document
    {
        if (! array_key_exists($type, Document::TYPES)) {
            throw new BusinessException('Type de document inconnu.');
        }

        return DB::transaction(function () use ($type, $data, $user) {
            $items = [];
            foreach ($data['items'] ?? [] as $item) {
                $qty = (int) ($item['quantity'] ?? 0);
                if ($qty <= 0) {
                    continue;
                }
                $product = ! empty($item['product_id']) ? Product::find($item['product_id']) : null;
                $description = trim((string) ($item['description'] ?? '')) ?: $product?->name;
                if (! $description) {
                    continue;
                }
                $price = isset($item['unit_price']) && $item['unit_price'] !== '' ? (float) $item['unit_price'] : (float) ($product?->price ?? 0);
                $items[] = [
                    'product_id' => $product?->id,
                    'description' => $description,
                    'product_reference' => $product?->reference,
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'total' => $qty * $price,
                ];
            }
            if (! $items) {
                throw new BusinessException('Le document doit contenir au moins une ligne.');
            }

            $subtotal = array_sum(array_column($items, 'total'));
            $discount = min(max(0, (float) ($data['discount'] ?? 0)), $subtotal);
            $fee = max(0, (float) ($data['delivery_fee'] ?? 0));
            $total = $subtotal - $discount + $fee;

            $document = Document::create([
                'type' => $type,
                'number' => Sequence::next($type === 'devis' ? 'DEV' : 'FAC', 4),
                'customer_id' => $data['customer_id'],
                'store_id' => $data['store_id'],
                'order_id' => $data['order_id'] ?? null,
                'source_document_id' => $data['source_document_id'] ?? null,
                'user_id' => $user->id,
                'issued_at' => $data['issued_at'] ?? now()->toDateString(),
                'valid_until' => $type === 'devis' ? ($data['valid_until'] ?? now()->addDays(30)->toDateString()) : null,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'delivery_fee' => $fee,
                'total' => $total,
                'amount_paid' => min(max(0, (float) ($data['amount_paid'] ?? 0)), $total),
                'notes' => $data['notes'] ?? null,
            ]);
            $document->items()->createMany($items);

            return $document->load('items');
        });
    }

    /** Facture à partir d'une commande (une seule facture par commande) */
    public function invoiceFromOrder(Order $order, User $user): Document
    {
        $existing = $order->documents()->where('type', 'facture')->first();
        if ($existing) {
            return $existing;
        }
        if (! $order->customer_id) {
            throw new BusinessException('Associez un client à la commande avant de générer la facture.');
        }
        if (in_array($order->status, ['annulee', 'retournee'], true)) {
            throw new BusinessException('Impossible de facturer une commande annulée ou retournée.');
        }

        return $this->create('facture', [
            'customer_id' => $order->customer_id,
            'store_id' => $order->store_id,
            'order_id' => $order->id,
            'discount' => $order->discount,
            'delivery_fee' => $order->delivery_fee,
            'amount_paid' => $order->amount_paid,
            'notes' => "Commande {$order->number}",
            'items' => $order->items->map(fn ($i) => [
                'product_id' => $i->product_id,
                'description' => $i->product_name,
                'quantity' => $i->quantity,
                'unit_price' => $i->unit_price,
            ])->all(),
        ], $user);
    }

    /** Transforme un devis accepté en facture */
    public function convertQuote(Document $quote, User $user): Document
    {
        if (! $quote->isQuote()) {
            throw new BusinessException('Seul un devis peut être transformé en facture.');
        }
        $existing = Document::where('source_document_id', $quote->id)->where('type', 'facture')->first();
        if ($existing) {
            return $existing;
        }

        return $this->create('facture', [
            'customer_id' => $quote->customer_id,
            'store_id' => $quote->store_id,
            'source_document_id' => $quote->id,
            'discount' => $quote->discount,
            'delivery_fee' => $quote->delivery_fee,
            'notes' => trim("Selon devis {$quote->number}. ".($quote->notes ?? '')),
            'items' => $quote->items->map(fn ($i) => [
                'product_id' => $i->product_id,
                'description' => $i->description,
                'quantity' => $i->quantity,
                'unit_price' => $i->unit_price,
            ])->all(),
        ], $user);
    }
}
