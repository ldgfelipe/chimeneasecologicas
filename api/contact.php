<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('success'=>false,'message'=>'Método no permitido'));
    exit;
}

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$message = trim($_POST['message'] ?? '');

if ($name === '' || $email === '' || $message === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(array('success'=>false,'message'=>'Completa los campos requeridos.'));
    exit;
}

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

$sent = @mail($to, $subject, $body, $headers);

// Guarda en la base de datos (fuente de verdad)
$pdo = get_db();
$savedDb = false;
if ($pdo) {
    try {
        $stmt = $pdo->prepare("INSERT INTO submissions (name, email, phone, message, sent) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(array($name, $email, $phone, $message, $sent ? 1 : 0));
        $savedDb = true;
    } catch (PDOException $e) {
        $savedDb = false;
    }
}

// Fallback to JSON
if (!$savedDb) {
    $dir = __DIR__ . '/../data';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $file = $dir . '/submissions.json';
    $subs = array();
    if (file_exists($file)) {
        $subs = json_decode(file_get_contents($file), true) ?: array();
    }
    $subs[] = array(
        'date' => date('Y-m-d H:i:s'),
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'message' => $message,
        'sent' => $sent,
    );
    file_put_contents($file, json_encode($subs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

echo json_encode(array('success'=>true,'message'=>'¡Gracias! Tu mensaje fue enviado. Te contactaremos pronto.'));
