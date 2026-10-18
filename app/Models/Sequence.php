<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/** Numérotation sans trou ni doublon (verrou de ligne) */
class Sequence extends Model
{
    protected $fillable = ['key', 'value'];

    public static function next(string $prefix, int $pad = 5): string
    {
        $year = now()->format('Y');
        $key = "{$prefix}-{$year}";

        self::query()->insertOrIgnore(['key' => $key, 'value' => 0, 'created_at' => now(), 'updated_at' => now()]);

        $value = DB::transaction(function () use ($key) {
            $sequence = self::where('key', $key)->lockForUpdate()->firstOrFail();
            $sequence->increment('value');

            return $sequence->value;
        });

        return sprintf('%s-%s-%s', $prefix, $year, str_pad((string) $value, $pad, '0', STR_PAD_LEFT));
    }
}
