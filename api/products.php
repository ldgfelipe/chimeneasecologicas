<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
$file = dirname(__DIR__) . '/data/products.json';
if (!file_exists($file)) { echo json_encode(array()); exit; }
echo file_get_contents($file);
