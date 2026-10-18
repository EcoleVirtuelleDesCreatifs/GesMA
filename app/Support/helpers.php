<?php

if (! function_exists('money')) {
    /** Formate un montant en F CFA : 125 000 F CFA */
    function money($amount, bool $withCurrency = true): string
    {
        $formatted = number_format((float) $amount, 0, ',', ' ');

        return $withCurrency ? $formatted.' '.config('gesma.company.currency', 'F CFA') : $formatted;
    }
}

if (! function_exists('status_badge')) {
    /** Classe CSS du badge selon le statut */
    function status_badge(?string $status): string
    {
        return match ($status) {
            'livree', 'appliquee' => 'badge-success',
            'en_preparation', 'preparation' => 'badge-info',
            'expediee', 'en_route' => 'badge-primary',
            'non_livree', 'echec', 'conflit' => 'badge-warning',
            'annulee', 'retournee', 'retour', 'rejetee' => 'badge-danger',
            default => 'badge-muted',
        };
    }
}
