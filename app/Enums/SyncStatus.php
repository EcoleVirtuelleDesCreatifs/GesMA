<?php

namespace App\Enums;

enum SyncStatus: string
{
    use EnumHelpers;

    case Applied = 'applied';
    case Duplicate = 'duplicate';
    case Conflict = 'conflict';
    case Failed = 'failed';
    case Resolved = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::Applied => 'Synchronisée',
            self::Duplicate => 'Doublon ignoré',
            self::Conflict => 'Conflit à vérifier',
            self::Failed => 'Échec',
            self::Resolved => 'Résolue',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Applied => 'success',
            self::Duplicate => 'muted',
            self::Conflict => 'warning',
            self::Failed => 'danger',
            self::Resolved => 'info',
        };
    }
}
