<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class DocumentLine extends Model
{
    public $timestamps = false;

    protected $fillable = ['product_id', 'designation', 'reference', 'unit_price', 'quantity', 'discount_rate', 'line_total'];

    protected function casts(): array
    {
        return ['unit_price' => 'integer', 'quantity' => 'integer', 'discount_rate' => 'float', 'line_total' => 'integer'];
    }

    /** Montant hors remise : quantité × prix unitaire. */
    public function grossTotal(): int
    {
        return $this->unit_price * $this->quantity;
    }

    /** Montant de la remise de ligne. */
    public function discountAmount(): int
    {
        return max(0, $this->grossTotal() - $this->line_total);
    }

    /** Prix unitaire après remise (affichage). */
    public function netUnitPrice(): float
    {
        return $this->unit_price * (100 - (float) $this->discount_rate) / 100;
    }

    /** Calcule le montant net d'une ligne (arrondi au franc). */
    public static function computeTotal(int $unitPrice, int $quantity, float $discountRate = 0): int
    {
        $rate = min(100, max(0, $discountRate));

        return (int) round($unitPrice * $quantity * (100 - $rate) / 100);
    }

    public function document(): MorphTo
    {
        return $this->morphTo();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
