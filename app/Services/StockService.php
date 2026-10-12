<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class StockService
{
    public function __construct(private NumberGenerator $numbers) {}

    /**
     * Applique une variation de stock et trace le mouvement.
     * $delta est positif pour une entrée, négatif pour une sortie.
     */
    public function move(
        int $productId,
        int $storeId,
        int $delta,
        StockMovementType $type,
        ?string $reason = null,
        ?Model $source = null,
        ?User $user = null,
        bool $allowNegative = false,
    ): StockMovement {
        return DB::transaction(function () use ($productId, $storeId, $delta, $type, $reason, $source, $user, $allowNegative) {
            $stock = $this->lockedStock($productId, $storeId);
            $newQuantity = $stock->quantity + $delta;

            if ($delta < 0 && $newQuantity < 0 && ! $allowNegative) {
                $product = Product::withTrashed()->find($productId);
                throw new InsufficientStockException([[
                    'product_id' => $productId,
                    'product' => $product?->name ?? '#'.$productId,
                    'requested' => -$delta,
                    'available' => $stock->quantity,
                ]]);
            }

            $stock->update(['quantity' => $newQuantity]);

            return StockMovement::create([
                'product_id' => $productId,
                'store_id' => $storeId,
                'type' => $type,
                'quantity' => $delta,
                'balance_after' => $newQuantity,
                'reason' => $reason,
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
                'user_id' => $user?->id ?? auth()->id(),
            ]);
        });
    }

    /** Correction d'inventaire : fixe la quantité réelle constatée (action sensible, historisée). */
    public function correct(Product $product, int $storeId, int $countedQuantity, string $reason, User $user): ?StockMovement
    {
        return DB::transaction(function () use ($product, $storeId, $countedQuantity, $reason, $user) {
            $stock = $this->lockedStock($product->id, $storeId);
            $delta = $countedQuantity - $stock->quantity;
            if ($delta === 0) {
                return null;
            }
            $movement = $this->move($product->id, $storeId, $delta, StockMovementType::Adjustment, $reason, null, $user, true);
            AuditLogger::log('stock.correction', sprintf(
                'Correction de stock « %s » : %d → %d (%s)', $product->name, $stock->quantity, $countedQuantity, $reason
            ), $product, ['store_id' => $storeId, 'before' => $stock->quantity, 'after' => $countedQuantity], $user, $storeId);

            return $movement;
        });
    }

    /**
     * Vérifie la disponibilité de plusieurs lignes dans un magasin.
     *
     * @param  array<int, int>  $quantities  product_id => quantité
     */
    public function assertAvailable(int $storeId, array $quantities): void
    {
        $stocks = Stock::where('store_id', $storeId)->whereIn('product_id', array_keys($quantities))->pluck('quantity', 'product_id');
        $shortages = [];
        foreach ($quantities as $productId => $qty) {
            $available = (int) ($stocks[$productId] ?? 0);
            if ($qty > $available) {
                $shortages[] = [
                    'product_id' => $productId,
                    'product' => Product::withTrashed()->find($productId)?->name ?? '#'.$productId,
                    'requested' => $qty,
                    'available' => $available,
                ];
            }
        }
        if ($shortages) {
            throw new InsufficientStockException($shortages);
        }
    }

    /** @param  array<int, array{product_id:int, quantity:int}>  $items */
    public function transfer(int $fromStoreId, int $toStoreId, array $items, User $user, ?string $notes = null): StockTransfer
    {
        if ($fromStoreId === $toStoreId) {
            throw new BusinessRuleException('Le magasin de départ et le magasin de destination doivent être différents.');
        }

        return DB::transaction(function () use ($fromStoreId, $toStoreId, $items, $user, $notes) {
            $quantities = [];
            foreach ($items as $item) {
                $quantities[$item['product_id']] = ($quantities[$item['product_id']] ?? 0) + (int) $item['quantity'];
            }
            $this->assertAvailable($fromStoreId, $quantities);

            $transfer = StockTransfer::create([
                'number' => $this->numbers->next(NumberGenerator::TRANSFER),
                'from_store_id' => $fromStoreId,
                'to_store_id' => $toStoreId,
                'user_id' => $user->id,
                'notes' => $notes,
                'transferred_at' => now(),
            ]);

            foreach ($quantities as $productId => $qty) {
                $transfer->items()->create(['product_id' => $productId, 'quantity' => $qty]);
                $this->move($productId, $fromStoreId, -$qty, StockMovementType::TransferOut, 'Transfert '.$transfer->number, $transfer, $user);
                $this->move($productId, $toStoreId, $qty, StockMovementType::TransferIn, 'Transfert '.$transfer->number, $transfer, $user);
            }

            AuditLogger::log('stock.transfer', 'Transfert de stock '.$transfer->number, $transfer, ['items' => $quantities], $user, $fromStoreId);

            return $transfer;
        });
    }

    private function lockedStock(int $productId, int $storeId): Stock
    {
        $stock = Stock::where('product_id', $productId)->where('store_id', $storeId)->lockForUpdate()->first();

        return $stock ?? Stock::create(['product_id' => $productId, 'store_id' => $storeId, 'quantity' => 0]);
    }
}
