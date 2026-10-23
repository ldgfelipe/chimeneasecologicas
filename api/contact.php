<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store');

function respond($ok, $msg) {
    if ($ok) {
        header('Location: ../gracias.html');
    } else {
        header('Location: ../index.html?status=error&msg=' . urlencode($msg) . '#contacto');
    }
    exit;
}

// Acepta GET o POST (algunos hostings/WAF bloquean POST; usamos GET por defecto)
if (!in_array($_SERVER['REQUEST_METHOD'], array('GET', 'POST'), true)) {
    respond(false, 'Método no permitido');
}

$input = ($_SERVER['REQUEST_METHOD'] === 'POST') ? $_POST : $_GET;

// 1) Honeypot (campos trampa invisibles): si un bot los llena, el envío se descarta (no se guarda)
if (trim($input['website'] ?? '') !== '' || trim($input['company'] ?? '') !== '') {
    respond(false, 'No se pudo procesar tu solicitud.');
}

$name = trim($input['name'] ?? '');
$email = trim($input['email'] ?? '');
$phone = trim($input['phone'] ?? '');
$message = trim($input['message'] ?? '');
$product = trim($input['product'] ?? '');
$interest = trim($input['interest'] ?? 'informacion');
$appointmentDate = trim($input['appointment_date'] ?? '');
$consentPrivacy = ($input['consent_privacy'] ?? '') === '1';
$consentAds = ($input['consent_ads'] ?? '') === '1';

// 2) Validación de campos requeridos
if ($name === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(false, 'Ingresa un nombre y un correo válido.');
}
if ($message === '') {
    respond(false, 'Escribe tu mensaje.');
}
if ($product === '') {
    respond(false, 'Selecciona un producto del listado.');
}
if ($interest === 'cita' && $appointmentDate === '') {
    respond(false, 'Indica la fecha disponible para tu cita.');
}
if (!$consentPrivacy) {
    respond(false, 'Debes aceptar el aviso de privacidad.');
}
if (!$consentAds) {
    respond(false, 'Debes indicar si aceptas recibir información publicitaria.');
}

// 3) CAPTCHA matemático
try {
    require __DIR__ . '/db.php';
    $okCaptcha = captcha_verify(
        (int)($input['captcha_a'] ?? 0),
        (int)($input['captcha_b'] ?? 0),
        (int)($input['captcha_answer'] ?? 0),
        (string)($input['captcha_token'] ?? ''),
        (int)($input['captcha_time'] ?? 0)
    );
} catch (Throwable $e) {
    $okCaptcha = false;
}
if (!$okCaptcha) {
    respond(false, 'La verificación de seguridad es incorrecta. Intenta de nuevo.');
}

// 4) Guardar en la base de datos (único almacenamiento)
try {
    $pdo = get_db();
    if (!$pdo) {
        respond(false, 'Error de conexión con la base de datos. Intenta por WhatsApp.');
    }
    $stmt = $pdo->prepare("INSERT INTO submissions (name, email, phone, product, interest, appointment_date, message, consent_privacy, consent_ads, sent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0)");
    $stmt->execute(array($name, $email, $phone, $product, $interest, ($interest === 'cita' ? $appointmentDate : ''), $message, $consentPrivacy ? 1 : 0, $consentAds ? 1 : 0));

    respond(true, '¡Gracias! Tu mensaje fue recibido. Te contactaremos pronto.');
} catch (Throwable $e) {
    respond(false, 'Error del servidor: ' . $e->getMessage());
}
