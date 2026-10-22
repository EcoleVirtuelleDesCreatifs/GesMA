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

if (! function_exists('whatsapp_number')) {
    /** Numéro au format international pour wa.me : "07 99 14 03 56" -> "2250799140356". */
    function whatsapp_number(?string $phone, string $defaultCountryCode = '225'): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);
        if ($digits === '') {
            return null;
        }
        if (str_starts_with($digits, '00')) {
            return substr($digits, 2);
        }
        if (! str_starts_with($digits, $defaultCountryCode)) {
            return $defaultCountryCode . $digits;
        }

        return $digits;
    }
}

if (! function_exists('whatsapp_share_url')) {
    /** URL https://wa.me/<numero>?text=<message>. */
    function whatsapp_share_url(?string $phone, string $message): string
    {
        return 'https://wa.me/' . ($phone ?? '') . '?text=' . rawurlencode($message);
    }
}

if (! function_exists('whatsapp_document_link')) {
    /** Lien wa.me pré-rempli : résumé du document + URL signée (7 jours) du PDF. */
    function whatsapp_document_link(\App\Models\Document $document): string
    {
        $phone = whatsapp_number(
            $document->customer?->phone,
            config('gesma.documents.whatsapp_country_code', '225')
        );
        $pdfUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'documents.share.pdf',
            now()->addDays(7),
            ['document' => $document->id]
        );
        $type = $document->isQuote() ? 'devis' : 'facture';
        $name = $document->customer->name ?? 'Client';
        $message = "Bonjour {$name},\n"
            . "Voici votre {$type} {$document->number} — Total : " . money($document->total) . ".\n"
            . "Téléchargez le PDF ici : {$pdfUrl}";

        return whatsapp_share_url($phone, $message);
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
