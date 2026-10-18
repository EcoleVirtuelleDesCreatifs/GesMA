<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use BelongsToStore;

    protected $fillable = ['store_id', 'user_id', 'spent_at', 'amount', 'category', 'description', 'client_uuid'];

    protected function casts(): array
    {
        return ['spent_at' => 'date', 'amount' => 'decimal:2'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function categoryLabel(): string
    {
        return config("gesma.expense_categories.{$this->category}", $this->category);
    }
}
