<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    use EnumHelpers;

    case Unpaid = 'unpaid';
    case Partial = 'partial';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'Non payée',
            self::Partial => 'Partiellement payée',
            self::Paid => 'Payée',
            self::Cancelled => 'Annulée',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Unpaid => 'warning',
            self::Partial => 'info',
            self::Paid => 'success',
            self::Cancelled => 'muted',
        };
    }
}
