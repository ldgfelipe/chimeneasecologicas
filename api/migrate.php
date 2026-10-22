<?php
/**
 * Migra los datos de los archivos JSON a la base de datos (config + productos).
 * Uso: https://tudominio.com/api/migrate.php?key=chimeneas2026
 */
require __DIR__ . '/db.php';

$KEY = 'chimeneas2026';
$isCli = (PHP_SAPI === 'cli');
$key = $_GET['key'] ?? '';
header('Content-Type: application/json; charset=utf-8');

if (!$isCli && !hash_equals($KEY, $key)) {
    echo json_encode(array('ok' => false, 'message' => 'Clave incorrecta'));
    exit;
}

$pdo = get_db();
if (!$pdo) {
    echo json_encode(array('ok' => false, 'message' => 'Sin conexión: ' . db_last_error()));
    exit;
}

db_install();

$seed = db_seed();

$base = dirname(__DIR__);
// Forzar guardado de config + productos actuales (por si la BD ya tenía datos)
$cfg = file_exists($base . '/config.json') ? (json_decode(file_get_contents($base . '/config.json'), true) ?: array()) : array();
$cfgOk = db_save_settings($cfg);
$products = file_exists($base . '/data/products.json') ? (json_decode(file_get_contents($base . '/data/products.json'), true) ?: array()) : array();
$prodOk = sync_products_db($products);

echo json_encode(array(
    'ok' => ($cfgOk && $prodOk),
    'message' => ($cfgOk && $prodOk)
        ? ('Guardado en la BD: ' . count($cfg) . ' configuraciones y ' . count($products) . ' productos')
        : 'Error al guardar en la BD',
), JSON_UNESCAPED_UNICODE);
