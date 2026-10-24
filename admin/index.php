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
    </div></body></html>
    <?php exit;
}

require __DIR__ . '/../api/db.php';

// Configuración — solo base de datos
$dbConnected = db_available();
$cfg = db_load_settings();
if ($cfg === null) $cfg = array();

// Productos — solo base de datos
$products = db_load_products();
if ($products === null) $products = array();

// Envíos de formularios — solo base de datos
$subs = db_load_submissions();
if ($subs === null) $subs = array();

$tab = $_GET['tab'] ?? 'config';
$saved = false;
$uploaded = false;
$prodMsg = '';
$pwMsg = '';
$pwErr = '';
$saveMsg = '';
$saveErr = '';

// Cambiar contraseña
if (isset($_POST['change_password'])) {
    $current = trim($_POST['current_password'] ?? '');
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $hash = file_exists($passFile) ? trim(file_get_contents($passFile)) : '';
    $validCurrent = $hash ? password_verify($current, $hash) : ($current === 'admin123');
    if (!$validCurrent) {
        $pwErr = 'La contraseña actual es incorrecta.';
    } elseif (strlen($new) < 6) {
        $pwErr = 'La nueva contraseña debe tener al menos 6 caracteres.';
    } elseif ($new !== $confirm) {
        $pwErr = 'Las contraseñas nuevas no coinciden.';
    } else {
        file_put_contents($passFile, password_hash($new, PASSWORD_DEFAULT));
        $pwMsg = 'Contraseña actualizada correctamente.';
    }
    $tab = 'password';
}

// Save config — solo base de datos (sin JSON)
if (isset($_POST['save_config'])) {
    // Sanitizar para evitar triggers de ModSecurity
    foreach ($_POST as $pk => $pv) {
        if (is_string($pv)) {
            $_POST[$pk] = strip_tags($pv);
        }
    }
    $keys = array('siteName','whatsapp','whatsappMessage','email','address','googleMapsUrl','googleMapsEmbed','gbpUrl','instagram','facebook','tiktok','analyticsId','formsEndpoint');
    foreach ($keys as $k) {
        if (isset($_POST[$k])) {
            $cfg[$k] = trim($_POST[$k]);
        }
    }
    $cfg['cacheBust'] = (string)time();
    if (db_save_settings($cfg)) {
        $saveMsg = 'Configuración guardada en la base de datos.';
    } else {
        $saveErr = 'No se pudo guardar en la base de datos. Revisa la conexión en la pestaña "Base de datos".';
    }
    $tab = 'config';
}

// Guardar código personalizado (head / body start / body end)
if (isset($_POST['save_custom_code'])) {
    foreach (array('custom_head','custom_body_start','custom_body_end') as $k) {
        if (isset($_POST[$k])) {
            $cfg[$k] = $_POST[$k];
        } else {
            $cfg[$k] = '';
        }
    }
    $cfg['cacheBust'] = (string)time();
    if (db_save_settings($cfg)) {
        $saveMsg = 'Código personalizado guardado en la base de datos.';
    } else {
        $saveErr = 'No se pudo guardar el código personalizado.';
    }
    $tab = 'customcode';
}

