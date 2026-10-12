<?php

namespace App\Models;

use App\Enums\QuoteStatus;
use App\Models\Concerns\HasDocumentLines;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quote extends Model
{
    use HasDocumentLines;

    protected $fillable = [
        'number', 'customer_id', 'store_id', 'user_id', 'order_id', 'status', 'issued_at', 'valid_until',
        'subtotal', 'discount', 'delivery_fee', 'total', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => QuoteStatus::class,
            'issued_at' => 'date',
            'valid_until' => 'date',
            'subtotal' => 'integer',
            'discount' => 'integer',
            'delivery_fee' => 'integer',
            'total' => 'integer',
        ];
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
