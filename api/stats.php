<?php

require_once __DIR__ . '/../app/helpers.php';

$pdo = db();
$today = today_sql();
$start30 = date('Y-m-d', strtotime('-29 days'));

$todayStmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN resolution = 'bought' THEN 1 ELSE 0 END) AS bought,
        SUM(CASE WHEN resolution = 'quoting' THEN 1 ELSE 0 END) AS quoting,
        SUM(CASE WHEN followup_1_done = 1 OR followup_2_done = 1 OR followup_3_done = 1 THEN 1 ELSE 0 END) AS followed
    FROM leads
    WHERE date(created_at) = ?
");
$todayStmt->execute([$today]);
$todayStats = $todayStmt->fetch();

$avgStmt = $pdo->prepare("
    SELECT COUNT(*) AS total, COUNT(DISTINCT date(created_at)) AS days
    FROM leads
    WHERE date(created_at) BETWEEN ? AND ?
");
$avgStmt->execute([$start30, $today]);
$avg = $avgStmt->fetch();

$ignoredStmt = $pdo->prepare("
    SELECT COUNT(*) AS total
    FROM call_events
    WHERE ignored = 1 AND date(occurred_at) = ?
");
$ignoredStmt->execute([$today]);
$ignored = $ignoredStmt->fetch();

$productsStmt = $pdo->query("
    SELECT product_name, COUNT(*) AS count
    FROM leads
    WHERE product_name != ''
    GROUP BY product_name
    ORDER BY count DESC
    LIMIT 10
");

$dailyStmt = $pdo->prepare("
    SELECT date(created_at) AS day, COUNT(*) AS leads,
           SUM(CASE WHEN resolution = 'bought' THEN 1 ELSE 0 END) AS bought
    FROM leads
    WHERE date(created_at) BETWEEN ? AND ?
    GROUP BY date(created_at)
    ORDER BY day DESC
");
$dailyStmt->execute([$start30, $today]);

$totalToday = (int)($todayStats['total'] ?? 0);
$boughtToday = (int)($todayStats['bought'] ?? 0);
$followedToday = (int)($todayStats['followed'] ?? 0);

json_response([
    'ok' => true,
    'today' => [
        'total' => $totalToday,
        'bought' => $boughtToday,
        'quoting' => (int)($todayStats['quoting'] ?? 0),
        'followed' => $followedToday,
        'ignored_calls' => (int)($ignored['total'] ?? 0),
        'conversion_rate' => $totalToday ? round(($boughtToday / $totalToday) * 100, 1) : 0,
        'followup_rate' => $totalToday ? round(($followedToday / $totalToday) * 100, 1) : 0,
    ],
    'average_daily_leads_30d' => (int)$avg['days'] ? round(((int)$avg['total']) / (int)$avg['days'], 1) : 0,
    'top_products' => $productsStmt->fetchAll(),
    'daily' => $dailyStmt->fetchAll(),
]);
