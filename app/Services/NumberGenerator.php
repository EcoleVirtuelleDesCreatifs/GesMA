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
            // insertOrIgnore évite l'erreur de doublon si deux documents sont créés au même instant.
            Sequence::query()->insertOrIgnore(['key' => $prefix, 'year' => $year, 'last_value' => 0]);
            $sequence = Sequence::where('key', $prefix)->where('year', $year)->lockForUpdate()->firstOrFail();
            $sequence->increment('last_value');

            return sprintf('%s-%d-%05d', $prefix, $year, $sequence->last_value);
        });
    }
}
