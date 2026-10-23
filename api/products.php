<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require __DIR__ . '/db.php';

// Leer desde base de datos; si no hay datos, usar data/products.json solo para lectura
$products = db_load_products();
if ($products === null || empty($products)) {
    $jsonFile = dirname(__DIR__) . '/data/products.json';
    if (file_exists($jsonFile)) {
        $products = json_decode(file_get_contents($jsonFile), true) ?: array();
    } else {
        $products = array();
    }
}

echo json_encode($products, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
