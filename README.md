# Merkizi API

Backend Laravel con PostgreSQL, Redis y autenticación de sesión mediante Sanctum. Las entidades de negocio y sus relaciones usan UUID.

## Requisitos

- Docker Engine o Docker Desktop en ejecución.
- Docker Compose v2 (`docker compose`).
- Git y, para las pruebas manuales, Bruno.
- Puerto local `8080` disponible.

PHP y Composer están incluidos en la imagen Docker; no necesitas instalarlos en tu equipo para este flujo. Los comandos siguientes se ejecutan desde la raíz del repositorio. Los ejemplos usan una terminal Bash o Zsh; en Windows puedes usar WSL.

```bash
docker --version
docker compose version
```

## 1. Preparar el entorno

Después de clonar el repositorio y entrar en su carpeta, crea `.env` si todavía no existe:

```bash
cp -n .env.example .env
```

En Linux, si tu UID/GID no son `1000`, exporta los tuyos para que los archivos creados en Docker pertenezcan a tu usuario. Estas variables deben estar presentes en las terminales desde las que ejecutes Compose:

```bash
export LOCAL_UID=$(id -u)
export LOCAL_GID=$(id -g)
```

También puedes guardar esos valores numéricos como `LOCAL_UID` y `LOCAL_GID` en tu `.env` local.

La configuración de `.env.example` ya corresponde a los servicios de `compose.yaml`:

```dotenv
APP_ENV=local
APP_URL=http://localhost:8080
LOG_CHANNEL=stderr

DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=merkizi
DB_USERNAME=merkizi
DB_PASSWORD=local_dev_only

REDIS_CLIENT=phpredis
REDIS_HOST=redis
REDIS_PORT=6379
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=false
SANCTUM_STATEFUL_DOMAINS=localhost:4200,localhost:8080
```

`db` y `redis` son nombres internos de Docker. Ejecuta Artisan dentro del servicio `app`; esos nombres no se resuelven desde PHP ejecutado directamente en tu equipo. PostgreSQL y Redis no publican puertos al host.

## 2. Construir e instalar dependencias

```bash
docker compose build app
docker compose up -d db redis
docker compose run --rm app composer install
docker compose run --rm app php artisan key:generate --no-interaction
docker compose run --rm app php artisan config:clear
```

Genera `APP_KEY` solo en la instalación inicial. Si ya tienes una clave válida, omite `key:generate` para conservar las sesiones y los datos cifrados existentes.

No uses `composer run setup` para este procedimiento: el flujo de Docker se prepara con los comandos de esta guía. No hace falta compilar assets para probar la API JSON.

## 3. Crear las tablas y cargar los datos iniciales

```bash
docker compose run --rm app php artisan migrate --seed --no-interaction
```

Las migraciones crean las tablas y el seeder carga los datos. `db:seed` por sí solo no crea tablas.

En `APP_ENV=local` o `testing`, el seeder crea:

- Usuario `test@example.com`, contraseña inicial `password`.
- Empresa `Merkizi Demo`.
- Membresía activa de administrador para el usuario de prueba.
- Plan `Pro` y sus límites, con una suscripción activa para la empresa.

Repetir el seeder no duplica estos registros ni restablece la contraseña de un usuario existente. No crea empleados, sucursales, productos, cajas ni pedidos de ejemplo; los crearás desde la API. La cuenta de demostración no se crea en producción.

## 4. Levantar y comprobar los servicios

```bash
docker compose up -d --build
docker compose ps
curl -i http://localhost:8080/up
```

La comprobación `/up` debe responder HTTP `200`. La API tiene el prefijo `http://localhost:8080/api/v1`.

| Servicio | Función |
| --- | --- |
| `app` | PHP 8.4 / Laravel mediante PHP-FPM |
| `web` | Nginx, accesible en `http://localhost:8080` |
| `db` | PostgreSQL 18 |
| `redis` | Sesiones, caché y colas |
| `worker` | Procesa las colas `banking`, `default` y `exports` |
| `scheduler` | Ejecuta el planificador de Laravel |

Para consultar las rutas y migraciones instaladas:

```bash
docker compose exec app php artisan route:list --path=api
docker compose exec app php artisan migrate:status
```

