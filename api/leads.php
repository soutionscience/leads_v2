<?php

require_once __DIR__ . '/../app/helpers.php';

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $range = $_GET['range'] ?? 'today';
    $start = $_GET['start'] ?? null;
    $end = $_GET['end'] ?? null;

    if ($range === 'yesterday') {
        $start = $end = date('Y-m-d', strtotime('-1 day'));
    } elseif ($range === '2days') {
        $start = $end = date('Y-m-d', strtotime('-2 day'));
    } elseif ($range === '3days') {
        $start = $end = date('Y-m-d', strtotime('-3 day'));
    } elseif ($range === 'followups') {
        $start = null;
        $end = null;
    } elseif ($range !== 'custom') {
        $start = $end = today_sql();
    }

    if ($range === 'followups') {
        $stmt = $pdo->query("
            SELECT l.*, c.type AS contact_type, c.ignored AS contact_ignored
            FROM leads l
            LEFT JOIN contacts c ON c.id = l.contact_id
            WHERE l.resolution != 'bought'
              AND (l.followup_1_done = 0 OR l.followup_2_done = 0 OR l.followup_3_done = 0)
            ORDER BY l.created_at DESC
            LIMIT 300
        ");
    } else {
        $stmt = $pdo->prepare("
            SELECT l.*, c.type AS contact_type, c.ignored AS contact_ignored
            FROM leads l
            LEFT JOIN contacts c ON c.id = l.contact_id
            WHERE date(l.created_at) BETWEEN ? AND ?
            ORDER BY l.created_at DESC
            LIMIT 300
        ");
        $stmt->execute([$start, $end]);
    }

    json_response(['ok' => true, 'leads' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    $payload = read_json_body();
    $phone = trim((string)($payload['phone'] ?? ''));
    if ($phone === '') {
        json_response(['ok' => false, 'error' => 'Phone is required'], 422);
    }

    $normalized = normalize_phone($phone);
    $now = now_sql();
    $stmt = $pdo->prepare("
        INSERT INTO leads (
            phone_normalized, phone_display, customer_name, source, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $normalized,
        $phone,
        trim((string)($payload['customer_name'] ?? '')),
        $payload['source'] ?? 'manual',
        $now,
        $now,
    ]);

    json_response(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
}

if ($method === 'PUT' || $method === 'PATCH') {
    $payload = read_json_body();
    $id = (int)($payload['id'] ?? 0);
    if ($id <= 0) {
        json_response(['ok' => false, 'error' => 'Lead id is required'], 422);
    }

    $fields = [
        'customer_name', 'product_name', 'product_price', 'quoted_amount',
        'delivery_area', 'delivery_fee', 'resolution', 'notes',
        'followup_1_done', 'followup_2_done', 'followup_3_done', 'next_followup_at'
    ];

    $sets = [];
    $values = [];
    foreach ($fields as $field) {
        if (array_key_exists($field, $payload)) {
            $sets[] = "{$field} = ?";
            $values[] = $payload[$field];
        }
    }
    if (!$sets) {
        json_response(['ok' => false, 'error' => 'No fields to update'], 422);
    }
    $sets[] = 'updated_at = ?';
    $values[] = now_sql();
    $values[] = $id;

    $stmt = $pdo->prepare('UPDATE leads SET ' . implode(', ', $sets) . ' WHERE id = ?');
    $stmt->execute($values);

    $leadStmt = $pdo->prepare('SELECT * FROM leads WHERE id = ?');
    $leadStmt->execute([$id]);
    json_response(['ok' => true, 'lead' => $leadStmt->fetch()]);
}

json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
