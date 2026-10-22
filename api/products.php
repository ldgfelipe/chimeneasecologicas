<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require __DIR__ . '/db.php';

$products = db_load_products();
if ($products === null) {
    // Fallback de solo lectura a data/products.json (si la BD no está disponible)
    $file = dirname(__DIR__) . '/data/products.json';
    $products = file_exists($file) ? (json_decode(file_get_contents($file), true) ?: array()) : array();
}

echo json_encode($products, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