## 5. Probar con Bruno

Abre la carpeta `collections` como colección en Bruno. Contiene las solicitudes agrupadas por módulo y ejemplos de cuerpos JSON.

### Variables y autenticación

Configura las variables de la colección o un entorno local:

| Variable | Valor inicial |
| --- | --- |
| `URLBase` | `http://localhost:8080`, sin `/api/v1` ni barra final |
| `email` | `test@example.com` |
| `password` | `password` |
| `authentification` | Vacía para el flujo de sesión |

Mantén habilitados el almacenamiento y el envío de cookies en Bruno. Ejecuta, en este orden, las solicitudes de la carpeta `Autenticacion`:

1. `GET sanctum.csrf-cookie`: obtiene las cookies necesarias para CSRF.
2. `POST login`: inicia sesión con el correo y la contraseña configurados.
3. `GET user`: comprueba el usuario autenticado.

El script compartido envía `Origin`, `Referer` y `X-XSRF-TOKEN` para el flujo de sesión. Usa el mismo hostname durante todo el flujo; no alternes `localhost` y `127.0.0.1`.

El login devuelve el usuario, no un token Bearer. `authentification` permite usar un token Sanctum existente, sin escribir el prefijo `Bearer`. `POST logout` utiliza la sesión y sirve para cerrarla.

### IDs UUID y flujo de prueba

Las variables `branchId`, `categoryId`, `productId`, `employeeId`, `cashRegisterId`, `cashSessionId` y `orderId` contienen valores de ejemplo. Sustitúyelas manualmente por los UUID devueltos en `data.id` al crear un registro, o por IDs de los listados. No uses `1`, `2`, etc.

Un flujo básico de prueba es:

1. Crear una sucursal y guardar su UUID en `branchId`.
2. Crear una categoría y guardar su UUID en `categoryId`.
3. Crear un producto con precio y guardar su UUID en `productId`.
4. Crear un empleado si quieres probar el módulo de personal; guardar su UUID en `employeeId`.
5. Crear una caja para la sucursal y guardar su UUID en `cashRegisterId`.
6. Abrir una sesión de caja y guardar su UUID en `cashSessionId`.
7. Crear un pedido con esa sesión y el producto; guardar su UUID en `orderId`.
8. Registrar un pago en efectivo con el importe correspondiente al pedido.
9. Consultar el pedido y cerrar la sesión de caja.

Los listados pueden devolver `data: []` antes de crear registros. Ejecuta las solicitudes individualmente: la colección incluye operaciones de borrado, PUT/PATCH alternativos y ejemplos que necesitan IDs existentes, por lo que no está preparada para ejecutarse completa como una secuencia automática.

La carpeta `Sistema` también contiene rutas internas. Las solicitudes de almacenamiento requieren una URL temporal firmada generada por el servidor; no funcionan únicamente cambiando `storagePath`.

## 6. Consultar logs y base de datos

```bash
# Todos los servicios, últimas 100 líneas y seguimiento en tiempo real
docker compose logs -f --tail=100

# Laravel / PHP
docker compose logs -f --tail=100 app

# Nginx, colas y planificador
docker compose logs -f --tail=100 web worker scheduler

# PostgreSQL
docker compose logs -f --tail=100 db
```

`Ctrl+C` termina el seguimiento sin detener los contenedores. Con `LOG_CHANNEL=stderr`, los logs de Laravel aparecen en Docker. Si cambias a un canal que escribe archivos, puedes consultar el archivo correspondiente; por ejemplo, para el canal `single`:

```bash
docker compose exec app tail -f storage/logs/laravel.log
```

Para abrir una consola PostgreSQL dentro de Docker:

```bash
docker compose exec db psql -U merkizi -d merkizi
```

Dentro de `psql`, usa `\dt` para listar tablas y `\q` para salir.

## 7. Ejecutar pruebas automatizadas

**Usa una base exclusiva de pruebas.** La suite utiliza `RefreshDatabase`, que recrea tablas. Actualmente `phpunit.xml` no define `DB_DATABASE`: ejecutar las pruebas sin sobrescribirla puede borrar los datos de `merkizi`.

Crea la base de pruebas una sola vez, con PostgreSQL levantado:

