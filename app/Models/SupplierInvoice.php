<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierInvoice extends Model
{
    protected $fillable = [
        'number', 'supplier_id', 'purchase_order_id', 'store_id', 'user_id',
        'invoiced_at', 'due_at', 'total', 'attachment', 'notes',
    ];

    protected function casts(): array
    {
        return ['invoiced_at' => 'date', 'due_at' => 'date', 'total' => 'decimal:2'];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    /** Montant déjà réglé */
    public function paid(): float
    {
        return (float) $this->payments()->sum('amount');
    }

    /** Dette restante sur la facture */
    public function balance(): float
    {
        return max(0, (float) $this->total - $this->paid());
    }

    public function isDue(): bool
    {
        return $this->due_at && $this->due_at->isPast() && $this->balance() > 0;
    }

    public function statusLabel(): string
    {
        $paid = $this->paid();
        if ($paid <= 0) {
            return $this->isDue() ? 'En retard' : 'Non payée';
        }
        if ($this->balance() <= 0) {
            return 'Soldée';
        }

        return $this->isDue() ? 'Partielle (en retard)' : 'Partiellement payée';
    }

    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->whereRaw('total > (SELECT COALESCE(SUM(amount),0) FROM supplier_payments WHERE supplier_invoice_id = supplier_invoices.id)');
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->unpaid()->whereNotNull('due_at')->where('due_at', '<', today());
    }
}
