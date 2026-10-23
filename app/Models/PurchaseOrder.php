<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    protected $fillable = [
        'number', 'supplier_id', 'store_id', 'user_id', 'status',
        'ordered_at', 'expected_at', 'subtotal', 'discount',
        'tax_rate', 'tax_amount', 'extra_fees', 'total', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'ordered_at' => 'date',
            'expected_at' => 'date',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'extra_fees' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
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
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(SupplierInvoice::class);
    }

    public function statusLabel(): string
    {
        return config("gesma.purchase_statuses.{$this->status}", $this->status);
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, config("gesma.purchase_transitions.{$this->status}", []), true);
    }

    public function isOpen(): bool
    {
        return ! in_array($this->status, ['receptionnee', 'annulee'], true);
    }

    /** Quantité restant à recevoir (toutes lignes confondues) */
    public function remainingQuantity(): int
    {
        return (int) $this->items->sum(fn ($i) => max(0, $i->quantity - $i->quantity_received));
    }

    public function isLate(): bool
    {
        return $this->expected_at && $this->expected_at->isPast() && $this->isOpen();
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', ['receptionnee', 'annulee']);
    }
}
