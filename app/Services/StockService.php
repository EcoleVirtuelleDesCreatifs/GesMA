<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\Sequence;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class StockService
{
    /** Retourne la ligne de stock (créée si besoin) verrouillée pour mise à jour */
    public function lockedStock(int $productId, int $storeId): Stock
    {
        Stock::query()->insertOrIgnore([
            'product_id' => $productId,
            'store_id' => $storeId,
            'quantity' => 0,
            'alert_threshold' => config('gesma.default_low_stock_threshold', 3),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Stock::where('product_id', $productId)->where('store_id', $storeId)->lockForUpdate()->firstOrFail();
    }

    public function available(int $productId, int $storeId): int
    {
        return (int) Stock::where('product_id', $productId)->where('store_id', $storeId)->value('quantity');
    }

    /**
     * Applique une variation de stock et enregistre le mouvement.
     *
     * @param  int  $delta  positif = entrée, négatif = sortie
     */
    public function move(
        int $productId,
        int $storeId,
        int $delta,
        string $type,
        ?string $reason = null,
        ?Model $reference = null,
        ?User $user = null,
        bool $allowNegative = false,
    ): StockMovement {
        if ($delta === 0) {
            throw new BusinessException('La quantité doit être différente de zéro.');
        }

        return DB::transaction(function () use ($productId, $storeId, $delta, $type, $reason, $reference, $user, $allowNegative) {
            $stock = $this->lockedStock($productId, $storeId);
            $before = $stock->quantity;
            $after = $before + $delta;

            if ($after < 0 && ! $allowNegative) {
                $product = Product::find($productId);
                throw new BusinessException(sprintf(
                    'Stock insuffisant pour « %s » : %d disponible(s), %d demandé(s).',
                    $product?->name ?? '#'.$productId, $before, abs($delta)
                ));
            }

            $stock->update(['quantity' => $after]);

            return StockMovement::create([
                'product_id' => $productId,
                'store_id' => $storeId,
                'type' => $type,
                'quantity' => $delta,
                'quantity_before' => $before,
                'quantity_after' => $after,
                'reason' => $reason,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'user_id' => $user?->id ?? auth()->id(),
            ]);
        });
    }

    /** Correction d'inventaire : fixe la quantité réelle constatée (action sensible) */
    public function correct(Product $product, Store $store, int $newQuantity, string $reason, User $user): ?StockMovement
    {
        if ($newQuantity < 0) {
            throw new BusinessException('La quantité corrigée ne peut pas être négative.');
        }

        return DB::transaction(function () use ($product, $store, $newQuantity, $reason, $user) {
            $stock = $this->lockedStock($product->id, $store->id);
            $delta = $newQuantity - $stock->quantity;
            if ($delta === 0) {
                return null;
            }

            $movement = $this->move($product->id, $store->id, $delta, 'correction', $reason, null, $user);

            ActivityLog::record('stock.correction', sprintf(
                'Correction du stock de « %s » (%s) : %d → %d',
                $product->name, $store->name, $movement->quantity_before, $movement->quantity_after
            ), $product, [
                'magasin' => $store->name,
                'avant' => $movement->quantity_before,
                'apres' => $movement->quantity_after,
                'motif' => $reason,
            ], $store->id);

            return $movement;
        });
    }

    /** Transfert entre deux magasins : trace + mise à jour des deux stocks */
    public function transfer(Product $product, Store $from, Store $to, int $quantity, ?string $notes, User $user): StockTransfer
    {
        if ($from->is($to)) {
            throw new BusinessException('Le magasin de départ et le magasin d’arrivée doivent être différents.');
        }
        if ($quantity <= 0) {
            throw new BusinessException('La quantité à transférer doit être positive.');
        }

        return DB::transaction(function () use ($product, $from, $to, $quantity, $notes, $user) {
            $transfer = StockTransfer::create([
                'number' => Sequence::next('TRF'),
                'from_store_id' => $from->id,
                'to_store_id' => $to->id,
                'product_id' => $product->id,
                'quantity' => $quantity,
                'notes' => $notes,
                'user_id' => $user->id,
            ]);

            $this->move($product->id, $from->id, -$quantity, 'transfert_sortant', "Vers {$to->name}", $transfer, $user);
            $this->move($product->id, $to->id, $quantity, 'transfert_entrant', "Depuis {$from->name}", $transfer, $user);

            ActivityLog::record('stock.transfert', sprintf(
                'Transfert %s : %d × « %s » de %s vers %s',
                $transfer->number, $quantity, $product->name, $from->name, $to->name
            ), $transfer, ['notes' => $notes], $from->id);

            return $transfer;
        });
    }

    /** Initialise une ligne de stock à zéro pour chaque magasin (nouveau produit / nouveau magasin) */
    public function ensureStockRows(?Product $product = null, ?Store $store = null): void
    {
        $products = $product ? collect([$product]) : Product::all(['id']);
        $stores = $store ? collect([$store]) : Store::all(['id']);
        $threshold = config('gesma.default_low_stock_threshold', 3);
        $rows = [];
        foreach ($products as $p) {
            foreach ($stores as $s) {
                $rows[] = ['product_id' => $p->id, 'store_id' => $s->id, 'quantity' => 0, 'alert_threshold' => $threshold, 'created_at' => now(), 'updated_at' => now()];
            }
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            Stock::query()->insertOrIgnore($chunk);
        }
    }
}
