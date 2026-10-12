<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Models\Concerns\HasDocumentLines;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    use HasDocumentLines;

    protected $fillable = [
        'number', 'customer_id', 'store_id', 'user_id', 'order_id', 'quote_id', 'status', 'issued_at', 'due_at',
        'subtotal', 'discount', 'delivery_fee', 'total', 'amount_paid', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'issued_at' => 'date',
            'due_at' => 'date',
            'subtotal' => 'integer',
            'discount' => 'integer',
            'delivery_fee' => 'integer',
            'total' => 'integer',
            'amount_paid' => 'integer',
        ];
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function balanceDue(): int
    {
        return max(0, $this->total - $this->amount_paid);
    }

    public function refreshPaymentStatus(): void
    {
        if ($this->status === InvoiceStatus::Cancelled) {
            return;
        }
        $this->status = match (true) {
            $this->amount_paid >= $this->total => InvoiceStatus::Paid,
            $this->amount_paid > 0 => InvoiceStatus::Partial,
            default => InvoiceStatus::Unpaid,
        };
    }
}
