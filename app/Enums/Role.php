<?php

namespace App\Enums;

enum Role: string
{
    use EnumHelpers;

    case Direction = 'direction';
    case Manager = 'manager';
    case Sales = 'sales';
    case Logistics = 'logistics';

    public function label(): string
    {
        return match ($this) {
            self::Direction => 'Direction',
            self::Manager => 'Responsable de magasin',
            self::Sales => 'Équipe de vente',
            self::Logistics => 'Équipe logistique',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Direction => 'primary',
            self::Manager => 'info',
            self::Sales => 'success',
            self::Logistics => 'warning',
        };
    }

    /** Les permissions de chaque profil (à ajuster lors de la validation des droits). */
    public function permissions(): array
    {
        return match ($this) {
            self::Direction => ['*'],
            self::Manager => [
                'dashboard', 'orders.view', 'orders.manage', 'orders.cancel', 'sales.view',
                'customers.view', 'customers.manage', 'products.view', 'products.manage', 'products.price',
                'stock.view', 'stock.manage', 'stock.adjust', 'stock.transfer',
                'deliveries.view', 'deliveries.manage', 'quotes.manage', 'invoices.manage',
                'expenses.view', 'expenses.manage', 'reports.view', 'sync.resolve',
            ],
            self::Sales => [
                'dashboard', 'orders.view', 'orders.manage', 'sales.view',
                'customers.view', 'customers.manage', 'products.view', 'stock.view',
                'deliveries.view', 'quotes.manage', 'invoices.manage', 'expenses.manage',
            ],
            self::Logistics => [
                'orders.view', 'products.view', 'stock.view', 'customers.view',
                'deliveries.view', 'deliveries.manage', 'expenses.manage',
            ],
        };
    }
}
