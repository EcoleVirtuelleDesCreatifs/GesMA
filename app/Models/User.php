<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'phone', 'password', 'role', 'store_id', 'is_active'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function isDirection(): bool
    {
        return $this->role === Role::Direction;
    }

    public function hasPermission(string $permission): bool
    {
        $permissions = $this->role?->permissions() ?? [];

        return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
    }

    public function canAccessStore(int|string|null $storeId): bool
    {
        return $this->isDirection() || ($storeId !== null && (int) $storeId === (int) $this->store_id);
    }

    /** Magasins que l'utilisateur peut consulter. */
    public function accessibleStores()
    {
        return Store::query()
            ->when(! $this->isDirection(), fn ($q) => $q->whereKey($this->store_id ?? 0))
            ->orderBy('name')
            ->get();
    }
}
