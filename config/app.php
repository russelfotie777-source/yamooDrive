<?php
// Réglages du site. Les secrets viennent du fichier .env (voir .env.exemple).

return [
    'nom' => 'Yamoo Drive',
    'fuseau' => 'Africa/Douala',

    'contact' => [
        'whatsapp' => '237672894084',
        'telephone' => '+237672894084',
        'telephone_affiche' => '+237 672 89 40 84',
        'email' => 'contact@yamoogroup.com',
    ],

    // Conditions générales de location de yamoodrive.com.
    'regles' => [
        'tolerance_retour_minutes' => 60,
        'annulation_gratuite_heures' => 24,
        'age_minimum' => 23,
        'anciennete_permis_ans' => 2,
        // Délais de fonctionnement choisis pour la démonstration, à confirmer avec l'équipe.
        'delai_minimum_heures' => 2,
        'duree_max_jours' => 60,
        'attente_paiement_minutes' => 30,
    ],

    // Montants provisoires : Yamoo Drive doit valider ces chiffres avant la mise en ligne.
    'montants' => [
        'caution_sans_chauffeur' => 200000,
        'rapatriement' => [
            'douala|yaounde' => 40000,
            'bafoussam|douala' => 50000,
            'bafoussam|yaounde' => 50000,
        ],
    ],

    'villes' => [
        'douala' => 'Douala',
        'yaounde' => 'Yaoundé',
        'bafoussam' => 'Bafoussam',
    ],

    'lieux' => [
        'douala-agence' => ['ville' => 'douala', 'nom' => 'Agence de Douala', 'court' => 'Douala', 'detail' => 'Logpom, carrefour Adem'],
        'douala-aeroport' => ['ville' => 'douala', 'nom' => 'Aéroport international de Douala', 'court' => 'Aéroport de Douala', 'detail' => 'Livraison du véhicule à votre arrivée'],
        'yaounde-agence' => ['ville' => 'yaounde', 'nom' => 'Agence de Yaoundé', 'court' => 'Yaoundé', 'detail' => 'Prise en charge à l’agence'],
        'yaounde-aeroport' => ['ville' => 'yaounde', 'nom' => 'Aéroport de Yaoundé-Nsimalen', 'court' => 'Aéroport de Nsimalen', 'detail' => 'Livraison du véhicule à votre arrivée'],
        'bafoussam-agence' => ['ville' => 'bafoussam', 'nom' => 'Agence de Bafoussam', 'court' => 'Bafoussam', 'detail' => 'Prise en charge et retour à l’agence'],
    ],

    'categories' => [
        'suv' => 'SUV',
        'prestige' => '4x4 de prestige',
        'berline' => 'Berlines',
        'van' => 'Vans et minibus',
        'pickup' => 'Pick-up',
    ],

    'missions' => [
        'ville' => 'En ville',
        'aeroport' => 'Transfert aéroport',
        'mariage' => 'Mariage ou cérémonie',
        'entreprise' => 'Entreprise ou délégation',
        'voyage' => 'Voyage hors de la ville',
    ],

    'paiement' => [
        // « demo » simule MTN Mobile Money et Orange Money ; « notchpay » encaisse pour de vrai.
        'fournisseur' => env('PAIEMENT_FOURNISSEUR', 'demo'),
        'notchpay_cle' => env('NOTCHPAY_CLE_PUBLIQUE'),
        'notchpay_hash' => env('NOTCHPAY_HASH_WEBHOOK'),
    ],

    'assistant' => [
        'modele' => 'claude-opus-5-5',
        'effort' => 'low',
        'messages_par_session' => 40,
    ],

    'admin' => [
        'mot_de_passe' => env('ADMIN_MOT_DE_PASSE'),
    ],
];
