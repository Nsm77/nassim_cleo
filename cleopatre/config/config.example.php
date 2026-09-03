<?php
// CLÉOPÂTRE — Configuration exemple (development)
// Copier vers config.php et ajuster — NE PAS commiter config.php avec secrets
return [
    'db_path' => __DIR__ . '/../database/cleopatre.sqlite',
    'app_env' => 'development', // development | production
    'app_debug' => true,
    'app_url' => 'http://localhost:8000',
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
    'setup_secret' => 'change-me-in-production-'.bin2hex(random_bytes(8)),
    'mail' => [
        'enabled' => false,
        'from' => 'noreply@para-cleopatre.tn',
        'smtp_host' => '',
        'smtp_port' => 587,
        'smtp_user' => '',
        'smtp_pass' => '',
    ],
];
