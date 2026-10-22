<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store');

// Respuesta JSON garantizada (nunca HTML/error fatal que rompa el fetch)
function respond($ok, $msg) {
    echo json_encode(array('success' => (bool)$ok, 'message' => $msg), JSON_UNESCAPED_UNICODE);
    exit;
}

// Acepta GET o POST (algunos hostings/WAF bloquean POST; usamos GET por defecto)
if (!in_array($_SERVER['REQUEST_METHOD'], array('GET', 'POST'), true)) {
    respond(false, 'Método no permitido');
}

$input = ($_SERVER['REQUEST_METHOD'] === 'POST') ? $_POST : $_GET;

$name = trim($input['name'] ?? '');
$email = trim($input['email'] ?? '');
$phone = trim($input['phone'] ?? '');
$message = trim($input['message'] ?? '');

if ($name === '' || $email === '' || $message === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(false, 'Completa los campos requeridos.');
}

try {
    require __DIR__ . '/db.php';

    // Guarda el mensaje en la base de datos (fuente de verdad)
    $pdo = get_db();
    $savedDb = false;
    if ($pdo) {
        try {
            $stmt = $pdo->prepare("INSERT INTO submissions (name, email, phone, message, sent) VALUES (?, ?, ?, ?, 0)");
            $stmt->execute(array($name, $email, $phone, $message));
            $savedDb = true;
        } catch (PDOException $e) {
            $savedDb = false;
        }
    }

    // Respaldo a JSON solo si la BD no está disponible
    if (!$savedDb) {
        $dir = __DIR__ . '/../data';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        $file = $dir . '/submissions.json';
        $subs = array();
        if (file_exists($file)) {
            $subs = json_decode(file_get_contents($file), true) ?: array();
        }
        $subs[] = array('date' => date('Y-m-d H:i:s'), 'name' => $name, 'email' => $email, 'phone' => $phone, 'message' => $message, 'sent' => 0);
        @file_put_contents($file, json_encode($subs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    respond(true, '¡Gracias! Tu mensaje fue recibido. Te contactaremos pronto.');
} catch (Throwable $e) {
    respond(false, 'Error del servidor: ' . $e->getMessage());
}
