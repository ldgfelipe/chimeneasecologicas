<?php
/**
 * Exporta los envíos del formulario a CSV.
 * Acceso: /admin/export.php (requiere sesión iniciada)
 */
session_start();
if (empty($_SESSION['auth'])) {
    header('Location: index.php');
    exit;
}

$base = dirname(__DIR__);
$subs = array();

require __DIR__ . '/../api/db.php';
$pdo = get_db();
if ($pdo) {
    try {
        $stmt = $pdo->query("SELECT date, name, email, phone, message, sent FROM submissions ORDER BY id DESC");
        $subs = $stmt->fetchAll();
    } catch (PDOException $e) {
        $subs = array();
    }
}
if (empty($subs)) {
    $file = $base . '/data/submissions.json';
    if (file_exists($file)) $subs = json_decode(file_get_contents($file), true) ?: array();
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="envios-' . date('Y-m-d') . '.csv"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // BOM para Excel
fputcsv($out, array('Fecha', 'Nombre', 'Email', 'Telefono', 'Mensaje', 'Email enviado'));
foreach ($subs as $s) {
    fputcsv($out, array(
        $s['date'] ?? '',
        $s['name'] ?? '',
        $s['email'] ?? '',
        $s['phone'] ?? '',
        $s['message'] ?? '',
        !empty($s['sent']) ? 'Si' : 'No',
    ));
}
fclose($out);
exit;
