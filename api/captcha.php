<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store');

require __DIR__ . '/db.php';

echo json_encode(captcha_generate());
