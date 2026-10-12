<?php

namespace App\Services;

use RuntimeException;

class SyncConflict extends RuntimeException
{
    public function __construct(string $message, public readonly array $context = [])
    {
        parent::__construct($message);
    }
}
