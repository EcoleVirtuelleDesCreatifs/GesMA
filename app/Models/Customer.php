<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Customer extends Model
{
    use BelongsToStore;

    protected $fillable = ['name', 'phone', 'email', 'address', 'city', 'notes', 'store_id', 'client_uuid'];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function deliveries(): HasManyThrough
    {
        return $this->hasManyThrough(Delivery::class, Order::class);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        return $query->where(fn ($q) => $q->where('customers.name', 'like', "%{$term}%")
            ->orWhere('customers.phone', 'like', "%{$term}%")
            ->orWhere('customers.email', 'like', "%{$term}%"));
    }

    /**
     * Les clients sont partagés entre magasins : un client est visible s'il a été
     * créé dans le magasin de l'utilisateur ou s'il y a passé commande.
     */
    public function scopeVisibleTo(Builder $query, User $user, ?int $storeId = null): Builder
    {
        $target = $user->isDirection() ? $storeId : ($user->store_id ?? 0);
        if (! $target) {
            return $query;
        }

        return $query->where(fn ($q) => $q->where('customers.store_id', $target)
            ->orWhereHas('orders', fn ($o) => $o->where('store_id', $target)));
    }
}
