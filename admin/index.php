<?php
session_start();

function convert_to_webp($src, $dest, $quality = 82) {
    $info = getimagesize($src);
    if (!$info) return false;
    $mime = $info['mime'];
    switch ($mime) {
        case 'image/jpeg': $img = imagecreatefromjpeg($src); break;
        case 'image/png': $img = imagecreatefrompng($src); break;
        case 'image/gif': $img = imagecreatefromgif($src); break;
        case 'image/webp': $img = imagecreatefromwebp($src); break;
        default: return false;
    }
    if (!$img) return false;
    imagewebp($img, $dest, $quality);
    imagedestroy($img);
    return true;
}
$base = dirname(__DIR__);
$configFile = $base . '/config.json';
$subsFile = $base . '/data/submissions.json';
$prodFile = $base . '/data/products.json';
$passFile = __DIR__ . '/.password';
$imgDir = $base . '/assets/img';

if (isset($_POST['login'])) {
    $pw = trim($_POST['password'] ?? '');
    $hash = file_exists($passFile) ? trim(file_get_contents($passFile)) : '';
    $ok = false;
    if ($hash && password_verify($pw, $hash)) {
        $ok = true;
    } elseif (!$hash && $pw === 'admin123') {
        // First-time setup: create password file
        file_put_contents($passFile, password_hash('admin123', PASSWORD_DEFAULT));
        $ok = true;
    }
    if ($ok) {
        $_SESSION['auth'] = true;
        header('Location: index.php');
        exit;
    } else {
        $loginError = 'Contraseña incorrecta';
    }
}
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

