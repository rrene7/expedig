<?php
return [
    'app_name' => 'EXPEDIG - Expedientes Digitales',
    'base_url' => 'http://localhost/expedig',
    'timezone' => 'America/Panama',
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'expedig',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],
    'storage_path' => __DIR__ . '/storage/private',
    'max_upload_bytes' => 20 * 1024 * 1024,
];
