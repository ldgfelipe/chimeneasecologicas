<?php
/**
 * Instalador / diagnóstico de la base de datos.
 *
 * Uso:  https://tudominio.com/api/install.php?key=chimeneas2026
 *
 * - Crea la base de datos y las tablas (submissions, products)
 * - Migra los productos de data/products.json a la tabla `products`
 * - Muestra el estado de la conexión
 *
 * IMPORTANTE: elimina o renombra este archivo cuando termines.
 */
require __DIR__ . '/db.php';

$KEY = 'chimeneas2026';
$isCli = (PHP_SAPI === 'cli');
$key = $_GET['key'] ?? ($_POST['key'] ?? '');
$authorized = $isCli || hash_equals($KEY, $key);

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es"><head><meta charset="utf-8"><title>Instalador BD - Chimeneas Ecológicas</title>
<style>
body{font-family:system-ui,Segoe UI,sans-serif;background:#faf6f0;margin:0;padding:32px;color:#2b1f1a;}
.card{max-width:760px;margin:0 auto;background:#fff;border-radius:16px;padding:32px;box-shadow:0 12px 40px rgba(43,31,26,.08);}
h1{font-size:1.5rem;margin:0 0 6px;}
.ok{color:#2e7d32;font-weight:600;}
.err{color:#c62828;font-weight:600;}
code{background:#f3ede4;padding:2px 6px;border-radius:6px;}
table{width:100%;border-collapse:collapse;margin-top:16px;font-size:.9rem;}
td,th{padding:8px;border-bottom:1px solid #eee;text-align:left;}
</style></head>
<body><div class="card">
<h1>Instalador de base de datos</h1>
<p>Chimeneas Ecológicas &mdash; sitio web</p>
<hr>

<?php if (!$authorized): ?>
  <p class="err">Acceso denegado.</p>
  <p>Usa la clave de instalación: <code>?key=<?php echo htmlspecialchars($KEY); ?></code></p>
<?php else: ?>
  <?php
  $server = db_server();
  echo '<p><strong>Servidor MySQL:</strong> ';
  if ($server) {
      $ver = $server->query('SELECT VERSION()')->fetchColumn();
      echo '<span class="ok">conectado (' . htmlspecialchars($ver) . ')</span>';
  } else {
      echo '<span class="err">sin conexión</span>';
  }
  echo '</p>';

  echo '<p><strong>Base de datos <code>' . htmlspecialchars(DB_NAME) . '</code>:</strong> ';
  $pdo = get_db();
  if ($pdo) {
      echo '<span class="ok">conectada</span>';
  } else {
      echo '<span class="err">no disponible &mdash; ' . htmlspecialchars(db_last_error()) . '</span>';
  }
  echo '</p>';

  $res = db_install();
  echo '<p><strong>Tablas:</strong> <span class="' . ($res['ok'] ? 'ok' : 'err') . '">' . htmlspecialchars($res['msg']) . '</span></p>';

  if ($pdo) {
      // Sembrar configuración y productos desde los JSON (una sola vez)
      $seed = db_seed();
      echo '<p><strong>Siembra de datos:</strong> <span class="' . ($seed['ok'] ? 'ok' : 'err') . '">' . htmlspecialchars($seed['msg']);
      if ($seed['products'] > 0) echo ' · ' . $seed['products'] . ' productos';
      if ($seed['settings'] > 0) echo ' · ' . $seed['settings'] . ' configs';
      echo '</span></p>';

      // Estado de tablas
      echo '<table><tr><th>Tabla</th><th>Registros</th></tr>';
      foreach (array('products', 'submissions', 'settings') as $t) {
          try {
              $n = $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
          } catch (PDOException $e) {
              $n = '—';
          }
          echo '<tr><td>' . $t . '</td><td>' . $n . '</td></tr>';
      }
      echo '</table>';
  }
  ?>
  <p style="margin-top:24px" class="ok">Listo. <strong>Elimina este archivo</strong> (<code>api/install.php</code>) por seguridad.</p>
<?php endif; ?>
</div></body></html>