if (empty($_SESSION['auth'])) {
    ?>
    <!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Admin - Login</title>
    <style>body{font-family:Manrope,sans-serif;background:#faf6f0;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;}
    .card{background:#fff;padding:32px;border-radius:16px;box-shadow:0 12px 40px rgba(43,31,26,.08);width:320px;}
    h1{font-family:'Cormorant Garamond',serif;margin-bottom:16px;}
    input{width:100%;padding:12px;border:1px solid #e6ddd2;border-radius:10px;margin-bottom:12px;font-size:1rem;}
    button{width:100%;padding:12px;background:#7a3b1e;color:#fff;border:none;border-radius:999px;font-weight:600;cursor:pointer;}
    .err{color:#c62828;margin-bottom:12px;font-size:.9rem;}</style></head>
    <body><div class="card">
      <h1>Administración</h1>
      <?php if (!empty($loginError)) echo '<div class="err">'.$loginError.'</div>'; ?>
      <form method="post">
        <input type="password" name="password" placeholder="Contraseña" required>
        <button type="submit" name="login">Entrar</button>
      </form>
      <p style="font-size:.8rem;color:#6b5a52;margin-top:12px;">Contraseña por defecto: <code>admin123</code></p>
    </div></body></html>
    <?php exit;
}

$cfg = json_decode(file_get_contents($configFile), true) ?: array();
$subs = file_exists($subsFile) ? (json_decode(file_get_contents($subsFile), true) ?: array()) : array();
$products = file_exists($prodFile) ? (json_decode(file_get_contents($prodFile), true) ?: array()) : array();
$tab = $_GET['tab'] ?? 'config';
$saved = false;
$uploaded = false;
$prodMsg = '';

// Save config
if (isset($_POST['save_config'])) {
    foreach (array('siteName','whatsapp','whatsappMessage','email','address','googleMapsUrl','googleMapsEmbed','gbpUrl','instagram','facebook','tiktok','analyticsId','formsEndpoint') as $k) {
        if (isset($_POST[$k])) $cfg[$k] = trim($_POST[$k]);
    }
    $cfg['cacheBust'] = (string)time();
    file_put_contents($configFile, json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    $saved = true;
    $tab = 'config';
}

// Upload image (sections)
if (isset($_POST['upload_image']) && isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $key = $_POST['image_key'] ?? '';
    $allowed = array('logo','hero','about','showroom');
    if (in_array($key, $allowed)) {
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, array('jpg','jpeg','png','webp','gif'))) {
            $filename = $key . '.webp';
            $dest = $imgDir . '/' . $filename;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $dest . '.tmp')) {
                if (convert_to_webp($dest . '.tmp', $dest)) {
                    @unlink($dest . '.tmp');
                    if (!isset($cfg['images'])) $cfg['images'] = array();
                    $cfg['images'][$key] = 'assets/img/' . $filename;
                    $cfg['cacheBust'] = (string)time();
                    file_put_contents($configFile, json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                    $uploaded = true;
                } else {
                    @rename($dest . '.tmp', $dest);
                    if (!isset($cfg['images'])) $cfg['images'] = array();
                    $cfg['images'][$key] = 'assets/img/' . $key . '.' . $ext;
                    file_put_contents($configFile, json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                    $uploaded = true;
                }
            }
        }
    }
    $tab = 'images';
}

// Product CRUD
if (isset($_POST['add_product'])) {
    $np = array(
        'id' => time(),
        'name' => trim($_POST['pname'] ?? ''),
        'category' => trim($_POST['pcat'] ?? ''),
        'specs' => trim($_POST['pspecs'] ?? ''),
        'image' => trim($_POST['pimage'] ?? ''),
        'link' => trim($_POST['plink'] ?? '#contacto'),
    );
    if ($np['name'] !== '') {
        // handle image upload for product
        if (isset($_FILES['pimgfile']) && $_FILES['pimgfile']['error'] === UPLOAD_ERR_OK) {
            $tmp = $imgDir . '/tmp-' . time();
            if (move_uploaded_file($_FILES['pimgfile']['tmp_name'], $tmp)) {
                $filename = 'prod-' . time() . '.webp';
                $dest = $imgDir . '/' . $filename;
                if (convert_to_webp($tmp, $dest)) {
                    @unlink($tmp);
                    $np['image'] = 'assets/img/' . $filename;
                } else {
                    $ext = strtolower(pathinfo($_FILES['pimgfile']['name'], PATHINFO_EXTENSION));
                    $filename = 'prod-' . time() . '.' . $ext;
                    @rename($tmp, $imgDir . '/' . $filename);
                    $np['image'] = 'assets/img/' . $filename;
                }
            }
        }
        $products[] = $np;
        file_put_contents($prodFile, json_encode($products, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $prodMsg = 'Producto agregado.';
    }
    $tab = 'products';
}
if (isset($_POST['delete_product'])) {
    $did = (int)($_POST['delete_id'] ?? 0);
    $products = array_values(array_filter($products, function($p) use ($did){ return ($p['id'] ?? 0) != $did; }));
    file_put_contents($prodFile, json_encode($products, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    $prodMsg = 'Producto eliminado.';
    $tab = 'products';
}
if (isset($_POST['edit_product'])) {
    $eid = (int)($_POST['edit_id'] ?? 0);
    foreach ($products as &$p) {
        if (($p['id'] ?? 0) == $eid) {
            $p['name'] = trim($_POST['pname'] ?? $p['name']);
            $p['category'] = trim($_POST['pcat'] ?? $p['category']);
            $p['specs'] = trim($_POST['pspecs'] ?? $p['specs']);
            $p['link'] = trim($_POST['plink'] ?? $p['link']);
            if (isset($_FILES['pimgfile']) && $_FILES['pimgfile']['error'] === UPLOAD_ERR_OK) {
                $tmp = $imgDir . '/tmp-' . time();
                if (move_uploaded_file($_FILES['pimgfile']['tmp_name'], $tmp)) {
                    $filename = 'prod-' . time() . '.webp';
                    $dest = $imgDir . '/' . $filename;
                    if (convert_to_webp($tmp, $dest)) {
                        @unlink($tmp);
                        $p['image'] = 'assets/img/' . $filename;
                    } else {
                        $ext = strtolower(pathinfo($_FILES['pimgfile']['name'], PATHINFO_EXTENSION));
                        $filename = 'prod-' . time() . '.' . $ext;
                        @rename($tmp, $imgDir . '/' . $filename);
                        $p['image'] = 'assets/img/' . $filename;
                    }
                }
            } elseif (!empty($_POST['pimage'])) {
                $p['image'] = trim($_POST['pimage']);
            }
        }
    }
    unset($p);
    file_put_contents($prodFile, json_encode($products, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    $prodMsg = 'Producto actualizado.';
    $tab = 'products';
}
?>
<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"><title>Admin - Chimeneas Ecológicas</title>
<style>
body{font-family:Manrope,sans-serif;background:#faf6f0;margin:0;padding:20px;}
.container{max-width:1100px;margin:0 auto;}
h1,h2,h3{font-family:'Cormorant Garamond',serif;color:#2b1f1a;}
.topbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:10px;}
.tabs{display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap;}
.tab{padding:10px 18px;border-radius:999px;background:#fff;border:1px solid #e6ddd2;cursor:pointer;font-weight:600;color:#6b5a52;text-decoration:none;}
.tab.active{background:#7a3b1e;color:#fff;border-color:#7a3b1e;}
.card{background:#fff;padding:20px;border-radius:16px;box-shadow:0 8px 24px rgba(43,31,26,.06);margin-bottom:20px;}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;}
input,textarea,select{width:100%;padding:10px;border:1px solid #e6ddd2;border-radius:10px;font-family:inherit;font-size:.95rem;margin-bottom:10px;}
button{padding:10px 20px;background:#7a3b1e;color:#fff;border:none;border-radius:999px;font-weight:600;cursor:pointer;}
table{width:100%;border-collapse:collapse;}
th,td{padding:10px;border-bottom:1px solid #e6ddd2;text-align:left;font-size:.9rem;vertical-align:top;}
th{color:#7a3b1e;}
.img-preview{width:100%;max-height:140px;object-fit:cover;border-radius:10px;margin-bottom:10px;border:1px solid #e6ddd2;}
.upload-row{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px;align-items:start;}
.alert{padding:12px;border-radius:10px;margin-bottom:16px;}
.alert-ok{background:#e8f5e9;color:#2e7d32;}
.prod-img{width:60px;height:45px;object-fit:cover;border-radius:6px;}
@media(max-width:800px){.grid,.upload-row{grid-template-columns:1fr;}}
</style></head>
<body>
<div class="container">
  <div class="topbar">
    <h1>Administración</h1>
    <a href="?logout=1">Cerrar sesión</a>
  </div>

  <div class="tabs">
    <a class="tab <?php echo $tab==='config'?'active':''; ?>" href="?tab=config">Configuración</a>
    <a class="tab <?php echo $tab==='images'?'active':''; ?>" href="?tab=images">Imágenes</a>
    <a class="tab <?php echo $tab==='products'?'active':''; ?>" href="?tab=products">Productos</a>
    <a class="tab <?php echo $tab==='forms'?'active':''; ?>" href="?tab=forms">Formularios</a>
    <a class="tab <?php echo $tab==='analytics'?'active':''; ?>" href="?tab=analytics">Analytics y GBP</a>
  </div>

  <?php if ($saved) echo '<div class="alert alert-ok">Configuración guardada.</div>'; ?>
  <?php if ($uploaded) echo '<div class="alert alert-ok">Imagen subida correctamente.</div>'; ?>
  <?php if ($prodMsg) echo '<div class="alert alert-ok">'.$prodMsg.'</div>'; ?>

  <?php if ($tab === 'config'): ?>
  <div class="card">
    <h2>Configuración general</h2>
    <form method="post">
      <input type="hidden" name="save_config" value="1">
      <?php foreach (array('siteName'=>'Nombre del sitio','whatsapp'=>'WhatsApp (solo números)','whatsappMessage'=>'Mensaje de WhatsApp','email'=>'Email','address'=>'Dirección','googleMapsUrl'=>'URL Google Maps','googleMapsEmbed'=>'URL embed Google Maps','gbpUrl'=>'URL Google Business Profile','instagram'=>'Instagram','facebook'=>'Facebook','tiktok'=>'TikTok','analyticsId'=>'Google Analytics ID (G-XXXX)','formsEndpoint'=>'Endpoint de formularios (local: /api/contact.php | hosting estático: URL de Formspree)') as $k=>$label): ?>
        <label style="font-size:.8rem;text-transform:uppercase;letter-spacing:.08em;color:#6b5a52;font-weight:600;"><?php echo $label; ?></label>
        <input type="text" name="<?php echo $k; ?>" value="<?php echo htmlspecialchars($cfg[$k] ?? ''); ?>">
      <?php endforeach; ?>
      <button type="submit">Guardar cambios</button>
    </form>
    <div style="margin-top:16px;padding:12px;background:#f3ede4;border-radius:10px;font-size:.85rem;color:#6b5a52;">
      <strong>💡 Hosting estático (sin PHP):</strong> Si tu hosting no soporta PHP, usa <a href="https://formspree.io" target="_blank" rel="noopener">Formspree</a> (gratis). Crea un formulario en Formspree y pega la URL en "Endpoint de formularios" (ej. <code>https://formspree.io/f/TU_ID</code>). Los envíos llegarán a tu email y al dashboard de Formspree.
    </div>
  </div>

  <?php elseif ($tab === 'images'): ?>
  <div class="card">
    <h2>Imágenes de las secciones</h2>
    <p style="color:#6b5a52;font-size:.9rem;margin-bottom:16px;">Sube una imagen para cada sección. Se actualizará automáticamente en el sitio.</p>
    <?php
    $imgLabels = array('logo'=>'Logo','hero'=>'Hero (portada)','about'=>'Acerca de','showroom'=>'Showroom');
    $imgs = $cfg['images'] ?? array();
    foreach ($imgLabels as $key=>$label):
        $current = $imgs[$key] ?? '';
    ?>
    <div class="upload-row">
      <div>
        <h3 style="margin-bottom:8px;"><?php echo $label; ?></h3>
        <?php if ($current): ?>
          <img src="../<?php echo htmlspecialchars($current); ?>" alt="<?php echo $label; ?>" class="img-preview">
          <p style="font-size:.8rem;color:#6b5a52;">Actual: <?php echo htmlspecialchars($current); ?></p>
        <?php else: ?>
          <p style="color:#6b5a52;">Sin imagen</p>
        <?php endif; ?>
      </div>
      <div>
        <form method="post" enctype="multipart/form-data">
          <input type="hidden" name="image_key" value="<?php echo $key; ?>">
          <label style="font-size:.8rem;text-transform:uppercase;letter-spacing:.08em;color:#6b5a52;font-weight:600;">Subir nueva imagen</label>
          <input type="file" name="image" accept="image/*" required>
          <button type="submit" name="upload_image">Subir</button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <?php elseif ($tab === 'products'): ?>
  <div class="card">
    <h2>Agregar producto</h2>
    <form method="post" enctype="multipart/form-data">
      <div class="grid">
        <div>
          <label style="font-size:.8rem;text-transform:uppercase;letter-spacing:.08em;color:#6b5a52;font-weight:600;">Nombre</label>
          <input type="text" name="pname" required>
          <label style="font-size:.8rem;text-transform:uppercase;letter-spacing:.08em;color:#6b5a52;font-weight:600;">Categoría</label>
          <input type="text" name="pcat" placeholder="Ej. Chimenea de Etanol">
          <label style="font-size:.8rem;text-transform:uppercase;letter-spacing:.08em;color:#6b5a52;font-weight:600;">Especificaciones</label>
          <input type="text" name="pspecs" placeholder="Capacidad 1 L · Hasta 40 m²">
        </div>
        <div>
          <label style="font-size:.8rem;text-transform:uppercase;letter-spacing:.08em;color:#6b5a52;font-weight:600;">Imagen (URL o subir)</label>
          <input type="text" name="pimage" placeholder="assets/img/...">
          <input type="file" name="pimgfile" accept="image/*">
          <label style="font-size:.8rem;text-transform:uppercase;letter-spacing:.08em;color:#6b5a52;font-weight:600;">Enlace (botón Cotizar)</label>
          <input type="text" name="plink" value="#contacto">
        </div>
      </div>
      <button type="submit" name="add_product">Agregar producto</button>
    </form>
  </div>

  <div class="card">
    <h2>Productos actuales</h2>
    <?php if (empty($products)): ?>
      <p>No hay productos.</p>
    <?php else: ?>
      <table>
        <thead><tr><th>Img</th><th>Nombre</th><th>Categoría</th><th>Especificaciones</th><th>Acciones</th></tr></thead>
        <tbody>
        <?php foreach ($products as $p): ?>
          <tr>
            <td><?php if (!empty($p['image'])): ?><img src="../<?php echo htmlspecialchars($p['image']); ?>" class="prod-img"><?php endif; ?></td>
            <td><?php echo htmlspecialchars($p['name']); ?></td>
            <td><?php echo htmlspecialchars($p['category']); ?></td>
            <td><?php echo htmlspecialchars($p['specs']); ?></td>
            <td>
              <form method="post" style="display:inline;" onsubmit="return confirm('¿Eliminar este producto?');">
                <input type="hidden" name="delete_id" value="<?php echo (int)$p['id']; ?>">
                <button type="submit" name="delete_product" style="background:#c62828;padding:6px 12px;font-size:.75rem;">Eliminar</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <?php elseif ($tab === 'forms'): ?>
  <div class="card">
    <h2>Formularios recibidos</h2>
    <p>Total: <?php echo count($subs); ?></p>
    <?php if (empty($subs)): ?>
      <p>No hay envíos aún.</p>
    <?php else: ?>
      <table>
        <thead><tr><th>Fecha</th><th>Nombre</th><th>Email</th><th>Teléfono</th><th>Mensaje</th></tr></thead>
        <tbody>
        <?php foreach (array_reverse(array_slice($subs, -50)) as $s): ?>
          <tr>
            <td><?php echo htmlspecialchars($s['date']); ?></td>
            <td><?php echo htmlspecialchars($s['name']); ?></td>
            <td><?php echo htmlspecialchars($s['email']); ?></td>
            <td><?php echo htmlspecialchars($s['phone']); ?></td>
            <td><?php echo htmlspecialchars(mb_substr($s['message'],0,80)); ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <?php elseif ($tab === 'analytics'): ?>
  <div class="card">
    <h2>Analytics y Google Business Profile</h2>
    <p><strong>Google Analytics ID:</strong> <?php echo htmlspecialchars($cfg['analyticsId'] ?? 'No configurado'); ?></p>
    <p><a href="https://analytics.google.com" target="_blank" rel="noopener">Abrir Google Analytics</a></p>
    <p><strong>Google Business Profile:</strong> <a href="<?php echo htmlspecialchars($cfg['gbpUrl'] ?? '#'); ?>" target="_blank" rel="noopener">Ver perfil</a></p>
    <p style="font-size:.85rem;color:#6b5a52;margin-top:12px;">Para medir visitas, configura tu ID de GA4 en la pestaña de Configuración y descomenta el script de gtag en index.html.</p>
    <h3 style="margin-top:20px;">Resumen de formularios</h3>
    <p>Total de envíos: <strong><?php echo count($subs); ?></strong></p>
  </div>
  <?php endif; ?>
</div>
</body></html>
