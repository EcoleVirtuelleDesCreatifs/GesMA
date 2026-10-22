<?php

if (! function_exists('money')) {
    /** Formate un montant en F CFA : 125 000 F CFA */
    function money($amount, bool $withCurrency = true): string
    {
        $formatted = number_format((float) $amount, 0, ',', ' ');

        return $withCurrency ? $formatted . ' ' . config('gesma.company.currency', 'F CFA') : $formatted;
    }
}

if (! function_exists('nombre_lettres')) {
    /** Convertit un entier positif en lettres françaises (0 -> "zéro"). */
    function nombre_lettres(int $n): string
    {
        if ($n === 0) {
            return 'zéro';
        }

        $units = [
            '',
            'un',
            'deux',
            'trois',
            'quatre',
            'cinq',
            'six',
            'sept',
            'huit',
            'neuf',
            'dix',
            'onze',
            'douze',
            'treize',
            'quatorze',
            'quinze',
            'seize',
            'dix-sept',
            'dix-huit',
            'dix-neuf'
        ];
        $tens = ['', '', 'vingt', 'trente', 'quarante', 'cinquante', 'soixante', '', 'quatre-vingt', ''];

        $below100 = function (int $n) use ($units, $tens): string {
            if ($n < 20) {
                return $units[$n];
            }
            $t = intdiv($n, 10);
            $r = $n % 10;
            if ($t === 7 || $t === 9) {
                // 70-79, 90-99 : soixante-dix / quatre-vingt-dix
                $base = $tens[$t - 1];
                $rest = $n - ($t - 1) * 10;
                return $base . '-' . ($rest === 11 && $t === 7 ? 'et-onze' : $units[$rest]);
            }
            $word = $tens[$t];
            if ($r === 0) {
                return $word . ($t === 8 ? 's' : '');
            }
            if ($r === 1 && $t !== 8) {
                return $word . ' et un';
            }
            return $word . '-' . $units[$r];
        };

        $below1000 = function (int $n) use ($below100): string {
            if ($n < 100) {
                return $below100($n);
            }
            $h = intdiv($n, 100);
            $rest = $n % 100;
            $word = $h === 1 ? 'cent' : $below100($h) . ' cent' . ($rest === 0 ? 's' : '');
            return $rest ? $word . ' ' . $below100($rest) : $word;
        };

        $chunks = [
            [1000000000, 'milliard', 'milliards'],
            [1000000, 'million', 'millions'],
            [1000, 'mille', 'mille'],
        ];
        $words = [];
        foreach ($chunks as [$div, $singular, $plural]) {
            $q = intdiv($n, $div);
            $n %= $div;
            if ($q === 0) {
                continue;
            }
            $group = $below1000($q);
            // cent/vingt restent au singulier devant mille, million, milliard
            $group = preg_replace('/(cent|vingt)s$/u', '$1', $group);
            if ($div === 1000) {
                $words[] = $q === 1 ? 'mille' : $group . ' mille';
            } else {
                $words[] = $group . ' ' . ($q === 1 ? $singular : $plural);
            }
        }
        if ($n > 0) {
            $words[] = $below1000($n);
        }

        return implode(' ', $words);
    }
}

if (! function_exists('montant_lettres')) {
    /** Montant en lettres pour la ligne "Arrêté(e) ... à la somme de". */
    function montant_lettres($amount): string
    {
        return nombre_lettres((int) round((float) $amount));
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
