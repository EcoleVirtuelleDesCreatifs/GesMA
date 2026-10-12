<?php

namespace App\Models\Concerns;

use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cloisonnement des données par magasin : la direction voit tout,
 * les autres profils ne voient que les données de leur magasin.
 */
trait BelongsToStore
{
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isDirection()) {
            return $query;
        }

        return $query->where($this->getTable().'.store_id', $user->store_id ?? 0);
    }

    public function scopeForStore(Builder $query, $storeId): Builder
    {
        return $storeId ? $query->where($this->getTable().'.store_id', $storeId) : $query;
    }
}