// Guardar popup/ventana emergente
if (isset($_POST['save_popup'])) {
    $cfg['popup_enabled'] = isset($_POST['popup_enabled']) ? '1' : '0';
    $cfg['popup_once'] = isset($_POST['popup_once']) ? '1' : '0';
    foreach (array('popup_title','popup_body','popup_image','popup_btn_text','popup_btn_url') as $k) {
        if (isset($_POST[$k])) {
            $cfg[$k] = trim($_POST[$k]);
        }
    }

    // Quitar imagen actual
    if (!empty($_POST['popup_image_remove'])) {
        $cfg['popup_image'] = '';
    }

    // Subir imagen del popup (se convierte a WebP automáticamente)
    if (isset($_FILES['popup_image_file']) && $_FILES['popup_image_file']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['popup_image_file']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, array('jpg','jpeg','png','webp','gif'))) {
            if (!is_dir($imgDir)) @mkdir($imgDir, 0755, true);
            $tmp = $imgDir . '/popup-upload.tmp';
            if (move_uploaded_file($_FILES['popup_image_file']['tmp_name'], $tmp)) {
                $dest = $imgDir . '/popup.webp';
                if (convert_to_webp($tmp, $dest)) {
                    @unlink($tmp);
                    $cfg['popup_image'] = 'assets/img/popup.webp';
                } else {
                    $finalName = 'popup.' . $ext;
                    @rename($tmp, $imgDir . '/' . $finalName);
                    $cfg['popup_image'] = 'assets/img/' . $finalName;
                }
            } else {
                $saveErr = 'No se pudo mover la imagen subida. Verifica permisos de assets/img.';
            }
        } else {
            $saveErr = 'Formato de imagen no permitido (usa JPG, PNG, WebP o GIF).';
        }
    }

    $cfg['cacheBust'] = (string)time();
    if (db_save_settings($cfg)) {
        $saveMsg = 'Ventana emergente guardada en la base de datos.';
    } else {
        $saveErr = 'No se pudo guardar el popup en la base de datos.';
    }
    $tab = 'popup';
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
                    $uploaded = true;
                } else {
                    @rename($dest . '.tmp', $dest);
                    if (!isset($cfg['images'])) $cfg['images'] = array();
                    $cfg['images'][$key] = 'assets/img/' . $key . '.' . $ext;
                    $cfg['cacheBust'] = (string)time();
                    $uploaded = true;
                }
                db_save_settings($cfg);
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
        'price' => trim($_POST['pprice'] ?? ''),
        'specs' => trim($_POST['pspecs'] ?? ''),
        'description' => trim($_POST['pdescription'] ?? ''),
        'details' => trim($_POST['pdetails'] ?? ''),
        'image' => trim($_POST['pimage'] ?? ''),
        'images' => array(),
        'link' => trim($_POST['plink'] ?? '#contacto'),
    );
    if ($np['name'] !== '') {
        // handle multiple image uploads for product
        $uploaded = array();
        if (!empty($_FILES['pimgfiles']) && is_array($_FILES['pimgfiles']['name'])) {
            foreach ($_FILES['pimgfiles']['name'] as $i => $fname) {
                if ($fname === '' || $_FILES['pimgfiles']['error'][$i] !== UPLOAD_ERR_OK) continue;
                $tmp = $imgDir . '/tmp-' . time() . '-' . $i;
                if (move_uploaded_file($_FILES['pimgfiles']['tmp_name'][$i], $tmp)) {
                    $filename = 'prod-' . time() . '-' . $i . '.webp';
                    $dest = $imgDir . '/' . $filename;
                    if (convert_to_webp($tmp, $dest)) {
                        @unlink($tmp);
                        $uploaded[] = 'assets/img/' . $filename;
                    } else {
                        $ext = strtolower(pathinfo($fname, PATHINFO_EXTENSION));
                        $filename = 'prod-' . time() . '-' . $i . '.' . $ext;
                        @rename($tmp, $imgDir . '/' . $filename);
                        $uploaded[] = 'assets/img/' . $filename;
                    }
                }
            }
        }
        if (!empty($uploaded)) {
            $np['images'] = $uploaded;
            $np['image'] = $uploaded[0];
        } elseif (!empty($np['image'])) {
            $np['images'] = array($np['image']);
        }
        $products[] = $np;
        sync_products_db($products);
        $cfg['cacheBust'] = (string)time();
        db_save_settings($cfg);
        $prodMsg = 'Producto agregado.';
    }
    $tab = 'products';
}
if (isset($_POST['delete_product'])) {
    $did = (int)($_POST['delete_id'] ?? 0);
    $products = array_values(array_filter($products, function($p) use ($did){ return ($p['id'] ?? 0) != $did; }));
    sync_products_db($products);
    $cfg['cacheBust'] = (string)time();
    db_save_settings($cfg);
    $prodMsg = 'Producto eliminado.';
    $tab = 'products';
}
if (isset($_POST['edit_product'])) {
    $eid = (int)($_POST['edit_id'] ?? 0);
    foreach ($products as &$p) {
        if (($p['id'] ?? 0) == $eid) {
            $p['name'] = trim($_POST['pname'] ?? $p['name']);
            $p['category'] = trim($_POST['pcat'] ?? $p['category']);
            $p['price'] = trim($_POST['pprice'] ?? ($p['price'] ?? ''));
            $p['specs'] = trim($_POST['pspecs'] ?? $p['specs']);
            $p['description'] = trim($_POST['pdescription'] ?? ($p['description'] ?? ''));
            $p['details'] = trim($_POST['pdetails'] ?? ($p['details'] ?? ''));
            $p['link'] = trim($_POST['plink'] ?? $p['link']);
            if (!isset($p['images']) || !is_array($p['images'])) {
                $p['images'] = !empty($p['image']) ? array($p['image']) : array();
            }
            if (!empty($_POST['pimage'])) $p['image'] = trim($_POST['pimage']);
            // multiple image uploads
            $uploaded = array();
            if (!empty($_FILES['pimgfiles']) && is_array($_FILES['pimgfiles']['name'])) {
                foreach ($_FILES['pimgfiles']['name'] as $i => $fname) {
                    if ($fname === '' || $_FILES['pimgfiles']['error'][$i] !== UPLOAD_ERR_OK) continue;
                    $tmp = $imgDir . '/tmp-' . time() . '-' . $i;
                    if (move_uploaded_file($_FILES['pimgfiles']['tmp_name'][$i], $tmp)) {
                        $filename = 'prod-' . time() . '-' . $i . '.webp';
                        $dest = $imgDir . '/' . $filename;
                        if (convert_to_webp($tmp, $dest)) {
                            @unlink($tmp);
                            $uploaded[] = 'assets/img/' . $filename;
                        } else {
                            $ext = strtolower(pathinfo($fname, PATHINFO_EXTENSION));
                            $filename = 'prod-' . time() . '-' . $i . '.' . $ext;
                            @rename($tmp, $imgDir . '/' . $filename);
                            $uploaded[] = 'assets/img/' . $filename;
                        }
                    }
                }
            }
            if (!empty($uploaded)) {
                $p['images'] = $uploaded;
                $p['image'] = $uploaded[0];
            } elseif (!empty($_POST['pimage'])) {
                if (!empty($p['images'])) $p['images'][0] = $p['image'];
                else $p['images'] = array($p['image']);
            }
        }
    }
    unset($p);
    sync_products_db($products);
    $cfg['cacheBust'] = (string)time();
    db_save_settings($cfg);
    $prodMsg = 'Producto actualizado.';
    $tab = 'products';
}
// --- Base de datos ---
$dbMsg = '';
$dbOk = false;
if (isset($_POST['install_db'])) {
    $res = db_install();
    $dbMsg = $res['msg'];
    $dbOk = $res['ok'];
    if ($dbOk) {
        $seed = db_seed();
        $dbMsg .= ' ' . $seed['msg'];
        if ($seed['products'] > 0) $dbMsg .= " ({$seed['products']} productos sembrados)";
        if ($seed['settings'] > 0) $dbMsg .= " ({$seed['settings']} configs sembradas)";
    }
    $tab = 'database';
}
if (isset($_POST['migrate_db'])) {
    $ok = db_save_settings($cfg) && sync_products_db($products);
    $dbMsg = $ok ? 'Configuración y productos guardados en la base de datos.' : 'No se pudo guardar en la base de datos.';
    $dbOk = db_available();
    $tab = 'database';
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
.alert-err{background:#fdecea;color:#c62828;}
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
    <a class="tab <?php echo $tab==='customcode'?'active':''; ?>" href="?tab=customcode">Scripts / Código personalizado</a>
    <a class="tab <?php echo $tab==='popup'?'active':''; ?>" href="?tab=popup">Popup / Ventana emergente</a>
    <a class="tab <?php echo $tab==='database'?'active':''; ?>" href="?tab=database">Base de datos</a>
    <a class="tab <?php echo $tab==='password'?'active':''; ?>" href="?tab=password">Contraseña</a>
  </div>

  <?php if ($saveMsg) echo '<div class="alert alert-ok">'.htmlspecialchars($saveMsg).'</div>'; ?>
  <?php if ($saveErr) echo '<div class="alert alert-err">'.htmlspecialchars($saveErr).'</div>'; ?>
  <?php if ($uploaded) echo '<div class="alert alert-ok">Imagen subida correctamente.</div>'; ?>
  <?php if ($prodMsg) echo '<div class="alert alert-ok">'.htmlspecialchars($prodMsg).'</div>'; ?>

  <?php if ($tab === 'config'): ?>
  <div class="card">
    <h2>Configuración general</h2>
    <p style="font-size:.85rem;margin-bottom:16px;">
      <strong>Almacenamiento:</strong>
      <?php if ($dbConnected): ?>
        <span style="color:#2e7d32;font-weight:600;">Base de datos MySQL (<?php echo htmlspecialchars(DB_NAME); ?>)</span>
      <?php else: ?>
        <span style="color:#c62828;font-weight:600;">Sin conexión a la base de datos</span>
        <br><small style="color:#6b5a52;"><?php echo htmlspecialchars(db_last_error()); ?></small>
        <?php if (!file_exists(__DIR__ . '/../api/db-credentials.php')): ?>
          <br><small style="color:#c62828;"><strong>Falta el archivo <code>api/db-credentials.php</code>.</strong> Créalo en el servidor con tus credenciales (ver pestaña "Base de datos").</small>
        <?php endif; ?>
      <?php endif; ?>
    </p>
    <p style="font-size:.85rem;color:#6b5a52;margin-bottom:12px;">Todo se guarda <strong>únicamente</strong> en la base de datos.</p>

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
          <input type="text" name="pcat" placeholder="Ej. Quemadores Bioetanol">
          <label style="font-size:.8rem;text-transform:uppercase;letter-spacing:.08em;color:#6b5a52;font-weight:600;">Precio</label>
          <input type="text" name="pprice" placeholder="Ej. $3,000">
          <label style="font-size:.8rem;text-transform:uppercase;letter-spacing:.08em;color:#6b5a52;font-weight:600;">Especificaciones (resumen)</label>
          <input type="text" name="pspecs" placeholder="Capacidad 1 litro · Calienta 40 m²">
        </div>
        <div>
          <label style="font-size:.8rem;text-transform:uppercase;letter-spacing:.08em;color:#6b5a52;font-weight:600;">Descripción (modal)</label>
          <textarea name="pdescription" rows="3" placeholder="Descripción del producto que verá el cliente..."></textarea>
          <label style="font-size:.8rem;text-transform:uppercase;letter-spacing:.08em;color:#6b5a52;font-weight:600;">Detalles (una línea por dato)</label>
          <textarea name="pdetails" rows="3" placeholder="Medidas: ...&#10;Capacidad: ...&#10;Incluye: ..."></textarea>
        </div>
      </div>
      <div class="grid">
        <div>
          <label style="font-size:.8rem;text-transform:uppercase;letter-spacing:.08em;color:#6b5a52;font-weight:600;">Imagen principal (URL)</label>
          <input type="text" name="pimage" placeholder="assets/img/...">
          <label style="font-size:.8rem;text-transform:uppercase;letter-spacing:.08em;color:#6b5a52;font-weight:600;">Subir imagen(es) — puedes elegir varias</label>
          <input type="file" name="pimgfiles[]" accept="image/*" multiple>
        </div>
        <div>
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
        <thead><tr><th>Img</th><th>Nombre</th><th>Categoría</th><th>Precio</th><th>Acciones</th></tr></thead>
        <tbody>
        <?php foreach ($products as $p): ?>
          <tr>
            <td><?php if (!empty($p['image'])): ?><img src="../<?php echo htmlspecialchars($p['image']); ?>" class="prod-img"><?php endif; ?></td>
            <td><?php echo htmlspecialchars($p['name']); ?></td>
            <td><?php echo htmlspecialchars($p['category']); ?></td>
            <td><?php echo htmlspecialchars($p['price'] ?? ''); ?></td>
            <td style="white-space:nowrap;">
              <button type="button" onclick="document.getElementById('edit-<?php echo (int)$p['id']; ?>').style.display = document.getElementById('edit-<?php echo (int)$p['id']; ?>').style.display === 'none' ? 'block' : 'none';" style="background:#5b7c99;padding:6px 12px;font-size:.75rem;">Editar</button>
              <form method="post" style="display:inline;" onsubmit="return confirm('¿Eliminar este producto?');">
                <input type="hidden" name="delete_id" value="<?php echo (int)$p['id']; ?>">
                <button type="submit" name="delete_product" style="background:#c62828;padding:6px 12px;font-size:.75rem;">Eliminar</button>
              </form>
            </td>
          </tr>
          <tr>
            <td colspan="5" style="padding:0;">
              <div id="edit-<?php echo (int)$p['id']; ?>" style="display:none;background:#f8f4ef;padding:14px;border-radius:10px;margin-bottom:12px;">
                <form method="post" enctype="multipart/form-data">
                  <input type="hidden" name="edit_id" value="<?php echo (int)$p['id']; ?>">
                  <div class="grid">
                    <div>
                      <label style="font-size:.75rem;text-transform:uppercase;color:#6b5a52;font-weight:600;">Nombre</label>
                      <input type="text" name="pname" value="<?php echo htmlspecialchars($p['name']); ?>">
                      <label style="font-size:.75rem;text-transform:uppercase;color:#6b5a52;font-weight:600;">Categoría</label>
                      <input type="text" name="pcat" value="<?php echo htmlspecialchars($p['category'] ?? ''); ?>">
                      <label style="font-size:.75rem;text-transform:uppercase;color:#6b5a52;font-weight:600;">Precio</label>
                      <input type="text" name="pprice" value="<?php echo htmlspecialchars($p['price'] ?? ''); ?>">
                      <label style="font-size:.75rem;text-transform:uppercase;color:#6b5a52;font-weight:600;">Especificaciones</label>
                      <input type="text" name="pspecs" value="<?php echo htmlspecialchars($p['specs'] ?? ''); ?>">
                    </div>
                    <div>
                      <label style="font-size:.75rem;text-transform:uppercase;color:#6b5a52;font-weight:600;">Descripción</label>
                      <textarea name="pdescription" rows="3"><?php echo htmlspecialchars($p['description'] ?? ''); ?></textarea>
                      <label style="font-size:.75rem;text-transform:uppercase;color:#6b5a52;font-weight:600;">Detalles</label>
                      <textarea name="pdetails" rows="3"><?php echo htmlspecialchars($p['details'] ?? ''); ?></textarea>
                      <label style="font-size:.75rem;text-transform:uppercase;color:#6b5a52;font-weight:600;">Imagen principal (URL)</label>
                      <input type="text" name="pimage" value="<?php echo htmlspecialchars($p['image'] ?? ''); ?>">
                      <label style="font-size:.75rem;text-transform:uppercase;color:#6b5a52;font-weight:600;">Reemplazar imágenes (elige varias)</label>
                      <input type="file" name="pimgfiles[]" accept="image/*" multiple>
                      <?php if (!empty($p['images'])): ?>
                        <p style="font-size:.75rem;color:#6b5a52;margin-top:6px;">Imágenes actuales: <?php echo htmlspecialchars(implode(', ', $p['images'])); ?></p>
                      <?php endif; ?>
                    </div>
                  </div>
                  <button type="submit" name="edit_product">Guardar cambios</button>
                </form>
              </div>
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
    <p style="margin-bottom:12px;"><a href="export.php" style="font-weight:600;color:#7a3b1e;">⬇ Descargar CSV (Excel)</a></p>
    <?php if (empty($subs)): ?>
      <p>No hay envíos aún.</p>
    <?php else: ?>
      <table>
        <thead><tr><th>Fecha</th><th>Nombre</th><th>Contacto</th><th>Producto</th><th>Interés</th><th>Mensaje</th></tr></thead>
        <tbody>
        <?php foreach (array_reverse(array_slice($subs, -50)) as $s): ?>
          <tr>
            <td style="white-space:nowrap;"><?php echo htmlspecialchars($s['date']); ?></td>
            <td><?php echo htmlspecialchars($s['name']); ?></td>
            <td><?php echo htmlspecialchars($s['email']); ?><br><small style="color:#6b5a52;"><?php echo htmlspecialchars($s['phone']); ?></small></td>
            <td><?php echo htmlspecialchars($s['product'] ?? '—'); ?></td>
            <td>
              <?php
                $int = $s['interest'] ?? 'informacion';
                echo $int === 'cita' ? '📅 Cita' : 'ℹ️ Información';
                if ($int === 'cita' && !empty($s['appointment_date'])) echo '<br><small style="color:#6b5a52;">' . htmlspecialchars($s['appointment_date']) . '</small>';
                $pc = !empty($s['consent_privacy']) ? 'Privacidad' : '';
                $ac = !empty($s['consent_ads']) ? 'Publicidad' : '';
                if ($pc || $ac) echo '<br><small style="color:#6b5a52;">' . htmlspecialchars(trim($pc . ($pc && $ac ? ' · ' : '') . $ac)) . '</small>';
              ?>
            </td>
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
    <p style="font-size:.85rem;color:#6b5a52;margin-top:12px;">Para medir visitas, configura tu ID de GA4 en la pestaña de Configuración o usando la pestaña <strong>Scripts / Código personalizado</strong>.</p>
    <h3 style="margin-top:20px;">Resumen de formularios</h3>
    <p>Total de envíos: <strong><?php echo count($subs); ?></strong></p>
  </div>

  <?php elseif ($tab === 'customcode'): ?>
  <div class="card">
    <h2>Scripts / Código personalizado</h2>
    <p style="color:#6b5a52;font-size:.9rem;margin-bottom:16px;">Inserta código directamente en el sitio sin editar archivos. Se guarda en la base de datos.</p>
    <form method="post">
      <input type="hidden" name="save_custom_code" value="1">
      <label style="font-size:.8rem;text-transform:uppercase;letter-spacing:.08em;color:#6b5a52;font-weight:600;">1) Código dentro de &lt;head&gt; (antes de &lt;/head&gt;)</label>
      <textarea name="custom_head" rows="8" style="font-family:Consolas,monospace;font-size:.85rem;" placeholder="Ej. Google Tag Manager, Meta Pixel, verificación de dominio, etc."><?php echo htmlspecialchars($cfg['custom_head'] ?? ''); ?></textarea>
      <label style="font-size:.8rem;text-transform:uppercase;letter-spacing:.08em;color:#6b5a52;font-weight:600;">2) Código justo después de &lt;body&gt;</label>
      <textarea name="custom_body_start" rows="6" style="font-family:Consolas,monospace;font-size:.85rem;" placeholder="Ej. Google Tag Manager noscript, banners globales, etc."><?php echo htmlspecialchars($cfg['custom_body_start'] ?? ''); ?></textarea>
      <label style="font-size:.8rem;text-transform:uppercase;letter-spacing:.08em;color:#6b5a52;font-weight:600;">3) Código antes de &lt;/body&gt;</label>
      <textarea name="custom_body_end" rows="8" style="font-family:Consolas,monospace;font-size:.85rem;" placeholder="Ej. gtag/GA4, chatbots, widgets, scripts con async/defer."><?php echo htmlspecialchars($cfg['custom_body_end'] ?? ''); ?></textarea>
      <button type="submit">Guardar código</button>
    </form>
    <div style="margin-top:16px;padding:12px;background:#f3ede4;border-radius:10px;font-size:.85rem;color:#6b5a52;">
      <strong>Recomendado:</strong> Usa esta sección para pegar el código completo que te dé Google, Meta, etc. Se inyecta directamente en <code>index.php</code>.
    </div>
  </div>

  <?php elseif ($tab === 'popup'): ?>
  <div class="card">
    <h2>Popup / Ventana emergente</h2>
    <p style="color:#6b5a52;font-size:.9rem;margin-bottom:16px;">Muestra un aviso, promoción o mensaje al cargar la página. Puedes activarlo/desactivarlo desde aquí.</p>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="save_popup" value="1">
      <label class="check" style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
        <input type="checkbox" name="popup_enabled" value="1" <?php echo (!empty($cfg['popup_enabled']) && $cfg['popup_enabled'] !== '0') ? 'checked' : ''; ?>>
        <span>Activar ventana emergente</span>
      </label>
      <label class="check" style="display:flex;align-items:center;gap:10px;margin-bottom:16px;">
        <input type="checkbox" name="popup_once" value="1" <?php echo (!empty($cfg['popup_once']) && $cfg['popup_once'] !== '0') ? 'checked' : ''; ?>>
        <span>Mostrar solo una vez por visitante (usar cookie)</span>
      </label>
      <label style="font-size:.8rem;text-transform:uppercase;letter-spacing:.08em;color:#6b5a52;font-weight:600;">Título</label>
      <input type="text" name="popup_title" value="<?php echo htmlspecialchars($cfg['popup_title'] ?? ''); ?>" placeholder="Ej. Aviso importante">
      <label style="font-size:.8rem;text-transform:uppercase;letter-spacing:.08em;color:#6b5a52;font-weight:600;">Mensaje / Cuerpo</label>
      <textarea name="popup_body" rows="4" placeholder="Escribe el mensaje que aparecerá en el popup..."><?php echo htmlspecialchars($cfg['popup_body'] ?? ''); ?></textarea>
      <label style="font-size:.8rem;text-transform:uppercase;letter-spacing:.08em;color:#6b5a52;font-weight:600;">Imagen del popup</label>
      <?php if (!empty($cfg['popup_image'])): ?>
        <img src="../<?php echo htmlspecialchars($cfg['popup_image']); ?>?v=<?php echo time(); ?>" alt="Imagen del popup" style="max-width:100%;max-height:220px;object-fit:cover;border-radius:10px;border:1px solid #e6ddd2;margin-bottom:8px;display:block;">
        <label class="check" style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
          <input type="checkbox" name="popup_image_remove" value="1">
          <span>Quitar la imagen actual</span>
        </label>
      <?php endif; ?>
      <label style="font-size:.8rem;text-transform:uppercase;letter-spacing:.08em;color:#6b5a52;font-weight:600;">Subir imagen (se convierte a WebP)</label>
      <input type="file" name="popup_image_file" accept="image/jpeg,image/png,image/webp,image/gif">
      <small style="display:block;color:#6b5a52;margin-bottom:12px;">Formatos: JPG, PNG, WebP o GIF. Tamaño recomendado: 1000 × 700 px.</small>
      <label style="font-size:.8rem;text-transform:uppercase;letter-spacing:.08em;color:#6b5a52;font-weight:600;">O escribe una URL de imagen (opcional)</label>
      <input type="text" name="popup_image" value="<?php echo htmlspecialchars($cfg['popup_image'] ?? ''); ?>" placeholder="assets/img/... o https://...">
      <div class="grid">
        <div>
          <label style="font-size:.8rem;text-transform:uppercase;letter-spacing:.08em;color:#6b5a52;font-weight:600;">Texto del botón (opcional)</label>
          <input type="text" name="popup_btn_text" value="<?php echo htmlspecialchars($cfg['popup_btn_text'] ?? ''); ?>" placeholder="Ej. Ver más / Cerrar">
        </div>
        <div>
          <label style="font-size:.8rem;text-transform:uppercase;letter-spacing:.08em;color:#6b5a52;font-weight:600;">Enlace del botón (opcional)</label>
          <input type="text" name="popup_btn_url" value="<?php echo htmlspecialchars($cfg['popup_btn_url'] ?? ''); ?>" placeholder="#contacto o https://...">
        </div>
      </div>
      <button type="submit">Guardar popup</button>
    </form>
    <p style="font-size:.85rem;color:#6b5a52;margin-top:12px;">Si no pones botón, solo aparecerá el botón "Cerrar".</p>
  </div>

  <?php elseif ($tab === 'database'): ?>
  <div class="card">
    <h2>Base de datos (MySQL)</h2>
    <?php
      $conn = db_available();
      $server = db_server();
      $counts = array('products' => '—', 'submissions' => '—', 'settings' => '—');
      if ($conn) {
          $pdoTmp = get_db();
          foreach (array_keys($counts) as $t) {
              try { $counts[$t] = $pdoTmp->query("SELECT COUNT(*) FROM `$t`")->fetchColumn(); }
              catch (PDOException $e) { $counts[$t] = 'error'; }
          }
      }
    ?>
    <p><strong>Estado:</strong>
      <?php if ($conn): ?>
        <span style="color:#2e7d32;font-weight:600;">Conectada</span>
        <?php if ($server): $ver = $server->query('SELECT VERSION()')->fetchColumn(); ?> &middot; MySQL <?php echo htmlspecialchars($ver); ?><?php endif; ?>
      <?php else: ?>
        <span style="color:#c62828;font-weight:600;">Sin conexión</span>
        <br><small style="color:#6b5a52;"><?php echo htmlspecialchars(db_last_error()); ?></small>
      <?php endif; ?>
    </p>
    <p><strong>Base de datos:</strong> <code><?php echo htmlspecialchars(DB_NAME); ?></code></p>
    <p><strong>Registros:</strong> productos = <?php echo htmlspecialchars((string)$counts['products']); ?> &middot; envíos = <?php echo htmlspecialchars((string)$counts['submissions']); ?> &middot; configuraciones = <?php echo htmlspecialchars((string)$counts['settings']); ?></p>

    <?php if ($dbMsg): ?>
      <div class="alert <?php echo $dbOk ? 'alert-ok' : 'alert-err'; ?>"><?php echo htmlspecialchars($dbMsg); ?></div>
    <?php endif; ?>

    <form method="post" style="display:inline-block;margin-right:8px;">
      <input type="hidden" name="install_db" value="1">
      <button type="submit">Crear tablas / Base de datos</button>
    </form>
    <form method="post" style="display:inline-block;">
      <input type="hidden" name="migrate_db" value="1">
      <button type="submit">Guardar todo en la BD</button>
    </form>

    <h3 style="margin-top:24px;">Cómo configurarlo en tu hosting</h3>
    <ol style="font-size:.9rem;color:#6b5a52;line-height:1.7;">
      <li>En cPanel abre <strong>MySQL Databases</strong> y crea una base de datos y un usuario con todos los permisos (ALL PRIVILEGES).</li>
      <li>Crea el archivo <code>api/db-credentials.php</code> en el servidor (NO se sube a git por seguridad). Contenido:
        <pre style="background:#2b1f1a;color:#e6dacb;padding:12px;border-radius:8px;overflow:auto;margin:8px 0;">&lt;?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'vanderbi_chimeneas');
