# Arquitectura — Merkizi Backend

Monolito modular en Laravel 13. El backend es el único contenido de este repositorio; el frontend Angular vive en otro repositorio y consume la API REST bajo `/api/v1`.

## Stack

| Componente | Elección |
| --- | --- |
| Lenguaje / framework | PHP 8.4 / Laravel 13 |
| Base de datos | PostgreSQL 18 |
| Caché / sesión / colas | Redis 7.4 |
| Autenticación | Laravel Sanctum (sesión SPA, cookies) |
| Calidad | PHPUnit, Laravel Pint, PHPStan/Larastan |
| Contrato | OpenAPI 3.1 (`docs/api/openapi.yaml`) |
| CI | GitHub Actions (`.github/workflows/ci.yml`) |

## Estructura

```text
app/
  Http/
    Controllers/Auth/          # inicio y cierre de sesión
    Requests/Auth/             # Form Requests de autenticación
  Shared/
    Identity/
      Models/                  # User (identidad global)
    Tenancy/
      Concerns/                # HasTenantScope
      Http/Middleware/         # ResolveTenant (alias "tenant")
      Models/                  # Tenant, Branch, Plan, Subscription, PlanLimit, TenantMembership
      Resolvers/               # TenantResolver + UserMembershipTenantResolver
      Scopes/                  # TenantScope
      TenantContext.php        # contexto de empresa (singleton)
      TenancyServiceProvider.php
  Modules/
    Branches/                  # Actions, Policies, Http (Controller/Request/Resource), routes
    Catalog/                   # Categories, Products (+ precios por sucursal)
    Staff/                     # Employees
    Cash/                      # CashRegisters, CashSessions, CashMovements, Payments
    Sales/                     # Orders, OrderItems (snapshot) + acciones de pago
bootstrap/app.php              # middleware statefulApi, alias tenant, provider de tenancy
config/
database/
  migrations/                  # centralizadas
  factories/                   # espejo del namespace de los modelos
  seeders/                     # PlanSeeder (plan mayor) + DatabaseSeeder
docs/
  architecture/                # este documento
  api/openapi.yaml             # contrato REST
docker/
routes/
  api.php                      # prefijo v1: login, logout, user
tests/
  Feature/Auth/                # autenticación SPA
  Feature/Tenancy/             # aislamiento y resolución de empresa
  Feature/Modules/             # flujo mínimo integrado (operación mínima)
```

## Multiempresa y aislamiento

- `users` es identidad global; `tenants` es la empresa cliente; `tenant_memberships` vincula usuario y empresa; `branches` son las sucursales; `plans` / `subscriptions` / `plan_limits` gobiernan habilitaciones comerciales.
- Toda tabla propiedad de una empresa incluye `tenant_id`; `branch_id` cuando el dato es específico de sucursal.
- El contexto de empresa se resuelve desde una **membresía autorizada** (nunca desde el cuerpo de la petición). `ResolveTenant` establece el `TenantContext` por request y lo limpia al terminar.
- Los modelos con `HasTenantScope` reciben un `TenantScope` global que filtra por el `tenant_id` activo; sin contexto, la consulta no filtra (útil para jobs y comandos).
- `TenantMembership` **no** está sujeto al scope: es el puente que el resolver consulta para determinar la empresa del usuario.
- Los jobs transportarán el tenant explícitamente y limpiarán el contexto al finalizar (pendiente de implementar junto a los módulos).

## Planes y límites

El plan mayor (`pro`) se siembra con `max_branches = 10` y `max_cash_registers_per_branch = 5` (ver `PlanSeeder`). Los límites no son constantes globales; viven en `plan_limits`. Los permisos de usuario y las habilitaciones del plan se tratan por separado.

## Autenticación (SPA)

Flujo Sanctum por cookies: obtener cookie CSRF (`/sanctum/csrf-cookie`) → `POST /api/v1/login` → consumir API con sesión → `POST /api/v1/logout`. No se usan tokens en `localStorage`.

Rutas actuales:

- `POST /api/v1/login`
- `POST /api/v1/logout` (`auth:sanctum`)
- `GET /api/v1/user` (`auth:sanctum`)

## Convenciones

- Contrato `data` / `meta` / `links` para listados; errores con `code` estable y `request_id`.
- Códigos HTTP: `401` sesión ausente, `403` acceso denegado, `422` validación, `409` conflicto de estado.
- Montos exactos como cadenas decimales con moneda explícita; nunca `float`.
- Instantes en UTC; la zona y la fecha operativa se resuelven por sucursal.
- `Form Request` para toda entrada validada; validación de identificadores relacionados y de route model binding.
- Migraciones centralizadas; sin carpetas ni interfaces vacías.

## Calidad y CI

- `composer pint` / `composer pint:test` (Laravel Pint, `pint.json`).
- `composer phpstan` (PHPStan/Larastan, `phpstan.neon`, nivel 5).
- `composer test` o `php artisan test` (`.env.testing` usa PostgreSQL `merkizi_test`).
- Pipeline (`ci.yml`): validar Composer → instalar dependencias bloqueadas → Pint → Larastan → pruebas con PostgreSQL/Redis → validar OpenAPI → construir imagen.

## Referencias

La especificación funcional completa está en `guia/GUIA_CREACION.md` (reglas de negocio §8, orden de implementación §12 y pendientes §14).
