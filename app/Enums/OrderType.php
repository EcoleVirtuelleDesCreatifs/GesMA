<?php

namespace App\Enums;

enum OrderType: string
{
    use EnumHelpers;

    case Order = 'order';
    case Sale = 'sale';

    public function label(): string
    {
        return match ($this) {
            self::Order => 'Commande',
            self::Sale => 'Vente directe',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Order => 'info',
            self::Sale => 'success',
        };
    }
}
