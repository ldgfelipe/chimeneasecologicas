<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require __DIR__ . '/db.php';
$pdo = get_db();
$products = array();

if ($pdo) {
    try {
        $stmt = $pdo->query("SELECT id, name, category, price, specs, description, details, image, images, link FROM products ORDER BY id ASC");
        $products = $stmt->fetchAll();
        foreach ($products as &$row) {
            $row['id'] = (int)$row['id'];
            // Decodifica la galería de imágenes (JSON) a array
            if (!empty($row['images'])) {
                $decoded = json_decode($row['images'], true);
                $row['images'] = is_array($decoded) ? $decoded : array();
            } else {
                $row['images'] = !empty($row['image']) ? array($row['image']) : array();
            }
        }
        unset($row);
    } catch (PDOException $e) {
        $products = array();
    }
}

if (empty($products)) {
    $file = dirname(__DIR__) . '/data/products.json';
    if (file_exists($file)) {
        $products = json_decode(file_get_contents($file), true) ?: array();
    }
}

echo json_encode($products, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
