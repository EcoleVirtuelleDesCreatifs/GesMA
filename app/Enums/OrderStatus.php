<?php

namespace App\Enums;

enum OrderStatus: string
{
    use EnumHelpers;

    case Preparing = 'preparing';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case NotDelivered = 'not_delivered';
    case Cancelled = 'cancelled';
    case Returned = 'returned';

    public function label(): string
    {
        return match ($this) {
            self::Preparing => 'En préparation',
            self::Shipped => 'Expédiée',
            self::Delivered => 'Livrée',
            self::NotDelivered => 'Non livrée',
            self::Cancelled => 'Annulée',
            self::Returned => 'Retournée',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Preparing => 'info',
            self::Shipped => 'primary',
            self::Delivered => 'success',
            self::NotDelivered => 'warning',
            self::Cancelled => 'muted',
            self::Returned => 'danger',
        };
    }

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Preparing => [self::Shipped, self::Delivered, self::Cancelled],
            self::Shipped => [self::Delivered, self::NotDelivered, self::Returned, self::Cancelled],
            self::NotDelivered => [self::Shipped, self::Delivered, self::Returned, self::Cancelled],
            self::Delivered => [self::Returned],
            self::Cancelled, self::Returned => [],
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->allowedTransitions(), true);
    }

    /** Statuts dont les produits sont sortis du stock (et pas encore réintégrés). */
    public function holdsStock(): bool
    {
        return ! in_array($this, [self::Cancelled, self::Returned], true);
    }

    /** Commandes encore en cours (ni livrées, ni annulées, ni retournées). */
    public static function open(): array
    {
        return [self::Preparing->value, self::Shipped->value, self::NotDelivered->value];
    }
}
