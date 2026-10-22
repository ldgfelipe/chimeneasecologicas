<?php
/**
 * Configuración y helpers de base de datos (MySQL/MariaDB).
 *
 * Las credenciales reales viven en api/db-credentials.php (ignorado por git).
 * Si ese archivo no existe se usan los valores por defecto de abajo (entorno local).
 */
if (file_exists(__DIR__ . '/db-credentials.php')) {
    require_once __DIR__ . '/db-credentials.php';
}

if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', 'chimeneasecologicas_web');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');
if (!defined('CAPTCHA_SECRET')) define('CAPTCHA_SECRET', 'chm_ecol_captcha_2026_v2');

/** Genera un reto CAPTCHA matemático (estateless, sin sesiones). */
function captcha_generate() {
    $a = rand(2, 9);
    $b = rand(2, 9);
    $sum = $a + $b;
    $time = time();
    $token = hash_hmac('sha256', $a . '|' . $b . '|' . $sum . '|' . $time, CAPTCHA_SECRET);
    return array('a' => $a, 'b' => $b, 'token' => $token, 'time' => $time);
}

/**
 * Verifica el CAPTCHA matemático.
 * @return bool
 */
function captcha_verify($a, $b, $answer, $token, $time, $minSeconds = 3, $maxAge = 600) {
    $sum = (int)$a + (int)$b;
    if ((int)$answer !== $sum) return false;
    $expected = hash_hmac('sha256', $a . '|' . $b . '|' . $sum . '|' . $time, CAPTCHA_SECRET);
    if (!function_exists('hash_equals')) return false;
    if (!hash_equals($expected, (string)$token)) return false;
    $elapsed = time() - (int)$time;
    if ($elapsed < $minSeconds) return false; // demasiado rápido (bot)
    if ($elapsed > $maxAge) return false;     // expirado
    return true;
}

/** Conexión al servidor MySQL sin seleccionar base de datos. */
function db_server() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';charset=' . DB_CHARSET,
                DB_USER,
                DB_PASS,
                array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC)
            );
        } catch (PDOException $e) {
            return null;
        }
    }
    return $pdo;
}

/** Conexión a la base de datos (crea la BD si el usuario tiene permisos). */
function get_db() {
    static $pdo = null;
    static $tried = false;
    if ($pdo !== null) return $pdo;
    if ($tried) return null;
    $tried = true;

    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
            DB_USER,
            DB_PASS,
            array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC)
        );
        return $pdo;
    } catch (PDOException $e) {
        // Si la BD no existe, intenta crearla (hostings cPanel a veces lo permiten)
        $server = db_server();
        if ($server) {
            try {
                $server->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '', DB_NAME) . '` CHARACTER SET ' . DB_CHARSET);
                $pdo = new PDO(
                    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
                    DB_USER,
                    DB_PASS,
                    array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC)
                );
                return $pdo;
            } catch (PDOException $e2) {
                return null;
            }
        }
        return null;
    }
}

function db_available() {
    return get_db() !== null;
}

/** Mensaje de error legible de la última conexión. */
function db_last_error() {
    try {
        new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET, DB_USER, DB_PASS);
    } catch (PDOException $e) {
        return $e->getMessage();
    }
    return '';
}

