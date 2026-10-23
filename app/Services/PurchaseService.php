<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\ActivityLog;
use App\Models\Expense;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\Sequence;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Models\SupplierReturn;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PurchaseService
{
    public function __construct(private StockService $stock) {}

    /** Crée une commande d'achat en brouillon */
    public function createOrder(array $data, User $user): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $user) {
            $order = PurchaseOrder::create([
                'number' => Sequence::next('BC'),
                'supplier_id' => $data['supplier_id'],
                'store_id' => $data['store_id'],
                'user_id' => $user->id,
                'status' => 'brouillon',
                'ordered_at' => $data['ordered_at'] ?? today(),
                'expected_at' => $data['expected_at'] ?? null,
                'discount' => $data['discount'] ?? 0,
                'tax_rate' => $data['tax_rate'] ?? 0,
                'extra_fees' => $data['extra_fees'] ?? 0,
                'notes' => $data['notes'] ?? null,
            ]);

            $subtotal = 0;
            foreach ($data['items'] as $line) {
                $qty = (int) $line['quantity'];
                $price = (float) $line['unit_price'];
                $discount = (float) ($line['discount'] ?? 0);
                $total = max(0, $qty * $price - $discount);
                $order->items()->create([
                    'product_id' => $line['product_id'],
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'discount' => $discount,
                    'total' => $total,
                ]);
                $subtotal += $total;
            }

            $net = max(0, $subtotal - (float) $order->discount);
            $tax = round($net * (float) $order->tax_rate / 100, 2);
            $order->update([
                'subtotal' => $subtotal,
                'tax_amount' => $tax,
                'total' => $net + $tax + (float) $order->extra_fees,
            ]);

            ActivityLog::record('achat.creation', "Commande d'achat {$order->number} créée", $order, [
                'fournisseur' => $order->supplier->name,
                'total' => $order->total,
            ], $order->store_id);

            return $order;
        });
    }

    /** Changement de statut contrôlé (soumission, validation, envoi, annulation) */
    public function changeStatus(PurchaseOrder $order, string $status, User $user): PurchaseOrder
    {
        if (! $order->canTransitionTo($status)) {
            throw new BusinessException(sprintf(
                'Transition impossible : %s → %s.',
                $order->statusLabel(), config("gesma.purchase_statuses.{$status}", $status)
            ));
        }
        if ($status === 'annulee' && $order->receipts()->where('status', 'validee')->exists()) {
            throw new BusinessException('Impossible d’annuler : des réceptions ont déjà été validées.');
        }

        return DB::transaction(function () use ($order, $status, $user) {
            // Verrou pour éviter les doubles validations
            $order = PurchaseOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (! $order->canTransitionTo($status)) {
                throw new BusinessException('La commande a déjà changé de statut.');
            }
            $order->update(['status' => $status]);

            ActivityLog::record('achat.statut', "Commande {$order->number} : {$order->statusLabel()}", $order, [], $order->store_id);

            return $order;
        });
    }

    /**
     * Enregistre et valide une réception : entre en stock les quantités acceptées
     * et met à jour le suivi de la commande (partielle / réceptionnée).
     */
    public function recordReceipt(PurchaseOrder $order, array $lines, int $storeId, ?string $notes, User $user): GoodsReceipt
    {
        if (! in_array($order->status, ['envoyee', 'partielle', 'validee'], true)) {
            throw new BusinessException('Seules les commandes validées ou envoyées peuvent être réceptionnées.');
        }

        return DB::transaction(function () use ($order, $lines, $storeId, $notes, $user) {
            $order = PurchaseOrder::whereKey($order->id)->lockForUpdate()->with('items')->firstOrFail();

            $receipt = GoodsReceipt::create([
                'number' => Sequence::next('REC'),
                'purchase_order_id' => $order->id,
                'supplier_id' => $order->supplier_id,
                'store_id' => $storeId,
                'user_id' => $user->id,
                'status' => 'validee',
                'validated_at' => now(),
                'notes' => $notes,
            ]);

            $receivedSomething = false;
            foreach ($order->items as $item) {
                $line = $lines[$item->id] ?? [];
                $received = (int) ($line['received'] ?? 0);
                $rejected = (int) ($line['rejected'] ?? 0);
                if ($received <= 0 && $rejected <= 0) {
                    continue;
                }
                if ($received + $rejected > $item->remaining()) {
                    throw new BusinessException(sprintf(
                        'Réception supérieure au reste attendu pour « %s » (reste : %d).',
                        $item->product->name, $item->remaining()
                    ));
                }

                $receipt->items()->create([
                    'purchase_order_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'quantity_expected' => $item->remaining(),
                    'quantity_received' => $received,
                    'quantity_rejected' => $rejected,
                    'defect_reason' => $line['defect_reason'] ?? null,
                ]);

                if ($received > 0) {
                    $receivedSomething = true;
                    $item->increment('quantity_received', $received);
                    // Entrée en stock automatique, traçable
                    $this->stock->move(
                        $item->product_id, $storeId, $received,
                        'approvisionnement', "Réception {$receipt->number} (commande {$order->number})",
                        $receipt, $user
                    );
                    // Mémorise le dernier achat sur la fiche produit-fournisseur
                    $order->supplier->products()->updateExistingPivot($item->product_id, [
                        'last_purchased_at' => today(),
                    ]);
                }
            }

            if (! $receivedSomething && $receipt->items()->where('quantity_rejected', '>', 0)->doesntExist()) {
                throw new BusinessException('Aucune quantité reçue ou refusée saisie.');
            }

            // Clôture si tout est réceptionné
            $order->refresh();
            $order->load('items');
            $fully = $order->items->every(fn ($i) => $i->remaining() === 0);
            if ($fully && $order->canTransitionTo('receptionnee')) {
                $order->update(['status' => 'receptionnee']);
            } elseif ($order->status === 'envoyee' || $order->status === 'validee') {
                $order->update(['status' => 'partielle']);
            }

            ActivityLog::record('achat.reception', "Réception {$receipt->number} validée (commande {$order->number})", $receipt, [
                'magasin' => $receipt->store->name,
            ], $storeId);

            return $receipt;
        });
    }

    /** Enregistre une facture fournisseur */
    public function createInvoice(array $data, User $user): SupplierInvoice
    {
        return DB::transaction(function () use ($data, $user) {
            $invoice = SupplierInvoice::create($data + ['user_id' => $user->id]);

            ActivityLog::record('achat.facture', "Facture fournisseur {$invoice->number} enregistrée (".money($invoice->total).')', $invoice, [], $invoice->store_id);

            return $invoice;
        });
    }

    /**
     * Enregistre un règlement de facture et crée l'écriture de trésorerie
     * (dépense « Achat de marchandises ») — une seule écriture par paiement.
     */
    public function recordPayment(SupplierInvoice $invoice, array $data, User $user): SupplierPayment
    {
        return DB::transaction(function () use ($invoice, $data, $user) {
            $invoice = SupplierInvoice::whereKey($invoice->id)->lockForUpdate()->with('payments')->firstOrFail();
            $amount = (float) $data['amount'];
            $balance = $invoice->balance();
            if ($amount <= 0) {
                throw new BusinessException('Le montant du règlement doit être positif.');
            }
            if ($amount > $balance) {
                throw new BusinessException(sprintf(
                    'Le règlement (%s) dépasse le solde restant de la facture (%s).',
                    money($amount), money($balance)
                ));
            }

            $storeId = $data['store_id'] ?? $invoice->store_id ?? $invoice->purchaseOrder?->store_id ?? $user->store_id;
            if (! $storeId) {
                throw new BusinessException('Indiquez le magasin payeur pour l’écriture de trésorerie.');
            }

            // Écriture trésorerie liée : la dépense porte la référence du paiement
            $expense = Expense::create([
                'store_id' => $storeId,
                'user_id' => $user->id,
                'spent_at' => $data['paid_at'],
                'amount' => $amount,
                'category' => 'achat_marchandises',
                'description' => sprintf('Règlement fournisseur %s — facture %s', $invoice->supplier->name, $invoice->number),
            ]);

            $payment = SupplierPayment::create([
                'supplier_invoice_id' => $invoice->id,
                'supplier_id' => $invoice->supplier_id,
                'store_id' => $expense->store_id,
                'user_id' => $user->id,
                'paid_at' => $data['paid_at'],
                'amount' => $amount,
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
                'attachment' => $data['attachment'] ?? null,
                'expense_id' => $expense->id,
                'notes' => $data['notes'] ?? null,
            ]);

            ActivityLog::record('achat.paiement', sprintf(
                'Règlement de %s sur la facture %s (%s)',
                money($amount), $invoice->number, $invoice->supplier->name
            ), $payment, ['reste' => $invoice->fresh()->balance()], $payment->store_id);

            return $payment;
        });
    }

    /** Déclare un retour fournisseur ; sort du stock les produits déjà réceptionnés */
    public function recordReturn(Supplier $supplier, array $data, array $lines, User $user): SupplierReturn
    {
        return DB::transaction(function () use ($supplier, $data, $lines, $user) {
            $return = SupplierReturn::create([
                'number' => Sequence::next('RET'),
                'supplier_id' => $supplier->id,
                'purchase_order_id' => $data['purchase_order_id'] ?? null,
                'goods_receipt_id' => $data['goods_receipt_id'] ?? null,
                'store_id' => $data['store_id'],
                'user_id' => $user->id,
                'returned_at' => $data['returned_at'] ?? today(),
                'status' => 'declaree',
                'decision' => $data['decision'] ?? 'avoir',
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($lines as $line) {
                $qty = (int) ($line['quantity'] ?? 0);
                if ($qty <= 0) {
                    continue;
                }
                $fromStock = ! empty($line['from_stock']);
                $item = $return->items()->create([
                    'product_id' => $line['product_id'],
                    'quantity' => $qty,
                    'from_stock' => $fromStock,
                    'reason' => $line['reason'] ?? null,
                ]);

                // Produit déjà entré en stock -> sortie de stock unique
                if ($fromStock) {
                    $this->stock->move(
                        $item->product_id, $return->store_id, -$qty,
                        'retour_fournisseur', "Retour {$return->number} — {$supplier->name}",
                        $return, $user
                    );
                }
            }

            if ($return->items()->count() === 0) {
                throw new BusinessException('Ajoutez au moins un produit au retour.');
            }

            ActivityLog::record('achat.retour', "Retour fournisseur {$return->number} déclaré", $return, [], $return->store_id);

            return $return;
        });
    }
}
