<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class SupplierProduct extends Pivot
{
    protected $table = 'supplier_products';

    public $incrementing = true;

    protected $fillable = ['supplier_id', 'product_id', 'purchase_price', 'min_quantity', 'lead_time_days', 'last_purchased_at', 'is_active'];

    protected function casts(): array
    {
        return [
            'purchase_price' => 'decimal:2',
            'last_purchased_at' => 'date',
            'is_active' => 'boolean',
        ];
    }
}