/** Verifica si una columna existe en una tabla. */
function db_column_exists($table, $column) {
    $pdo = get_db();
    if (!$pdo) return false;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
        $stmt->execute(array($table, $column));
        return (int)$stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

/** Crea las tablas si no existen y aplica migraciones de columnas. */
function db_install() {
    $pdo = get_db();
    if (!$pdo) {
        return array('ok' => false, 'msg' => 'No se pudo conectar a la base de datos. Revisa api/db.php. Detalle: ' . db_last_error());
    }
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS submissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            date DATETIME DEFAULT CURRENT_TIMESTAMP,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            phone VARCHAR(50),
            product VARCHAR(255) DEFAULT '',
            interest VARCHAR(50) DEFAULT 'informacion',
            appointment_date VARCHAR(50) DEFAULT '',
            message TEXT,
            consent_privacy TINYINT(1) DEFAULT 0,
            consent_ads TINYINT(1) DEFAULT 0,
            sent TINYINT(1) DEFAULT 1
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            category VARCHAR(100),
            price VARCHAR(50) DEFAULT '',
            specs VARCHAR(255),
            description TEXT,
            details TEXT,
            image VARCHAR(255),
            images TEXT,
            link VARCHAR(255) DEFAULT '#contacto',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
            skey VARCHAR(100) PRIMARY KEY,
            svalue TEXT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        // Migraciones de columnas (para instalaciones previas)
        $migrations = array(
            'products' => array(
                'price' => "ALTER TABLE products ADD COLUMN price VARCHAR(50) DEFAULT '' AFTER category",
                'details' => "ALTER TABLE products ADD COLUMN details TEXT AFTER description",
                'images' => "ALTER TABLE products ADD COLUMN images TEXT AFTER image",
            ),
            'submissions' => array(
                'product' => "ALTER TABLE submissions ADD COLUMN product VARCHAR(255) DEFAULT '' AFTER phone",
                'interest' => "ALTER TABLE submissions ADD COLUMN interest VARCHAR(50) DEFAULT 'informacion' AFTER product",
                'appointment_date' => "ALTER TABLE submissions ADD COLUMN appointment_date VARCHAR(50) DEFAULT '' AFTER interest",
                'consent_privacy' => "ALTER TABLE submissions ADD COLUMN consent_privacy TINYINT(1) DEFAULT 0 AFTER message",
                'consent_ads' => "ALTER TABLE submissions ADD COLUMN consent_ads TINYINT(1) DEFAULT 0 AFTER consent_privacy",
            ),
        );
        foreach ($migrations as $table => $cols) {
            foreach ($cols as $col => $sql) {
                if (!db_column_exists($table, $col)) {
                    $pdo->exec($sql);
                }
            }
        }
        return array('ok' => true, 'msg' => 'Tablas creadas / actualizadas correctamente.');
    } catch (PDOException $e) {
        return array('ok' => false, 'msg' => 'Error SQL: ' . $e->getMessage());
    }
}

/**
 * Sincroniza el arreglo de productos (JSON) hacia la tabla `products`.
 * @param array $products
 * @return bool
 */
