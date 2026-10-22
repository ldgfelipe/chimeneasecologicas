# 🗄️ Guía: Conectar la base de datos MySQL

El sitio funciona **sin base de datos** (usa `config.json` y `data/products.json`).
La base de datos es una **capa opcional** que agrega:

- Historial de envíos del formulario de contacto (tabla `submissions`)
- Productos almacenados en MySQL (tabla `products`)
- Exportación de envíos a CSV/Excel
- Mayor seguridad y escalabilidad

> Si tu hosting **no tiene PHP/MySQL**, el sitio sigue funcionando exactamente igual
> con los archivos JSON. No es obligatorio configurar la BD.

---

## 1. Crear la base de datos en cPanel

1. Entra a **cPanel** → **MySQL Databases**.
2. En *Create New Database* escribe un nombre, por ejemplo `chimeneas`
   → se creará `TUUSUARIO_chimeneas`.
3. En *Create New User* crea un usuario, por ejemplo `chimeneas`
   → será `TUUSUARIO_chimeneas`.
4. En *Add User To Database* agrega el usuario a la base con **ALL PRIVILEGES**.
5. Anota los datos:

   | Dato | Valor típico |
   |------|---------------|
   | Host | `localhost` |
   | Base de datos | `TUUSUARIO_chimeneas` |
   | Usuario | `TUUSUARIO_chimeneas` |
   | Contraseña | la que definiste |

---

## 2. Subir las credenciales

Copia el archivo de ejemplo y complétalo:

```bash
cp api/db-credentials.example.php api/db-credentials.php
```

```php
<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'TUUSUARIO_chimeneas');
define('DB_USER', 'TUUSUARIO_chimeneas');
define('DB_PASS', 'TU_CONTRASEÑA');
```

> `api/db-credentials.php` está en `.gitignore`, así que **nunca** se sube al repositorio
> ni se expone públicamente (además está bloqueado por `.htaccess`).

---

## 3. Crear las tablas

Desde el **panel de administración** del sitio:

`https://tudominio.com/admin/` → pestaña **Base de datos** → **Crear tablas / Base de datos**

Luego presiona **Sincronizar productos a la BD** para copiar los 8 productos actuales.

### Alternativa por SSH

```bash
php api/install.php
```

---

## 4. Verificar

En la pestaña **Base de datos** del admin verás:

```
Estado: Conectada · MySQL 8.0.x
Registros: productos = 8 · envíos = 0
```

Y la pestaña **Formularios** mostrará los envíos con opción de **descargar CSV**.

---

## Estructura de las tablas

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

### `products`

| Campo | Tipo | Descripción |
|-------|------|-------------|
| id | INT | Mismo id que `data/products.json` |
| name | VARCHAR(255) | Nombre |
| category | VARCHAR(100) | Categoría |
| specs | VARCHAR(255) | Especificaciones |
| description | TEXT | Descripción (modal) |
| image | VARCHAR(255) | Ruta de la imagen |
| link | VARCHAR(255) | Enlace del botón Cotizar |
| created_at | DATETIME | Automático |

---

## ⚙️ Comportamiento

| Acción | Sin BD | Con BD |
|--------|--------|--------|
| Formulario de contacto | guarda en `data/submissions.json` + `mail()` | guarda en `submissions` + `mail()` |
| Productos (frontend) | lee `data/products.json` | lee tabla `products` |
| Productos (admin) | edita `data/products.json` | edita JSON **y** sincroniza a MySQL |
| Envíos (admin) | lee `data/submissions.json` | lee tabla `submissions` |
| Exportar CSV | ✅ | ✅ |

Si algún día la BD deja de estar disponible, el sitio **cae automáticamente a los
archivos JSON** sin romperse.

---

## 🛡️ Seguridad

- `api/db-credentials.php` → ignorado por git y bloqueado por `.htaccess`
- `api/db.php` → bloqueado por `.htaccess` (solo se usa con `require` interno)
- `api/install.php` y `api/migrate.php` → bloqueados por HTTP (solo CLI/SSH)
- `config.json` y `data/*.json` → bloqueados por `.htaccess`
- La contraseña del admin vive en `admin/.password` (hash bcrypt, ignorado por git)
