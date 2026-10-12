<?php

namespace App\Models;

use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use BelongsToStore, HasFactory;

    protected $fillable = [
        'uuid', 'number', 'type', 'source', 'external_reference', 'customer_id', 'store_id', 'user_id',
        'status', 'subtotal', 'discount', 'delivery_fee', 'total', 'amount_paid', 'payment_method',
        'requires_delivery', 'delivery_address', 'delivery_city', 'notes', 'status_reason',
        'ordered_at', 'delivered_at', 'cancelled_at', 'returned_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => OrderType::class,
            'source' => OrderSource::class,
            'status' => OrderStatus::class,
            'requires_delivery' => 'boolean',
            'ordered_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'returned_at' => 'datetime',
            'subtotal' => 'integer',
            'discount' => 'integer',
            'delivery_fee' => 'integer',
            'total' => 'integer',
            'amount_paid' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'number';
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function delivery(): HasOne
    {
        return $this->hasOne(Delivery::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->latest('id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function balanceDue(): int
    {
        return max(0, $this->total - $this->amount_paid);
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->where('orders.number', 'like', "%{$term}%")
                ->orWhere('orders.external_reference', 'like', "%{$term}%")
                ->orWhereHas('customer', fn ($c) => $c->search($term));
        });
    }

    /** Ventes « valides » : ni annulées, ni retournées. */
    public function scopeValid($query)
    {
        return $query->whereNotIn('orders.status', [OrderStatus::Cancelled->value, OrderStatus::Returned->value]);
    }

    public function scopeBetween($query, $from, $to)
    {
        return $query->whereBetween('orders.ordered_at', [$from, $to]);
    }
}
