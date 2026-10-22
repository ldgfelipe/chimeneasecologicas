<?php
/**
 * Diagnóstico del formulario de contacto.
 * Uso: https://tudominio.com/api/test.php
 * - Muestra la versión de PHP y el estado de la BD.
 * - Acepta POST y muestra si los datos llegan al servidor.
 *
 * IMPORTANTE: elimina este archivo cuando termines de diagnosticar.
 */
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es"><head><meta charset="utf-8"><title>Diagnóstico - Chimeneas</title>
<style>body{font-family:system-ui,sans-serif;background:#faf7f2;padding:32px;color:#2a1b10;max-width:760px;margin:0 auto;}
.card{background:#fff;border-radius:14px;padding:24px;box-shadow:0 8px 24px rgba(42,27,16,.08);margin-bottom:16px;}
.ok{color:#2e7d32;font-weight:700;}.err{color:#c62828;font-weight:700;}
table{width:100%;border-collapse:collapse;font-size:.9rem;}td,th{padding:8px;border-bottom:1px solid #eee;text-align:left;}
</style></head><body>
<h1>Diagnóstico del formulario</h1>

<div class="card">
<h2>Entorno</h2>
<p>PHP: <span class="ok"><?php echo phpversion(); ?></span></p>
<?php
require __DIR__ . '/db.php';
$pdo = get_db();
echo '<p>Base de datos <code>' . htmlspecialchars(DB_NAME) . '</code>: ';
if ($pdo) { echo '<span class="ok">conectada</span>'; }
else { echo '<span class="err">sin conexión — ' . htmlspecialchars(db_last_error()) . '</span>'; }
echo '</p>';
?>
</div>

<div class="card">
<h2>Prueba de envío (POST)</h2>
<?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
  <p class="ok">El servidor recibió el POST correctamente.</p>
  <table><tr><th>Campo</th><th>Valor</th></tr>
  <?php foreach (array('name','email','phone','message') as $k): ?>
    <tr><td><?php echo $k; ?></td><td><?php echo htmlspecialchars($_POST[$k] ?? '(vacío)'); ?></td></tr>
  <?php endforeach; ?>
  </table>
<?php else: ?>
  <p>Envía un POST aquí para verificar que los datos llegan.</p>
  <form method="post">
    <input type="text" name="name" placeholder="Nombre"><br><br>
    <input type="text" name="email" placeholder="Email"><br><br>
    <textarea name="message" placeholder="Mensaje"></textarea><br><br>
    <button type="submit">Probar POST</button>
  </form>
<?php endif; ?>
</div>
</body></html>
