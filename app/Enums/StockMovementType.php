<?php

namespace App\Enums;

enum StockMovementType: string
{
    use EnumHelpers;

    case In = 'in';
    case Out = 'out';
    case Sale = 'sale';
    case Return = 'return';
    case Adjustment = 'adjustment';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';
    case Cancellation = 'cancellation';

    public function label(): string
    {
        return match ($this) {
            self::In => 'Entrée',
            self::Out => 'Sortie',
            self::Sale => 'Vente',
            self::Return => 'Retour',
            self::Adjustment => 'Correction',
            self::TransferIn => 'Transfert reçu',
            self::TransferOut => 'Transfert envoyé',
            self::Cancellation => 'Annulation de commande',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::In => 'success',
            self::Out => 'warning',
            self::Sale => 'primary',
            self::Return => 'info',
            self::Adjustment => 'danger',
            self::TransferIn => 'success',
            self::TransferOut => 'warning',
            self::Cancellation => 'info',
        };
    }
}
