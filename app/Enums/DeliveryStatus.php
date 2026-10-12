<?php

namespace App\Enums;

enum DeliveryStatus: string
{
    use EnumHelpers;

    case Pending = 'pending';
    case Preparing = 'preparing';
    case InTransit = 'in_transit';
    case Delivered = 'delivered';
    case Failed = 'failed';
    case Returned = 'returned';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'À préparer',
            self::Preparing => 'En préparation',
            self::InTransit => 'En route',
            self::Delivered => 'Livrée',
            self::Failed => 'Échec de livraison',
            self::Returned => 'Retournée',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'muted',
            self::Preparing => 'info',
            self::InTransit => 'primary',
            self::Delivered => 'success',
            self::Failed => 'warning',
            self::Returned => 'danger',
        };
    }

    /** Statut de commande correspondant à l'état de la livraison. */
    public function orderStatus(): ?OrderStatus
    {
        return match ($this) {
            self::InTransit => OrderStatus::Shipped,
            self::Delivered => OrderStatus::Delivered,
            self::Failed => OrderStatus::NotDelivered,
            self::Returned => OrderStatus::Returned,
            default => null,
        };
    }

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Preparing, self::InTransit],
            self::Preparing => [self::InTransit, self::Pending],
            self::InTransit => [self::Delivered, self::Failed, self::Returned],
            self::Failed => [self::InTransit, self::Returned],
            self::Delivered => [self::Returned],
            self::Returned => [],
        };
    }

    public function requiresReason(): bool
    {
        return in_array($this, [self::Failed, self::Returned], true);
    }

    public static function active(): array
    {
        return [self::Pending->value, self::Preparing->value, self::InTransit->value, self::Failed->value];
    }
}
