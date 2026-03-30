<?php

declare(strict_types=1);

function app_config(): array
{
    static $config;

    if ($config !== null) {
        return $config;
    }

    $secureCookie = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? null) === '443');

    $config = [
        'app_name' => getenv('APP_NAME') ?: 'Lab Inventory Portal',
        'base_url' => rtrim(getenv('APP_URL') ?: '', '/'),
        'db_host' => getenv('DB_HOST') ?: '127.0.0.1',
        'db_port' => getenv('DB_PORT') ?: '3306',
        'db_name' => getenv('DB_NAME') ?: 'lab_inventory_portal',
        'db_user' => getenv('DB_USER') ?: 'root',
        'db_pass' => getenv('DB_PASS') ?: '',
        'mail_from' => getenv('MAIL_FROM') ?: 'noreply@ipserver.in',
        'mail_enabled' => filter_var(getenv('MAIL_ENABLED') ?: 'true', FILTER_VALIDATE_BOOLEAN),
        'cookie_name' => 'lab_portal_remember',
        'cookie_secure' => $secureCookie,
        'cookie_days' => 21,
    ];

    return $config;
}
