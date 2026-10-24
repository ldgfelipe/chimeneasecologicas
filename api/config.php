<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require __DIR__ . '/db.php';

// Leer desde base de datos (100% BD, sin fallback a JSON)
$cfg = db_load_settings();
if ($cfg === null || empty($cfg)) {
    $cfg = array();
}

echo json_encode($cfg, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
