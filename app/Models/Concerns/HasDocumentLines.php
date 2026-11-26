<?php

namespace App\Models\Concerns;

use App\Models\Customer;
use App\Models\DocumentLine;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** Comportement commun aux devis et factures. */
trait HasDocumentLines
{
    use BelongsToStore;

    public function lines(): MorphMany
    {
        return $this->morphMany(DocumentLine::class, 'document')->orderBy('id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function getRouteKeyName(): string
    {
        return 'number';
    }

    /**
     * Remplace les lignes et recalcule les totaux.
     * subtotal = somme des lignes nettes (après remise de ligne) ; total = subtotal − remise globale + livraison.
     *
     * @param  array<int, array{product_id?:int|null, designation:string, reference?:string|null, unit_price:int, quantity:int, discount_rate?:float|int|string|null}>  $lines
     */
    public function syncLines(array $lines): void
    {
        $this->lines()->delete();
        $subtotal = 0;
        foreach ($lines as $line) {
            $rate = min(100, max(0, round((float) ($line['discount_rate'] ?? 0), 2)));
            $lineTotal = DocumentLine::computeTotal((int) $line['unit_price'], (int) $line['quantity'], $rate);
            $subtotal += $lineTotal;
            $this->lines()->create([
                'product_id' => $line['product_id'] ?? null,
                'designation' => $line['designation'],
                'reference' => $line['reference'] ?? null,
                'unit_price' => (int) $line['unit_price'],
                'quantity' => (int) $line['quantity'],
                'discount_rate' => $rate,
                'line_total' => $lineTotal,
            ]);
        }
        $this->subtotal = $subtotal;
        $this->total = max(0, $subtotal - (int) $this->discount + (int) $this->delivery_fee);
        $this->save();
    }

    /** Total hors remises (Σ quantité × prix unitaire). */
    public function grossTotal(): int
    {
        return (int) $this->lines->sum(fn (DocumentLine $l) => $l->grossTotal());
    }

    /** Total des remises : remises de ligne + remise globale. */
    public function totalDiscount(): int
    {
        return (int) $this->lines->sum(fn (DocumentLine $l) => $l->discountAmount()) + (int) $this->discount;
    }

    /** Total net hors frais de livraison. */
    public function netTotal(): int
    {
        return max(0, (int) $this->subtotal - (int) $this->discount);
    }
}
