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

    /** @param  array<int, array{product_id?:int|null, designation:string, reference?:string|null, unit_price:int, quantity:int}>  $lines */
    public function syncLines(array $lines): void
    {
        $this->lines()->delete();
        $subtotal = 0;
        foreach ($lines as $line) {
            $lineTotal = (int) $line['unit_price'] * (int) $line['quantity'];
            $subtotal += $lineTotal;
            $this->lines()->create([
                'product_id' => $line['product_id'] ?? null,
                'designation' => $line['designation'],
                'reference' => $line['reference'] ?? null,
                'unit_price' => (int) $line['unit_price'],
                'quantity' => (int) $line['quantity'],
                'line_total' => $lineTotal,
            ]);
        }
        $this->subtotal = $subtotal;
        $this->total = max(0, $subtotal - (int) $this->discount + (int) $this->delivery_fee);
        $this->save();
    }
}
