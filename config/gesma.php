<?php

return [
    // Informations affichées sur les devis, factures et rapports PDF.
    'company' => [
        'name' => env('GESMA_COMPANY_NAME', 'Mobilier Addict'),
        'slogan' => env('GESMA_COMPANY_SLOGAN', 'Le mobilier qui vous ressemble'),
        'address' => env('GESMA_COMPANY_ADDRESS', 'Yamoussoukro, Côte d\'Ivoire'),
        'phone' => env('GESMA_COMPANY_PHONE', '+225 00 00 00 00 00'),
        'email' => env('GESMA_COMPANY_EMAIL', 'contact@mobilieraddict.ci'),
        'website' => env('GESMA_COMPANY_WEBSITE', 'www.mobilieraddict.ci'),
        'legal' => env('GESMA_COMPANY_LEGAL', 'RCCM : à compléter — NCC : à compléter'),
        'logo' => env('GESMA_COMPANY_LOGO', 'images/logo.png'),
        // Ligne d'activité sous le nom (en-tête des devis et factures).
        'activity' => env('GESMA_COMPANY_ACTIVITY', 'Vente de mobilier, décoration et aménagement intérieur'),
        // Coordonnées bancaires affichées en pied de page des devis et factures (laisser vide pour masquer).
        'bank' => env('GESMA_COMPANY_BANK', ''),
        // Cachet / signature scanné (PNG, chemin dans public/) apposé sous « Le Service Commercial ». Facultatif.
        'stamp' => env('GESMA_COMPANY_STAMP', 'images/cachet.png'),
    ],

    // Mise en page commune des devis et factures PDF.
    'documents' => [
        'signature_label' => env('GESMA_DOCUMENT_SIGNATURE_LABEL', 'Le Service Commercial'),
        // Nombre de lignes minimum du tableau (la grille garde une hauteur fixe comme sur le modèle papier).
        'min_rows' => 22,
    ],

    // Couleurs de Mobilier Addict (documents PDF et interface).
    'colors' => [
        'primary' => env('GESMA_COLOR_PRIMARY', '#8B5A2B'),
        'accent' => env('GESMA_COLOR_ACCENT', '#D4A373'),
        'dark' => env('GESMA_COLOR_DARK', '#2F241D'),
    ],

    'currency' => 'F CFA',

    'quotes' => [
        // Intitulé du document : « DEVIS » ou « FACTURE PRO FORMA ».
        'title' => env('GESMA_QUOTE_TITLE', 'DEVIS'),
        'validity_days' => (int) env('GESMA_QUOTE_VALIDITY_DAYS', 30),
        'footer' => env('GESMA_QUOTE_FOOTER', 'Devis valable sous réserve de disponibilité des produits. Acompte de 50 % à la commande.'),
        // Conditions numérotées imprimées sous le montant en lettres.
        'conditions' => [
            'Validité' => 'Prix valables jusqu\'à la date de validité indiquée, sous réserve de disponibilité des produits.',
            'Acompte' => 'Un acompte de 50 % est demandé à la commande.',
        ],
    ],

    'invoices' => [
        'title' => env('GESMA_INVOICE_TITLE', 'FACTURE'),
        'due_days' => (int) env('GESMA_INVOICE_DUE_DAYS', 0),
        'footer' => env('GESMA_INVOICE_FOOTER', 'Merci pour votre confiance. Les marchandises vendues ne sont ni reprises ni échangées sans le présent document.'),
        'conditions' => [
            'Délai de retour' => 'Les marchandises achetées ne sont ni reprises ni échangées sans la présente facture.',
        ],
    ],

    'payment_methods' => [
        'cash' => 'Espèces',
        'mobile_money' => 'Mobile Money',
        'card' => 'Carte bancaire',
        'transfer' => 'Virement',
        'cheque' => 'Chèque',
    ],

    // Intégration avec le site web Mobilier Addict.
    'website' => [
        // Magasin attribué aux commandes du site lorsqu'aucun magasin n'est précisé.
        'default_store_code' => env('GESMA_WEBSITE_DEFAULT_STORE', 'YAM'),
        // Les commandes du site sont acceptées même si le stock est insuffisant (signalées à vérifier).
        'accept_orders_without_stock' => (bool) env('GESMA_WEBSITE_ACCEPT_WITHOUT_STOCK', true),
        // URL de notification facultative appelée lors d'un changement de statut d'une commande du site.
        'webhook_url' => env('GESMA_WEBSITE_WEBHOOK_URL'),
        'webhook_secret' => env('GESMA_WEBSITE_WEBHOOK_SECRET'),
    ],

    'sync' => [
        // Nombre maximum d'opérations hors connexion traitées par envoi.
        'batch_size' => 50,
    ],
];
