<?php
/**
 * Migra los productos de data/products.json a la tabla `products` de MySQL.
 * Uso: https://tudominio.com/api/migrate.php?key=chimeneas2026
 */
require __DIR__ . '/db.php';

$KEY = 'chimeneas2026';
$isCli = (PHP_SAPI === 'cli');
$key = $_GET['key'] ?? '';
if (!$isCli && !hash_equals($KEY, $key)) {
    echo json_encode(array('ok' => false, 'message' => 'Clave incorrecta'));
    exit;
}

$pdo = get_db();
if (!$pdo) {
    echo json_encode(array('ok' => false, 'message' => 'Sin conexión: ' . db_last_error()));
    exit;
}

$file = dirname(__DIR__) . '/data/products.json';
$products = file_exists($file) ? (json_decode(file_get_contents($file), true) ?: array()) : array();

$ok = sync_products_db($products);
echo json_encode(array(
    'ok' => $ok,
    'message' => $ok ? ('Migrados ' . count($products) . ' productos') : 'Error al sincronizar',
), JSON_UNESCAPED_UNICODE);
