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

// Load config
$cfg = json_decode(file_get_contents(__DIR__ . '/../config.json'), true);
$to = $cfg['email'] ?? 'hola@chimeneasecologicas.com';
$subject = "Nueva cotización de $name";
$body = "Nombre: $name\nEmail: $email\nTeléfono: $phone\n\nMensaje:\n$message";
$headers = "From: noreply@chimeneasecologicas.com.mx\r\nReply-To: $email\r\n";

$sent = @mail($to, $subject, $body, $headers);

// Store submission
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

echo json_encode(array('success'=>true,'message'=>'¡Gracias! Tu mensaje fue enviado. Te contactaremos pronto.'));
