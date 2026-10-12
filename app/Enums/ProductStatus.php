<?php

namespace App\Enums;

enum ProductStatus: string
{
    use EnumHelpers;

    case Available = 'available';
    case OnOrder = 'on_order';
    case Unavailable = 'unavailable';
    case Discontinued = 'discontinued';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Disponible',
            self::OnOrder => 'Sur commande',
            self::Unavailable => 'Indisponible',
            self::Discontinued => 'Arrêté',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Available => 'success',
            self::OnOrder => 'info',
            self::Unavailable => 'warning',
            self::Discontinued => 'muted',
        };
    }
}
