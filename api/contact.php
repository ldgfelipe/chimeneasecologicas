<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store');

// Respuesta JSON garantizada (nunca HTML/error fatal que rompa el fetch)
function respond($ok, $msg) {
    echo json_encode(array('success' => (bool)$ok, 'message' => $msg), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Método no permitido');
}

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$message = trim($_POST['message'] ?? '');

if ($name === '' || $email === '' || $message === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(false, 'Completa los campos requeridos.');
}

try {
    require __DIR__ . '/db.php';

    $cfg = db_load_settings();
    if ($cfg === null) {
        $file = __DIR__ . '/../config.json';
        $cfg = file_exists($file) ? (json_decode(file_get_contents($file), true) ?: array()) : array();
    }
    $to = $cfg['email'] ?? 'hola@chimeneasecologicas.com';
    $subject = "Nueva cotización de $name";
    $body = "Nombre: $name\nEmail: $email\nTeléfono: $phone\n\nMensaje:\n$message";
    $headers = "From: noreply@chimeneasecologicas.com.mx\r\nReply-To: $email\r\n";

    // 1) Guardar en la base de datos (fuente de verdad)
    $pdo = get_db();
    $savedDb = false;
    if ($pdo) {
        try {
            $stmt = $pdo->prepare("INSERT INTO submissions (name, email, phone, message, sent) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute(array($name, $email, $phone, $message, 0));
            $savedDb = true;
        } catch (PDOException $e) {
            $savedDb = false;
        }
    }

    // 2) Fallback a JSON solo si la BD no está disponible
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

    // 3) Enviar email (sin bloquear la respuesta)
    $sent = @mail($to, $subject, $body, $headers);
    if ($savedDb && $pdo) {
        try { $pdo->prepare("UPDATE submissions SET sent = ? WHERE id = LAST_INSERT_ID()")->execute(array($sent ? 1 : 0)); } catch (PDOException $e) {}
    }

    respond(true, '¡Gracias! Tu mensaje fue enviado. Te contactaremos pronto.');
} catch (Throwable $e) {
    respond(false, 'Error del servidor: ' . $e->getMessage());
}
