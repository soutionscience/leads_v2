<?php

require_once __DIR__ . '/../app/helpers.php';

$type = $_GET['type'] ?? 'products';
$q = strtolower(trim((string)($_GET['q'] ?? '')));
$file = $type === 'areas' ? 'delivery_areas.json' : 'products.json';
$items = load_data_file($file);

if ($q !== '') {
    $items = array_values(array_filter($items, function ($item) use ($q) {
        $haystack = strtolower(implode(' ', array_map('strval', $item)));
        return str_contains($haystack, $q);
    }));
}

json_response(['ok' => true, 'items' => array_slice($items, 0, 30)]);
