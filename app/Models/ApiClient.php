<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ApiClient extends Model
{
    protected $fillable = ['name', 'token_hash', 'token_prefix', 'default_store_id', 'is_active', 'last_used_at'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'last_used_at' => 'datetime'];
    }

    public function defaultStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'default_store_id');
    }

    /** Crée un client et renvoie le jeton en clair (affiché une seule fois). */
    public static function issue(string $name, ?int $defaultStoreId = null): array
    {
        $token = 'gma_'.Str::random(40);
        $client = static::create([
            'name' => $name,
            'token_hash' => hash('sha256', $token),
            'token_prefix' => substr($token, 0, 12),
            'default_store_id' => $defaultStoreId,
        ]);

        return [$client, $token];
    }

    public static function findByToken(?string $token): ?self
    {
        if (! $token) {
            return null;
        }

        return static::where('token_hash', hash('sha256', $token))->where('is_active', true)->first();
    }
}
