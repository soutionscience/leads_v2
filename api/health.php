<?php

require_once __DIR__ . '/../app/helpers.php';

$config = app_config();
$dbConfig = $config['database'];
$envPath = realpath(__DIR__ . '/../.env');

$checks = [
    'env_loaded' => $envPath !== false,
    'db_driver' => $dbConfig['driver'],
    'db_host' => $dbConfig['mysql']['host'] ?? null,
    'db_name_set' => !empty($dbConfig['mysql']['database']),
    'db_user_set' => !empty($dbConfig['mysql']['username']),
    'pdo_mysql_loaded' => in_array('mysql', PDO::getAvailableDrivers(), true),
    'pdo_sqlite_loaded' => in_array('sqlite', PDO::getAvailableDrivers(), true),
];

try {
    db();
    $checks['database_connection'] = true;
    json_response(['ok' => true, 'checks' => $checks]);
} catch (Throwable $error) {
    $checks['database_connection'] = false;
    json_response([
        'ok' => false,
        'error' => $error->getMessage(),
        'checks' => $checks,
    ], 500);
}
