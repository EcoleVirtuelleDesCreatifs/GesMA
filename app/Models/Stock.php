<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Stock extends Model
{
    use BelongsToStore;

    protected $fillable = ['product_id', 'store_id', 'quantity', 'low_stock_threshold'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'low_stock_threshold' => 'integer'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function threshold(): int
    {
        return $this->low_stock_threshold ?? $this->product?->low_stock_threshold ?? 0;
    }

    public function isLow(): bool
    {
        return $this->quantity <= $this->threshold();
    }

    /** Stocks dont la quantité est inférieure ou égale au seuil d'alerte. */
    public function scopeLow($query)
    {
        return $query->join('products', 'products.id', '=', 'stocks.product_id')
            ->whereNull('products.deleted_at')
            ->where('products.status', '!=', 'discontinued')
            ->whereRaw('stocks.quantity <= COALESCE(stocks.low_stock_threshold, products.low_stock_threshold)')
            ->select('stocks.*');
    }
}
