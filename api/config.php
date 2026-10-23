<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require __DIR__ . '/db.php';

// Base: config.json (respaldo/valores por defecto)
$file = dirname(__DIR__) . '/config.json';
$jsonCfg = file_exists($file) ? (json_decode(file_get_contents($file), true) ?: array()) : array();

// BD: sobreescribe la base si está disponible (merge para no perder claves)
$dbCfg = db_load_settings();
$cfg = $dbCfg !== null ? array_merge($jsonCfg, $dbCfg) : $jsonCfg;

echo json_encode($cfg, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
