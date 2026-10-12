<?php

namespace App\Enums;

enum QuoteStatus: string
{
    use EnumHelpers;

    case Draft = 'draft';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Refused = 'refused';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::Sent => 'Envoyé',
            self::Accepted => 'Accepté',
            self::Refused => 'Refusé',
            self::Expired => 'Expiré',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'muted',
            self::Sent => 'info',
            self::Accepted => 'success',
            self::Refused => 'danger',
            self::Expired => 'warning',
        };
    }
}
