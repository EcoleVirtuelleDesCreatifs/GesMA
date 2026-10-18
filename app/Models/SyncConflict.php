<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SyncConflict extends Model
{
    use BelongsToStore;

    public const TYPES = [
        'stock_insuffisant' => 'Stock insuffisant à la synchronisation',
        'statut_modifie' => 'Statut modifié pendant la coupure',
        'produit_indisponible' => 'Produit introuvable ou indisponible',
        'site_stock_insuffisant' => 'Commande du site avec stock insuffisant',
    ];

    protected $fillable = ['sync_operation_id', 'store_id', 'user_id', 'type', 'message', 'details', 'subject_type', 'subject_id', 'resolved_at', 'resolved_by', 'resolution_note'];

    protected function casts(): array
    {
        return ['details' => 'array', 'resolved_at' => 'datetime'];
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('resolved_at');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
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
        return self::TYPES[$this->type] ?? $this->type;
    }
}
