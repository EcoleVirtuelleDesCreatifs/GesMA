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
