<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require __DIR__ . '/db.php';

// Leer desde base de datos; si no hay datos, usar config.json solo para lectura
$cfg = db_load_settings();
if ($cfg === null || empty($cfg)) {
    $jsonFile = dirname(__DIR__) . '/config.json';
    if (file_exists($jsonFile)) {
        $cfg = json_decode(file_get_contents($jsonFile), true) ?: array();
    } else {
        $cfg = array();
    }
}

echo json_encode($cfg, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
