<?php

require_once __DIR__ . '/../app/helpers.php';

$payload = read_json_body();
$action = $_GET['action'] ?? ($payload['action'] ?? 'incoming');
require_webhook_token($payload);

$phone = trim((string)($payload['phone'] ?? $payload['number'] ?? ''));
if ($phone === '') {
    json_response(['ok' => false, 'error' => 'Phone number is required'], 422);
}

$phoneNormalized = normalize_phone($phone);
$pdo = db();

$contactStmt = $pdo->prepare('SELECT * FROM contacts WHERE phone_normalized = ?');
$contactStmt->execute([$phoneNormalized]);
$contact = $contactStmt->fetch();

if ($action === 'incoming' || $action === 'ringing' || $action === 'started') {
    $ignored = $contact && (int)$contact['ignored'] === 1;
    $ignoreReason = $ignored ? $contact['type'] : null;
    $leadId = null;

    if (!$ignored) {
        $leadStmt = $pdo->prepare("
            INSERT INTO leads (
                contact_id, phone_normalized, phone_display, customer_name, source,
                call_started_at, created_at, updated_at
            ) VALUES (?, ?, ?, ?, 'phone', ?, ?, ?)
        ");
        $now = now_sql();
        $leadStmt->execute([
            $contact['id'] ?? null,
            $phoneNormalized,
            $phone,
            $contact['name'] ?? '',
            $now,
            $now,
            $now,
        ]);
        $leadId = (int)$pdo->lastInsertId();
    }

    $eventStmt = $pdo->prepare("
        INSERT INTO call_events (
            lead_id, contact_id, phone_normalized, phone_display, event_type,
            ignored, ignore_reason, payload
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $eventStmt->execute([
        $leadId,
        $contact['id'] ?? null,
        $phoneNormalized,
        $phone,
        $action,
        $ignored ? 1 : 0,
        $ignoreReason,
        json_encode($payload),
    ]);

    json_response([
        'ok' => true,
        'ignored' => $ignored,
        'ignore_reason' => $ignoreReason,
        'lead_id' => $leadId,
        'contact' => $contact ?: null,
    ]);
}

if ($action === 'ended') {
    $duration = (int)($payload['duration_seconds'] ?? $payload['duration'] ?? 0);
    $stmt = $pdo->prepare("
        SELECT * FROM leads
        WHERE phone_normalized = ? AND call_ended_at IS NULL
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$phoneNormalized]);
    $lead = $stmt->fetch();

    if ($lead) {
        $update = $pdo->prepare("
            UPDATE leads
            SET call_ended_at = ?, call_duration_seconds = ?, updated_at = ?
            WHERE id = ?
        ");
        $now = now_sql();
        $update->execute([$now, $duration, $now, $lead['id']]);
    }

    $eventStmt = $pdo->prepare("
        INSERT INTO call_events (
            lead_id, contact_id, phone_normalized, phone_display, event_type,
            ignored, ignore_reason, payload
        ) VALUES (?, ?, ?, ?, 'ended', ?, ?, ?)
    ");
    $ignored = $contact && (int)$contact['ignored'] === 1;
    $eventStmt->execute([
        $lead['id'] ?? null,
        $contact['id'] ?? null,
        $phoneNormalized,
        $phone,
        $ignored ? 1 : 0,
        $ignored ? $contact['type'] : null,
        json_encode($payload),
    ]);

    json_response(['ok' => true, 'lead_id' => $lead['id'] ?? null, 'duration_seconds' => $duration]);
}

json_response(['ok' => false, 'error' => 'Unsupported action'], 400);
