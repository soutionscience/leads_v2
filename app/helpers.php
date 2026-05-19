<?php

require_once __DIR__ . '/db.php';

function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

function read_json_body(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);
    if (is_array($data)) {
        return $data;
    }
    return $_POST ?: $_GET;
}

function normalize_phone(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone);
    if (str_starts_with($digits, '0') && strlen($digits) === 10) {
        return '254' . substr($digits, 1);
    }
    if (str_starts_with($digits, '7') && strlen($digits) === 9) {
        return '254' . $digits;
    }
    if (str_starts_with($digits, '1') && strlen($digits) === 9) {
        return '254' . $digits;
    }
    return $digits;
}

function require_webhook_token(array $payload): void
{
    $expected = app_config()['webhook_token'];
    $provided = $payload['token'] ?? ($_SERVER['HTTP_X_WEBHOOK_TOKEN'] ?? '');
    if ($expected !== '' && $provided !== $expected) {
        json_response(['ok' => false, 'error' => 'Invalid webhook token'], 401);
    }
}

function now_sql(): string
{
    return date('Y-m-d H:i:s');
}

function today_sql(): string
{
    return date('Y-m-d');
}

function load_data_file(string $file): array
{
    $path = __DIR__ . '/../data/' . $file;
    if (!is_file($path)) {
        return [];
    }
    $data = json_decode(file_get_contents($path), true);
    return is_array($data) ? $data : [];
}
