<?php

return [

    'company' => [
        'name' => 'Mobilier Addict',
        'slogan' => 'Fabriquant et distributeur officiel de matelas orthopédiques authentiques de qualité premium',
        'products_line' => 'Matelas orthopédiques · Oreillers · Draps · Couettes · Électroménagers · Meubles · Lits',
        'address' => "Côte d'Ivoire",
        'phone' => '07 01 60 52 32 / 07 99 14 03 56',
        'email' => '',
        'website' => 'www.mobilier-addict.com',
        'currency' => 'F CFA',
        // Couleurs utilisées dans l'application et les PDF (à ajuster à la charte Mobilier Addict)
        'primary_color' => '#1F2A56',
        'accent_color' => '#E75480',
    ],

    /*
    | Identité légale utilisée sur les devis et factures PDF
    | (en-tête, slogan et pied de page — modèle "MOBILIER GROUP'S").
    */
    'documents' => [
        'initials' => 'MG',
        'name' => "MOBILIER GROUP'S",
        'legal_form' => 'SARL',
        'location' => 'Abidjan – Bingerville – Abatta',
        'phone1' => '07 99 14 03 56',
        'phone2' => '07 01 60 52 32',
        'slogan' => 'Literie · Électroménagers · Mobilier · Décoration · Plomberie · Sanitaire · Électricité · Matériaux de construction',
        'capital' => '5 000 000 F CFA',
        'siege' => 'Abidjan, Bingerville, Abatta, à 100 m du supermarché AB CENTER',
        'rccm' => 'CI-ABJ-03-2024-B12-02963',
        'cc' => '2402115 F',
        'quote_validity_days' => 30,
    ],

    /*
    | Profils utilisateurs (section 13 du cahier des charges)
    */
    'roles' => [
        'direction' => 'Direction',
        'responsable' => 'Responsable de magasin',
        'vendeur' => 'Équipe de vente',
        'logistique' => 'Équipe logistique',
    ],

    /*
    | Droits par profil. La Direction a tous les droits.
    | À ajuster après validation des profils (section 20).
    */
    'permissions' => [
        'dashboard.view' => ['responsable'],
        'products.view' => ['responsable', 'vendeur', 'logistique'],
        'products.manage' => ['responsable'],
        'products.price' => [],
        'stock.view' => ['responsable', 'vendeur', 'logistique'],
        'stock.move' => ['responsable'],
        'stock.adjust' => ['responsable'],
        'stock.transfer' => ['responsable'],
        'customers.view' => ['responsable', 'vendeur'],
        'customers.manage' => ['responsable', 'vendeur'],
        'customers.delete' => [],
        'orders.view' => ['responsable', 'vendeur', 'logistique'],
        'orders.create' => ['responsable', 'vendeur'],
        'orders.status' => ['responsable', 'vendeur', 'logistique'],
        'orders.cancel' => ['responsable'],
        'deliveries.view' => ['responsable', 'logistique'],
        'deliveries.manage' => ['responsable', 'logistique'],
        'documents.view' => ['responsable', 'vendeur'],
        'documents.manage' => ['responsable', 'vendeur'],
        'documents.delete' => ['responsable'],
        'expenses.view' => ['responsable'],
        'expenses.manage' => ['responsable'],
        'reports.view' => ['responsable'],
        'conflicts.view' => ['responsable'],
        'stores.manage' => [],
        'users.manage' => [],
        'activity.view' => [],
    ],

    'order_statuses' => [
        'en_preparation' => 'En préparation',
        'expediee' => 'Expédiée',
        'livree' => 'Livrée',
        'non_livree' => 'Non livrée',
        'annulee' => 'Annulée',
        'retournee' => 'Retournée',
    ],

    // Transitions autorisées entre statuts de commande
    'order_transitions' => [
        'en_preparation' => ['expediee', 'livree', 'annulee'],
        'expediee' => ['livree', 'non_livree', 'retournee'],
        'non_livree' => ['expediee', 'annulee', 'retournee'],
        'livree' => ['retournee'],
        'annulee' => [],
        'retournee' => [],
    ],

    'delivery_statuses' => [
        'preparation' => 'En préparation',
        'en_route' => 'Partie en livraison',
        'livree' => 'Livraison effectuée',
        'echec' => 'Échec de livraison',
        'retour' => 'Retour',
        'annulee' => 'Annulée',
    ],

    // Statut de commande correspondant à chaque statut de livraison
    'delivery_to_order_status' => [
        'preparation' => 'en_preparation',
        'en_route' => 'expediee',
        'livree' => 'livree',
        'echec' => 'non_livree',
        'retour' => 'retournee',
        'annulee' => 'annulee',
    ],

    'order_sources' => [
        'gesma' => 'GesMA',
        'site' => 'Site web',
        'hors_ligne' => 'Saisie hors connexion',
    ],

    'payment_methods' => [
        'especes' => 'Espèces',
        'mobile_money' => 'Mobile Money',
        'virement' => 'Virement',
        'cheque' => 'Chèque',
        'carte' => 'Carte bancaire',
        'a_la_livraison' => 'À la livraison',
    ],

    'expense_categories' => [
        'transport' => 'Transport',
        'livraison' => 'Livraisons',
        'loyer' => 'Loyer',
        'salaires' => 'Salaires',
        'electricite_eau' => 'Électricité / eau',
        'achat_marchandises' => 'Achat de marchandises',
        'communication' => 'Communication / Internet',
        'entretien' => 'Entretien / réparations',
        'fonctionnement' => 'Frais de fonctionnement',
        'autre' => 'Autre',
    ],

    'stock_movement_types' => [
        'entree' => 'Entrée',
        'sortie' => 'Sortie',
        'vente' => 'Vente',
        'retour' => 'Retour / annulation',
        'correction' => 'Correction',
        'transfert_sortant' => 'Transfert sortant',
        'transfert_entrant' => 'Transfert entrant',
    ],

    'default_low_stock_threshold' => 3,

    'website' => [
        'api_key' => env('GESMA_WEBSITE_API_KEY'),
        'default_store' => env('GESMA_WEBSITE_DEFAULT_STORE', 'YAM'),
    ],

    'backup' => [
        'keep_days' => (int) env('GESMA_BACKUP_KEEP_DAYS', 14),
    ],

    // Nombre maximum de clients envoyés sur l'appareil pour le mode hors connexion
    'offline_customer_limit' => 1000,
];
