<?php
// CLÉOPÂTRE — Configuration production sécurisée
// APP_ENV et APP_DEBUG sont déterminés automatiquement : development = affichage erreurs, production = silencieux
// setup_secret DOIT être remplacé en production — s'il reste à la valeur par défaut, le setup est désactivé
return [
    'db_path' => __DIR__ . '/../database/cleopatre.sqlite',
    'app_env' => getenv('CLEO_ENV') ?: 'production',
    'app_debug' => (getenv('CLEO_ENV') ?: 'production') === 'development',
    'app_url' => getenv('CLEO_URL') ?: 'http://localhost:8000',
    'store_name' => 'Cléopâtre',
    'store_email' => 'cleopatreparapharmacie@gmail.com',
    'store_phone' => '+216 29 835 402',
    'session_name' => 'CLEO_SESS',
    'session_lifetime' => 86400 * 7, // 7 jours
    'csrf_enabled' => true,
    'rate_limit' => [
        'login' => ['max' => 10, 'window' => 900], // 10 tentatives / 15 min
        'register' => ['max' => 5, 'window' => 3600],
        'contact' => ['max' => 5, 'window' => 3600],
    ],
    'shipping' => [
        'free_threshold' => 99000, // 99 DT millimes
        'cost' => 8000, // 8 DT
    ],
    'order_prefix' => 'CLEO',
    // IMPORTANT: remplacer en production. Si laissé à 'change-me-...', le setup est bloqué.
    'setup_secret' => getenv('CLEO_SETUP_SECRET') ?: 'change-me-in-production-' . 'cleo-setup-2026-change-in-prod',
    'setup_enabled' => false, // désactivé après installation initiale — passer à true temporairement pour créer le premier Super Admin
    'mail' => [
        'enabled' => false,
        'from' => 'noreply@para-cleopatre.tn',
        'smtp_host' => '',
        'smtp_port' => 587,
        'smtp_user' => '',
        'smtp_pass' => '',
    ],
];
