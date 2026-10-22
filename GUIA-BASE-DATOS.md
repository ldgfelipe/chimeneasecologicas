# 🗄️ Guía: Base de datos MySQL (fuente de verdad)

La base de datos es la **fuente de verdad** para todo el sistema:

- Configuración del sitio (tabla `settings`)
- Productos (tabla `products`)
- Envíos del formulario de contacto (tabla `submissions`)

Los archivos `config.json`, `data/products.json` y `data/submissions.json` solo se
usan como **respaldo de solo lectura**: si la base de datos no está disponible,
el sitio cae automáticamente a esos archivos para no romperse.

---

## 1. Crear la base de datos en cPanel

1. Entra a **cPanel** → **MySQL Databases**.
2. Crea una base de datos (ej. `chimeneas`) → quedará `TUUSUARIO_chimeneas`.
3. Crea un usuario (ej. `chimeneas`) → `TUUSUARIO_chimeneas`.
4. Agrega el usuario a la base con **ALL PRIVILEGES**.
5. Anota los datos:

   | Dato | Valor típico |
   |------|---------------|
   | Host | `localhost` |
   | Base de datos | `TUUSUARIO_chimeneas` |
   | Usuario | `TUUSUARIO_chimeneas` |
   | Contraseña | la que definiste |

---

## 2. Configurar credenciales

Crea el archivo `api/db-credentials.php` (copia del ejemplo):

```php
<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'TUUSUARIO_chimeneas');
define('DB_USER', 'TUUSUARIO_chimeneas');
define('DB_PASS', 'TU_CONTRASEÑA');
```

> `api/db-credentials.php` está en `.gitignore`, así que **nunca** se sube al
> repositorio ni se expone públicamente (además está bloqueado por `.htaccess`).

---

## 3. Crear tablas y sembrar datos

Desde el **panel de administración**:

`https://tudominio.com/admin/` → pestaña **Base de datos** → **Crear tablas / Base de datos**

Esto crea las tablas y siembra la configuración y los productos desde los JSON
(una sola vez). Después presiona **Guardar todo en la BD** para asegurar la copia.

### Alternativa por SSH

```bash
php api/install.php
```

---

## 4. Verificar

En la pestaña **Base de datos** del admin verás:

```
Estado: Conectada · MySQL 8.0.x
Registros: productos = 40 · envíos = 0 · configuraciones = 15
```

---

## Estructura de las tablas

### `settings` (configuración clave/valor)

| Campo | Tipo | Descripción |
|-------|------|-------------|
| skey | VARCHAR(100) PK | Clave (ej. `whatsapp`, `email`, `images`) |
| svalue | TEXT | Valor (los arrays se guardan como JSON) |

### `products`

| Campo | Tipo | Descripción |
|-------|------|-------------|
| id | INT | Identificador |
| name | VARCHAR(255) | Nombre |
| category | VARCHAR(100) | Categoría |
| price | VARCHAR(50) | Precio |
| specs | VARCHAR(255) | Especificaciones (resumen) |
| description | TEXT | Descripción (modal) |
| details | TEXT | Detalles técnicos (modal) |
| image | VARCHAR(255) | Imagen principal |
| images | TEXT | Galería (JSON array) |
| link | VARCHAR(255) | Enlace del botón Cotizar |
| created_at | DATETIME | Automático |

### `submissions`

| Campo | Tipo | Descripción |
|-------|------|-------------|
| id | INT AUTO_INCREMENT | Identificador |
| date | DATETIME | Fecha de envío |
| name | VARCHAR(255) | Nombre del cliente |
| email | VARCHAR(255) | Correo |
| phone | VARCHAR(50) | Teléfono |
| message | TEXT | Mensaje |
| sent | TINYINT(1) | 1 = se envió email, 0 = solo se guardó |

---

## ⚙️ Comportamiento

| Acción | Con BD | Sin BD (fallback) |
|--------|--------|-------------------|
| Guardar configuración (admin) | escribe en `settings` | escribe `config.json` |
| Subir imagen de sección (admin) | escribe en `settings` | escribe `config.json` |
| Agregar/editar/eliminar producto (admin) | escribe en `products` | escribe `data/products.json` |
| Leer configuración (frontend) | lee `settings` | lee `config.json` |
| Leer productos (frontend) | lee `products` | lee `data/products.json` |
| Formulario de contacto | guarda en `submissions` + `mail()` | guarda en `data/submissions.json` + `mail()` |
| Exportar CSV | ✅ | ✅ |

---

## 🛡️ Seguridad

- `api/db-credentials.php` → ignorado por git y bloqueado por `.htaccess`
- `api/db.php` → bloqueado por `.htaccess` (solo se usa con `require` interno)
- `api/install.php` y `api/migrate.php` → bloqueados por HTTP (solo CLI/SSH)
- `config.json` y `data/*.json` → bloqueados por `.htaccess`
- La contraseña del admin vive en `admin/.password` (hash bcrypt, ignorado por git)
