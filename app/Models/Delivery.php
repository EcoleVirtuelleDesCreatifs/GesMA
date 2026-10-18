<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Delivery extends Model
{
    use BelongsToStore;

    protected $fillable = [
        'order_id', 'store_id', 'status', 'address', 'city', 'contact_name', 'contact_phone', 'scheduled_date',
        'driver_id', 'driver_name', 'fee', 'failure_reason', 'departed_at', 'completed_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
            'fee' => 'decimal:2',
            'departed_at' => 'datetime',
            'completed_at' => 'datetime',
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

    public function statusLabel(): string
    {
        return config("gesma.delivery_statuses.{$this->status}", $this->status);
    }

    public function driverLabel(): string
    {
        return $this->driver?->name ?? $this->driver_name ?? '—';
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['preparation', 'en_route', 'echec'], true);
    }
}
