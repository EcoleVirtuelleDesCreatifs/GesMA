<?php

namespace App\Models;

use App\Enums\SyncStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncOperation extends Model
{
    protected $fillable = [
        'uuid', 'user_id', 'device_id', 'type', 'payload', 'status', 'message', 'result',
        'client_created_at', 'resolved_at', 'resolved_by',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'result' => 'array',
            'status' => SyncStatus::class,
            'client_created_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'order.create' => 'Création de commande',
            'sale.create' => 'Vente directe',
            'expense.create' => 'Dépense',
            'delivery.status' => 'Suivi de livraison',
            'customer.create' => 'Nouveau client',
            default => $this->type,
        };
    }
}
