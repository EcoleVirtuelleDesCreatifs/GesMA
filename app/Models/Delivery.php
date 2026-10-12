<?php

namespace App\Models;

use App\Enums\DeliveryStatus;
use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Delivery extends Model
{
    use BelongsToStore;

    protected $fillable = [
        'order_id', 'store_id', 'status', 'address', 'city', 'contact_name', 'contact_phone',
        'scheduled_date', 'driver_id', 'driver_name', 'fee', 'failure_reason', 'notes', 'departed_at', 'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => DeliveryStatus::class,
            'scheduled_date' => 'date',
            'departed_at' => 'datetime',
            'delivered_at' => 'datetime',
            'fee' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function driverLabel(): ?string
    {
        return $this->driver?->name ?? $this->driver_name;
    }
}
