<?php

namespace App\Exceptions;

use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    /** @param  array<int, array{product_id:int, product:string, requested:int, available:int}>  $shortages */
    public function __construct(public readonly array $shortages)
    {
        $lines = array_map(
            fn ($s) => sprintf('%s : demandé %d, disponible %d', $s['product'], $s['requested'], $s['available']),
            $shortages
        );
        parent::__construct('Stock insuffisant — '.implode(' ; ', $lines));
    }
}
