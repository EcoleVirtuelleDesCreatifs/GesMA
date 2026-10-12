<?php

namespace App\Enums;

enum OrderSource: string
{
    use EnumHelpers;

    case Gesma = 'gesma';
    case Website = 'website';
    case Offline = 'offline';

    public function label(): string
    {
        return match ($this) {
            self::Gesma => 'GesMA',
            self::Website => 'Site web',
            self::Offline => 'Hors connexion',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Gesma => 'muted',
            self::Website => 'primary',
            self::Offline => 'warning',
        };
    }
}
