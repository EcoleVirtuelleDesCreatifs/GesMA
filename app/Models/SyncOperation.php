<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncOperation extends Model
{
    protected $fillable = ['uuid', 'type', 'user_id', 'device_id', 'status', 'payload', 'result', 'recorded_at'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'result' => 'array', 'recorded_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
