<?php

namespace App\Services;

use App\Models\Sequence;
use Illuminate\Support\Facades\DB;

/** Numérotation continue des documents : CMD-2026-00001, FAC-2026-00001… */
class NumberGenerator
{
    public const ORDER = 'CMD';

    public const SALE = 'VTE';

    public const QUOTE = 'DEV';

    public const INVOICE = 'FAC';

    public const TRANSFER = 'TRF';

    public function next(string $prefix, ?int $year = null): string
    {
        $year ??= (int) now()->format('Y');

        return DB::transaction(function () use ($prefix, $year) {
            $sequence = Sequence::where('key', $prefix)->where('year', $year)->lockForUpdate()->first();
            if (! $sequence) {
                $sequence = Sequence::create(['key' => $prefix, 'year' => $year, 'last_value' => 0]);
            }
            $sequence->increment('last_value');

            return sprintf('%s-%d-%05d', $prefix, $year, $sequence->last_value);
        });
    }
}
