<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require __DIR__ . '/db.php';

$cfg = db_load_settings();
if ($cfg === null) {
    // Fallback de solo lectura a config.json (si la BD no está disponible)
    $file = dirname(__DIR__) . '/config.json';
    $cfg = file_exists($file) ? (json_decode(file_get_contents($file), true) ?: array()) : array();
}

echo json_encode($cfg, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