```bash
docker compose exec db createdb -U merkizi merkizi_test
```

Si indica que `merkizi_test` ya existe, continúa. Limpia cualquier configuración cacheada antes de ejecutar la suite, para que se lean las variables de conexión de pruebas:

```bash
docker compose exec app php artisan config:clear
docker compose exec -e APP_ENV=testing -e DB_CONNECTION=pgsql -e DB_DATABASE=merkizi_test app php artisan test --compact
```

Para ejecutar solo las pruebas de UUID:

```bash
docker compose exec -e APP_ENV=testing -e DB_CONNECTION=pgsql -e DB_DATABASE=merkizi_test app php artisan test --compact tests/Feature/Modules/UuidIdentifiersTest.php
```

Las pruebas crean sus datos automáticamente. Si configuraste una variable `DB_URL` por tu cuenta, elimínala del entorno de pruebas para que no reemplace la conexión anterior.

Comprobaciones adicionales:

```bash
# Comprueba el formato sin modificar archivos
docker compose exec app composer pint:test

# Análisis estático
docker compose exec app composer phpstan
```

## 8. Uso diario, actualización y reinicio de datos

```bash
# Arrancar los servicios existentes
docker compose up -d

# Aplicar migraciones pendientes
docker compose exec app php artisan migrate --no-interaction

# Repetir únicamente la carga inicial de datos
docker compose exec app php artisan db:seed --no-interaction

# Detener contenedores conservando los volúmenes
docker compose down
```

Después de cambiar dependencias o configuración:

```bash
docker compose exec app composer install
docker compose exec app php artisan config:clear
docker compose restart app worker scheduler
```

Si cambias el Dockerfile, reconstruye con `docker compose up -d --build`.

### Recrear la base de desarrollo

**El siguiente procedimiento borra todas las tablas y datos de `merkizi`.** Úsalo solo cuando quieras empezar desde cero, por ejemplo, al aplicar el cambio inicial de IDs numéricos a UUID en una instalación anterior.

```bash
docker compose stop worker scheduler
docker compose exec app php artisan migrate:fresh --seed --no-interaction
docker compose start worker scheduler
```

Después, borra las cookies de esta API en Bruno y repite CSRF y login. Los UUID anteriores dejan de existir; actualiza las variables al crear nuevos registros. Evita `docker compose down -v` si quieres conservar datos: esa opción elimina también los volúmenes de PostgreSQL y Redis.

## Problemas frecuentes

| Error | Qué revisar |
| --- | --- |
| `could not translate host name "db"` | Ejecuta Artisan con `docker compose exec app`, no con el PHP del host. |
| `relation "plans" does not exist` o `Migration table not found` | Ejecuta `docker compose exec app php artisan migrate --seed --no-interaction`. |
| `No application encryption key has been specified` | Genera la clave con `docker compose exec app php artisan key:generate` en la instalación inicial. |
| `vendor/autoload.php` no existe | Ejecuta `docker compose run --rm app composer install` y vuelve a levantar los servicios. |
| HTTP `419` | Obtén la cookie CSRF antes del login; comprueba las cookies y `SANCTUM_STATEFUL_DOMAINS`. |
| HTTP `401` | Inicia sesión y comprueba que Bruno envíe las cookies. |
| HTTP `403`, código `tenant_required` | El usuario necesita una membresía activa. Para `test@example.com`, ejecuta el seeder en entorno local. |
| HTTP `404` en un recurso | Usa un UUID existente de la empresa autenticada. La ruta de empleados es `/api/v1/employees`. |
| HTTP `422` | Revisa `errors` o `code` en la respuesta: faltan campos, hay UUID inválidos o se incumple una regla de negocio. |
| `URL and cookie name are required` en Bruno | Recarga la colección actual y verifica `URLBase`; el script usa variables de colección y entorno. |
| `502 Bad Gateway` | Comprueba `docker compose ps` y los logs de `app` y `web`. |
| Permisos de escritura | Revisa `LOCAL_UID`/`LOCAL_GID` y que `storage` y `bootstrap/cache` sean escribibles por el usuario del contenedor. |

Si cambias `.env`, ejecuta `docker compose exec app php artisan config:clear` y reinicia `app`, `worker` y `scheduler`.