function sync_products_db($products) {
    $pdo = get_db();
    if (!$pdo) return false;
    try {
        // Asegura que las columnas nuevas existan (migración transparente)
        foreach (array('price', 'details', 'images') as $col) {
            if (!db_column_exists('products', $col)) {
                $map = array(
                    'price' => "ALTER TABLE products ADD COLUMN price VARCHAR(50) DEFAULT '' AFTER category",
                    'details' => "ALTER TABLE products ADD COLUMN details TEXT AFTER description",
                    'images' => "ALTER TABLE products ADD COLUMN images TEXT AFTER image",
                );
                $pdo->exec($map[$col]);
            }
        }
        $pdo->exec("TRUNCATE TABLE products");
        $stmt = $pdo->prepare("INSERT INTO products (id, name, category, price, specs, description, details, image, images, link) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($products as $p) {
            $imgs = isset($p['images']) && is_array($p['images']) ? $p['images'] : array();
            if (empty($imgs) && !empty($p['image'])) $imgs = array($p['image']);
            $stmt->execute(array(
                (isset($p['id']) && $p['id'] ? (int)$p['id'] : null),
                $p['name'] ?? '',
                $p['category'] ?? '',
                $p['price'] ?? '',
                $p['specs'] ?? '',
                $p['description'] ?? '',
                $p['details'] ?? '',
                $p['image'] ?? '',
                json_encode($imgs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                $p['link'] ?? '#contacto'
            ));
        }
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Carga la configuración desde la tabla `settings`.
 * @return array|null  array de config, o null si la BD no está disponible
 */
function db_load_settings() {
    $pdo = get_db();
    if (!$pdo) return null;
    try {
        $stmt = $pdo->query("SELECT skey, svalue FROM settings");
        $out = array();
        foreach ($stmt->fetchAll() as $r) {
            $out[$r['skey']] = $r['svalue'];
        }
        if (empty($out)) return null; // tabla vacía -> el llamador usa el fallback JSON
        if (isset($out['images'])) {
            $dec = json_decode($out['images'], true);
            $out['images'] = is_array($dec) ? $dec : array();
        }
        return $out;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Guarda la configuración en la tabla `settings` (clave/valor).
 * Los valores que sean arrays (ej. images) se serializan a JSON.
 * @param array $cfg
 * @return bool
 */
function db_save_settings($cfg) {
    $pdo = get_db();
    if (!$pdo) return false;
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS settings (skey VARCHAR(100) PRIMARY KEY, svalue TEXT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $stmt = $pdo->prepare("REPLACE INTO settings (skey, svalue) VALUES (?, ?)");
        foreach ($cfg as $k => $v) {
            if (is_array($v)) {
                $v = json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
            $stmt->execute(array($k, (string)$v));
        }
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Carga los productos desde la tabla `products`.
 * @return array|null  array de productos, o null si la BD no está disponible
 */
function db_load_products() {
    $pdo = get_db();
    if (!$pdo) return null;
    try {
        $stmt = $pdo->query("SELECT id, name, category, price, specs, description, details, image, images, link FROM products ORDER BY id ASC");
        $rows = $stmt->fetchAll();
        if (empty($rows)) return null; // tabla vacía -> el llamador usa el fallback JSON
        foreach ($rows as &$r) {
            $r['id'] = (int)$r['id'];
            $dec = json_decode($r['images'] ?? '[]', true);
            $r['images'] = is_array($dec) ? $dec : (empty($r['image']) ? array() : array($r['image']));
        }
        unset($r);
        return $rows;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Carga los envíos del formulario desde la tabla `submissions`.
 * @return array|null
 */
function db_load_submissions() {
    $pdo = get_db();
    if (!$pdo) return null;
    try {
        return $pdo->query("SELECT * FROM submissions ORDER BY id DESC LIMIT 200")->fetchAll();
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Siembra la BD desde los archivos JSON (una sola vez, solo si la tabla está vacía).
 * Devuelve array('ok'=>bool,'msg'=>string,'products'=>int,'settings'=>int).
 */
function db_seed() {
    $pdo = get_db();
    if (!$pdo) {
        return array('ok' => false, 'msg' => 'Sin conexión a la base de datos', 'products' => 0, 'settings' => 0);
    }
    $seededProducts = 0;
    $seededSettings = 0;
    try {
        $base = dirname(__DIR__);
        // Productos
        $n = (int)$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
        if ($n === 0) {
            $file = $base . '/data/products.json';
            if (file_exists($file)) {
                $products = json_decode(file_get_contents($file), true) ?: array();
                sync_products_db($products);
                $seededProducts = count($products);
            }
        }
        // Configuración
        $n = (int)$pdo->query("SELECT COUNT(*) FROM settings")->fetchColumn();
        if ($n === 0) {
            $file = $base . '/config.json';
            if (file_exists($file)) {
                $cfg = json_decode(file_get_contents($file), true) ?: array();
                db_save_settings($cfg);
                $seededSettings = count($cfg);
            }
        }
        return array('ok' => true, 'msg' => 'Datos sembrados correctamente.', 'products' => $seededProducts, 'settings' => $seededSettings);
    } catch (PDOException $e) {
        return array('ok' => false, 'msg' => 'Error: ' . $e->getMessage(), 'products' => $seededProducts, 'settings' => $seededSettings);
    }
}
