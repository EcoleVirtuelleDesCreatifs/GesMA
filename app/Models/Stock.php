<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Stock extends Model
{
    use BelongsToStore;

    protected $fillable = ['product_id', 'store_id', 'quantity', 'alert_threshold'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'alert_threshold' => 'integer'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function isLow(): bool
    {
        return $this->quantity <= $this->alert_threshold;
    }

    public function scopeLow(Builder $query): Builder
    {
        return $query->whereColumn('stocks.quantity', '<=', 'stocks.alert_threshold');
    }
}
