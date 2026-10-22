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

/** Crea las tablas si no existen. Devuelve array('ok'=>bool,'msg'=>string). */
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
            specs VARCHAR(255),
            description TEXT,
            image VARCHAR(255),
            link VARCHAR(255) DEFAULT '#contacto',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        return array('ok' => true, 'msg' => 'Tablas creadas correctamente.');
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
        $pdo->exec("TRUNCATE TABLE products");
        $stmt = $pdo->prepare("INSERT INTO products (id, name, category, specs, description, image, link) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($products as $p) {
            $stmt->execute(array(
                (isset($p['id']) && $p['id'] ? (int)$p['id'] : null),
                $p['name'] ?? '',
                $p['category'] ?? '',
                $p['specs'] ?? '',
                $p['description'] ?? '',
                $p['image'] ?? '',
                $p['link'] ?? '#contacto'
            ));
        }
        return true;
    } catch (PDOException $e) {
        return false;
    }
}
