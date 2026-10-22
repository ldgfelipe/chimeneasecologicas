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
            message TEXT,
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
        // Migraciones de columnas (para instalaciones previas)
        $migrations = array(
            'products' => array(
                'price' => "ALTER TABLE products ADD COLUMN price VARCHAR(50) DEFAULT '' AFTER category",
                'details' => "ALTER TABLE products ADD COLUMN details TEXT AFTER description",
                'images' => "ALTER TABLE products ADD COLUMN images TEXT AFTER image",
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
