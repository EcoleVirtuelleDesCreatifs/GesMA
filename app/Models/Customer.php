<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Str;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = ['uuid', 'name', 'phone', 'phone_alt', 'email', 'address', 'city', 'notes', 'store_id', 'website_customer_id'];

    protected static function booted(): void
    {
        static::creating(function (Customer $customer) {
            $customer->uuid ??= (string) Str::uuid();
        });
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function deliveries(): HasManyThrough
    {
        return $this->hasManyThrough(Delivery::class, Order::class);
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! $term) {
            return $query;
        }

        $digits = preg_replace('/\D+/', '', $term);

        return $query->where(function ($q) use ($term, $digits) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('city', 'like', "%{$term}%");
            if (strlen($digits) >= 4) {
                $q->orWhere('phone', 'like', "%{$digits}%")->orWhere('phone_alt', 'like', "%{$digits}%");
            }
        });
    }

    /** Ajoute le nombre de commandes et le montant total acheté (hors annulations et retours). */
    public function scopeWithPurchaseStats($query)
    {
        $excluded = [OrderStatus::Cancelled->value, OrderStatus::Returned->value];

        return $query
            ->withCount(['orders as orders_count' => fn ($q) => $q->whereNotIn('status', $excluded)])
            ->withSum(['orders as total_spent' => fn ($q) => $q->whereNotIn('status', $excluded)], 'total');
    }

    public static function normalizePhone(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $clean = preg_replace('/[^\d+]/', '', $phone);

        return $clean === '' ? null : $clean;
    }
}
