<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use BelongsToStore;

    /** Statuts qui n'entrent pas dans le chiffre d'affaires */
    public const EXCLUDED_FROM_SALES = ['annulee', 'retournee'];

    protected $fillable = [
        'number', 'customer_id', 'store_id', 'user_id', 'source', 'external_reference', 'client_uuid',
        'status', 'requires_delivery', 'subtotal', 'discount', 'delivery_fee', 'total', 'amount_paid',
        'payment_method', 'notes', 'ordered_at', 'delivered_at', 'cancelled_at', 'returned_at', 'status_reason',
    ];

    protected function casts(): array
    {
        return [
            'requires_delivery' => 'boolean',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'ordered_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'returned_at' => 'datetime',
        ];
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

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->latest();
    }

    public function statusLabel(): string
    {
        return config("gesma.order_statuses.{$this->status}", $this->status);
    }

    public function sourceLabel(): string
    {
        return config("gesma.order_sources.{$this->source}", $this->source);
    }

    public function allowedTransitions(): array
    {
        return config("gesma.order_transitions.{$this->status}", []);
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, $this->allowedTransitions(), true);
    }

    public function balance(): float
    {
        return max(0, (float) $this->total - (float) $this->amount_paid);
    }

    /** Commandes comptées dans les ventes (hors annulations et retours) */
    public function scopeCountedAsSale(Builder $query): Builder
    {
        return $query->whereNotIn('orders.status', self::EXCLUDED_FROM_SALES);
    }

    public function scopeBetween(Builder $query, $from, $to): Builder
    {
        return $query->whereBetween('orders.ordered_at', [$from, $to]);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        return $query->where(fn ($q) => $q->where('orders.number', 'like', "%{$term}%")
            ->orWhere('orders.external_reference', 'like', "%{$term}%")
            ->orWhereHas('customer', fn ($c) => $c->search($term)));
    }
}
