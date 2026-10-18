<?php

namespace App\Models\Concerns;

use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Séparation des données par magasin : un utilisateur hors Direction
 * ne voit que les données de son magasin.
 */
trait BelongsToStore
{
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @param  int|null  $storeId  filtre optionnel (utilisé par la Direction)
     */
    public function scopeVisibleTo(Builder $query, User $user, ?int $storeId = null): Builder
    {
        $column = $query->getModel()->getTable().'.store_id';

        if ($user->isDirection()) {
            return $storeId ? $query->where($column, $storeId) : $query;
        }

        return $query->where($column, $user->store_id ?? 0);
    }
}
