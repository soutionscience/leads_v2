<?php

require_once __DIR__ . '/../app/helpers.php';

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $q = trim((string)($_GET['q'] ?? ''));
    if ($q !== '') {
        $like = '%' . $q . '%';
        $stmt = $pdo->prepare("
            SELECT * FROM contacts
            WHERE phone_display LIKE ? OR phone_normalized LIKE ? OR name LIKE ? OR type LIKE ?
            ORDER BY updated_at DESC
            LIMIT 100
        ");
        $stmt->execute([$like, $like, $like, $like]);
    } else {
        $stmt = $pdo->query('SELECT * FROM contacts ORDER BY updated_at DESC LIMIT 200');
    }
    json_response(['ok' => true, 'contacts' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    $payload = read_json_body();
    $phone = trim((string)($payload['phone'] ?? $payload['phone_display'] ?? ''));
    if ($phone === '') {
        json_response(['ok' => false, 'error' => 'Phone is required'], 422);
    }

    $type = $payload['type'] ?? 'customer';
    $ignoredTypes = ['supplier', 'delivery', 'staff', 'spam'];
    $ignored = in_array($type, $ignoredTypes, true) || !empty($payload['ignored']);
    $normalized = normalize_phone($phone);
    $now = now_sql();

    $driver = app_config()['database']['driver'];
    if ($driver === 'mysql') {
        $stmt = $pdo->prepare("
            INSERT INTO contacts (
                phone_normalized, phone_display, name, type, ignored, notes,
                external_source, external_contact_id, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                phone_display = VALUES(phone_display),
                name = VALUES(name),
                type = VALUES(type),
                ignored = VALUES(ignored),
                notes = VALUES(notes),
                external_source = VALUES(external_source),
                external_contact_id = VALUES(external_contact_id),
                updated_at = VALUES(updated_at)
        ");
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO contacts (
                phone_normalized, phone_display, name, type, ignored, notes,
                external_source, external_contact_id, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON CONFLICT(phone_normalized) DO UPDATE SET
                phone_display = excluded.phone_display,
                name = excluded.name,
                type = excluded.type,
                ignored = excluded.ignored,
                notes = excluded.notes,
                external_source = excluded.external_source,
                external_contact_id = excluded.external_contact_id,
                updated_at = excluded.updated_at
        ");
    }

    $stmt->execute([
            $normalized,
            $phone,
            trim((string)($payload['name'] ?? '')),
            $type,
            $ignored ? 1 : 0,
            $payload['notes'] ?? '',
            $payload['external_source'] ?? null,
            $payload['external_contact_id'] ?? null,
            $now,
            $now,
    ]);

    $contactStmt = $pdo->prepare('SELECT * FROM contacts WHERE phone_normalized = ?');
    $contactStmt->execute([$normalized]);
    json_response(['ok' => true, 'contact' => $contactStmt->fetch()]);
}

json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
