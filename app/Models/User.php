<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = ['name', 'email', 'phone', 'role', 'store_id', 'is_active', 'password', 'last_login_at'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function isDirection(): bool
    {
        return $this->role === 'direction';
    }

    public function roleLabel(): string
    {
        return config("gesma.roles.{$this->role}", $this->role);
    }

    /** Vérifie un droit défini dans config/gesma.php */
    public function hasPermission(string $permission): bool
    {
        if (! $this->is_active) {
            return false;
        }
        if ($this->isDirection()) {
            return true;
        }

        return in_array($this->role, config('gesma.permissions')[$permission] ?? [], true);
    }

    /** Identifiants des magasins accessibles */
    public function accessibleStoreIds(): array
    {
        if ($this->isDirection()) {
            return Store::pluck('id')->all();
        }

        return $this->store_id ? [$this->store_id] : [];
    }

    public function canAccessStore(?int $storeId): bool
    {
        return $storeId !== null && in_array($storeId, $this->accessibleStoreIds(), true);
    }
}
