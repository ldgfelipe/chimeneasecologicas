<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require __DIR__ . '/db.php';

// Leer desde base de datos (100% BD, sin fallback a JSON)
$products = db_load_products();
if ($products === null || empty($products)) {
    $products = array();
}

echo json_encode($products, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