define('DB_USER', 'vanderbi_chimeneas');
define('DB_PASS', 'Chimenea#25');</pre>
        <small>Si tu hosting usa un host distinto a <code>localhost</code>, cámbialo (lo indica cPanel en "MySQL Databases" o "Remote MySQL").</small>
      </li>
      <li>Vuelve aquí y presiona <strong>Crear tablas / Base de datos</strong> (crea tablas y copia los datos iniciales).</li>
    </ol>
    <p style="font-size:.85rem;color:#6b5a52;background:#f3ede4;padding:12px;border-radius:10px;">
      <strong>Nota:</strong> Todo el sistema (configuración, productos y envíos) se guarda <strong>únicamente en la base de datos</strong>.
      Los archivos <code>config.json</code> y <code>data/products.json</code> ya no se usan en tiempo de ejecución; solo sirven como datos
      iniciales la primera vez que creas las tablas.
    </p>
  </div>

  <?php elseif ($tab === 'password'): ?>
  <div class="card" style="max-width:480px;">
    <h2>Cambiar contraseña</h2>
    <?php if ($pwMsg) echo '<div class="alert alert-ok">'.htmlspecialchars($pwMsg).'</div>'; ?>
    <?php if ($pwErr) echo '<div class="alert alert-err">'.htmlspecialchars($pwErr).'</div>'; ?>
    <form method="post">
      <input type="hidden" name="change_password" value="1">
      <label style="font-size:.8rem;text-transform:uppercase;letter-spacing:.08em;color:#6b5a52;font-weight:600;">Contraseña actual</label>
      <input type="password" name="current_password" required>
      <label style="font-size:.8rem;text-transform:uppercase;letter-spacing:.08em;color:#6b5a52;font-weight:600;">Nueva contraseña</label>
      <input type="password" name="new_password" required>
      <label style="font-size:.8rem;text-transform:uppercase;letter-spacing:.08em;color:#6b5a52;font-weight:600;">Confirmar nueva contraseña</label>
      <input type="password" name="confirm_password" required>
      <button type="submit">Actualizar contraseña</button>
    </form>
    <p style="font-size:.85rem;color:#6b5a52;margin-top:12px;">La nueva contraseña debe tener al menos 6 caracteres.</p>
  </div>
  <?php endif; ?>
</div>
</body></html>
