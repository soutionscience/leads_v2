<?php

$envPath = __DIR__ . '/../.env';
if (is_file($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        $value = trim($value, "\"'");
        if (getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }
}

return [
    'app_name' => 'Leads CRM',
    'timezone' => 'Africa/Nairobi',
    'webhook_token' => getenv('LEADS_CRM_WEBHOOK_TOKEN') ?: 'change-me',
    'database' => [
        'driver' => getenv('LEADS_CRM_DB_DRIVER') ?: 'sqlite',
        'sqlite_path' => getenv('LEADS_CRM_SQLITE_PATH') ?: __DIR__ . '/../database/leads-crm.sqlite',
        'mysql' => [
            'host' => getenv('LEADS_CRM_DB_HOST') ?: 'localhost',
            'database' => getenv('LEADS_CRM_DB_NAME') ?: '',
            'username' => getenv('LEADS_CRM_DB_USER') ?: '',
            'password' => getenv('LEADS_CRM_DB_PASS') ?: '',
            'charset' => getenv('LEADS_CRM_DB_CHARSET') ?: 'utf8mb4',
        ],
    ],
];
