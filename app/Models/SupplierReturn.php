<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierReturn extends Model
{
    protected $fillable = [
        'number', 'supplier_id', 'purchase_order_id', 'goods_receipt_id',
        'store_id', 'user_id', 'returned_at', 'status', 'decision', 'notes',
    ];

    protected function casts(): array
    {
        return ['returned_at' => 'date'];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SupplierReturnItem::class);
    }

    public function statusLabel(): string
    {
        return config("gesma.supplier_return_statuses.{$this->status}", $this->status);
    }

    public function decisionLabel(): string
    {
        return config("gesma.supplier_return_decisions.{$this->decision}", $this->decision);
    }
}
