<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockTransfer extends Model
{
    protected $fillable = ['number', 'from_store_id', 'to_store_id', 'user_id', 'notes', 'transferred_at'];

    protected function casts(): array
    {
        return ['transferred_at' => 'datetime'];
    }

    public function fromStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'from_store_id');
    }

    public function toStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'to_store_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class);
    }

    public function scopeVisibleTo($query, User $user)
    {
        if ($user->isDirection()) {
            return $query;
        }

        return $query->where(fn ($q) => $q->where('from_store_id', $user->store_id)->orWhere('to_store_id', $user->store_id));
    }
}
