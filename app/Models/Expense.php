<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Expense extends Model
{
    use BelongsToStore;

    protected $fillable = ['uuid', 'spent_at', 'amount', 'expense_category_id', 'store_id', 'user_id', 'description', 'payment_method', 'source'];

    protected function casts(): array
    {
        return ['spent_at' => 'date', 'amount' => 'integer'];
    }

    protected static function booted(): void
    {
        static::creating(function (Expense $expense) {
            $expense->uuid ??= (string) Str::uuid();
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
