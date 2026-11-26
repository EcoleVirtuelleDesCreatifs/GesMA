<?php

use Illuminate\Support\HtmlString;

if (! function_exists('money')) {
    /** Formate un montant en F CFA : 125 000 F CFA */
    function money(int|float|null $amount, bool $withCurrency = true): string
    {
        $formatted = number_format((float) ($amount ?? 0), 0, ',', ' ');

        return $withCurrency ? $formatted.' F CFA' : $formatted;
    }
}

if (! function_exists('badge')) {
    /** Badge HTML pour un enum disposant de label() et color(). */
    function badge($enum): HtmlString
    {
        if (! $enum) {
            return new HtmlString('');
        }

        return new HtmlString(sprintf('<span class="badge badge-%s">%s</span>', e($enum->color()), e($enum->label())));
    }
}

if (! function_exists('amount_in_words')) {
    /**
     * Écrit un montant entier en toutes lettres (orthographe traditionnelle) :
     * 6 420 641 → « six millions quatre cent vingt mille six cent quarante et un ».
     */
    function amount_in_words(int|float|null $amount): string
    {
        $n = (int) round((float) ($amount ?? 0));
        if ($n === 0) {
            return 'zéro';
        }
        if ($n < 0) {
            return 'moins '.amount_in_words(-$n);
        }

        $units = ['', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf', 'dix', 'onze', 'douze',
            'treize', 'quatorze', 'quinze', 'seize', 'dix-sept', 'dix-huit', 'dix-neuf'];
        $tens = [2 => 'vingt', 3 => 'trente', 4 => 'quarante', 5 => 'cinquante', 6 => 'soixante'];

        // 0 à 99 ; $final indique si le groupe termine le nombre (accord de « quatre-vingts »).
        $below100 = function (int $n, bool $final) use ($units, $tens): string {
            if ($n < 20) {
                return $units[$n];
            }
            $t = intdiv($n, 10);
            $u = $n % 10;
            if ($t === 7 || $t === 9) {
                $base = $t === 7 ? 'soixante' : 'quatre-vingt';
                $rest = 10 + $u;
                if ($t === 7 && $u === 1) {
                    return 'soixante et onze';
                }

                return $base.'-'.$units[$rest];
            }
            if ($t === 8) {
                return $u === 0 ? ($final ? 'quatre-vingts' : 'quatre-vingt') : 'quatre-vingt-'.$units[$u];
            }
            if ($u === 0) {
                return $tens[$t];
            }

            return $tens[$t].($u === 1 ? ' et un' : '-'.$units[$u]);
        };

        // 0 à 999.
        $below1000 = function (int $n, bool $final) use ($below100, $units): string {
            $h = intdiv($n, 100);
            $r = $n % 100;
            $words = [];
            if ($h > 0) {
                $words[] = $h === 1 ? 'cent' : $units[$h].' cent'.($r === 0 && $final ? 's' : '');
            }
            if ($r > 0) {
                $words[] = $below100($r, $final);
            }

            return implode(' ', $words);
        };

        $parts = [];
        foreach ([[1_000_000_000, 'milliard'], [1_000_000, 'million']] as [$size, $name]) {
            if ($n >= $size) {
                $count = intdiv($n, $size);
                $n %= $size;
                $parts[] = amount_in_words($count).' '.$name.($count > 1 ? 's' : '');
            }
        }
        if ($n >= 1000) {
            $count = intdiv($n, 1000);
            $n %= 1000;
            $parts[] = $count === 1 ? 'mille' : $below1000($count, false).' mille';
        }
        if ($n > 0) {
            $parts[] = $below1000($n, true);
        }

        return implode(' ', $parts);
    }
}
