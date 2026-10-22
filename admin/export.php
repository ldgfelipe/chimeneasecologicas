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

require __DIR__ . '/../api/db.php';
$subs = db_load_submissions();
if ($subs === null) {
    $file = dirname(__DIR__) . '/data/submissions.json';
    $subs = file_exists($file) ? (json_decode(file_get_contents($file), true) ?: array()) : array();
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="envios-' . date('Y-m-d') . '.csv"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // BOM para Excel
fputcsv($out, array('Fecha', 'Nombre', 'Email', 'Telefono', 'Producto', 'Interes', 'Fecha cita', 'Mensaje', 'Acepta privacidad', 'Acepta publicidad'));
foreach ($subs as $s) {
    fputcsv($out, array(
        $s['date'] ?? '',
        $s['name'] ?? '',
        $s['email'] ?? '',
        $s['phone'] ?? '',
        $s['product'] ?? '',
        ($s['interest'] ?? '') === 'cita' ? 'Cita showroom' : 'Informacion',
        $s['appointment_date'] ?? '',
        $s['message'] ?? '',
        !empty($s['consent_privacy']) ? 'Si' : 'No',
        !empty($s['consent_ads']) ? 'Si' : 'No',
    ));
}
fclose($out);
exit;
