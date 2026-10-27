# Integración de ventas

Contratos definitivos del backend para `Requerimientos_Backend_Modulo_Ventas_ChefsitoMel.docx`. Todas las rutas de esta guía empiezan en `/api/v1`; las solicitudes Bruno están en `Mesas`, `Pedidos` y `Ventas`. Se reutilizan las rutas de catálogo y pedidos existentes.

## Preparación

Aplicar las nuevas tablas y permisos:

```bash
docker compose exec app php artisan migrate --seed --no-interaction
```

El seeder de demo solo se ejecuta en `local` y `testing`. Genera áreas, tres mesas por sucursal, una caja por sucursal, categorías/subcategorías, un plato, una variante y un extra. No abre turnos ni crea ventas automáticamente. El administrador es `test@example.com` / `password`; también existen `employee@example.com` y `cashier@example.com`. Las contraseñas existentes no se restablecen. El cajero de ejemplo no puede cerrar sesiones de caja y los empleados no pueden confirmar pagos externos, unir mesas, transferir, exportar ni devolver dinero salvo concesión explícita.

Configurar `URLBase` en Bruno. Autenticar con las solicitudes de `Autenticacion`: CSRF y login con cookies, o un token Sanctum ya emitido. `/user` devuelve `permission_codes`, rol y sucursales; los controles del frontend no sustituyen la autorización del backend. Actualizar los UUID de las variables con las respuestas de las consultas/creaciones. No enviar `tenant_id` ni un operador elegido por el cliente.

Antes de cobrar, abrir una sesión con `POST /cash-registers/{cashRegister}/sessions`; guardar `cashSessionId`. La caja debe pertenecer a la misma sucursal y estar abierta. El operador usa su propia sesión; un usuario con `caja.cajas.editar` en esa sucursal puede operar otras sesiones. Las sucursales se obtienen de las asignaciones del usuario autenticado.

## Flujo recomendado

1. `GET /categories` y `GET /products?branch_id=UUID&available=1`. El catálogo incluye subcategorías (`parent_id`, `children`), `effective_price`, variantes y extras admitidos.
2. `GET /areas?branch_id=UUID` y `GET /tables?branch_id=UUID`. Si es venta para llevar, omitir mesas.
3. `POST /accounts`: abrir una cuenta; guardar `accountId`. Para agregar consumo a una mesa ya ocupada, reutilizar su `active_account.id`, sin abrir otra cuenta.
4. `POST /orders` con `account_id`: agregar uno o más pedidos. Guardar `orderId`, IDs de ítems y `version`. El pedido devuelve precios y totales calculados por el servidor.
5. `GET /accounts/{account}`: leer el saldo actualizado. `POST /accounts/{account}/payments`: registrar abonos/pago mixto y guardar IDs. Consultar o resolver los pagos externos pendientes.
6. Con saldo cero y sin pendientes, `POST /accounts/{account}/close`: crear la venta final y liberar las mesas. Guardar `saleId`.
7. `GET /sales/history`, `GET /sales/{sale}` y `/audit` para historial, detalle y trazabilidad.

## Rutas y permisos

| Método | Ruta | Permiso |
|---|---|---|
| GET / POST | `/areas` | `salon.mesas.ver` / `salon.mesas.crear` |
| GET / POST | `/tables` | `salon.mesas.ver` / `salon.mesas.crear` |
| PATCH | `/tables/{table}` | `salon.mesas.editar` |
| POST | `/accounts` | `pos.venta.crear` |
| GET | `/accounts/open-tables` | `pos.venta.ver` |
| GET | `/accounts/{account}` | `pos.venta.ver` |
| POST | `/orders` | `pos.venta.crear` |
| GET | `/orders`, `/orders/{order}` | `pos.venta.ver` |
| PATCH | `/orders/{order}/items` | `pos.venta.editar` |
| POST | `/accounts/{account}/payments` | `pos.venta.cobrar` |
| GET | `/account-payments/{accountPayment}` | `pos.venta.ver` |
| PATCH | `/account-payments/{accountPayment}` | `pos.venta.confirmar_pago` |
| POST | `/accounts/{account}/close` | `pos.venta.cerrar` |
| POST | `/tables/merge` | `salon.mesas.unir` |
| POST | `/tables/transfer-items` | `salon.mesas.transferir` |
| GET | `/sales/history` | `pos.venta.historial` |
| GET | `/sales/{sale}` | `pos.venta.ver` |
| GET | `/accounts/{account}/audit`, `/sales/{sale}/audit` | `pos.venta.auditoria` |
| GET | `/sales/export` | `pos.venta.exportar` |
| POST | `/sales/{sale}/refunds` | `pos.venta.devolver` |

Todos los permisos de esta tabla tienen alcance de sucursal. Se comprueban empresa, usuario activo, asignación activa a sucursal y revocaciones individuales. Un UUID de otra empresa devuelve 404; un permiso/asignación insuficiente devuelve 403. Un UUID inexistente en un filtro de listado produce lista vacía, sin revelar datos ajenos.

## Crear cuenta y pedido

Cuenta de mesa:

```json
{
  "branch_id": "UUID",
  "service_type": "table",
  "table_id": "UUID",
  "cash_session_id": "UUID",
  "customer_name": "Cliente",
  "customer_tax_id": "123456",
  "guests": 2,
  "notes": "Observación"
}
```

`cash_session_id` es opcional al abrir; obligatorio al cobrar/cerrar si la cuenta aún no tiene sesión. Para llevar: `service_type: "takeaway"` y omitir `table_id`. Una mesa bloqueada/inactiva o de área inactiva no puede abrirse. Una mesa solo admite una cuenta activa. Respuesta 201: `{ "data": { "id": "UUID", "status": "open", "version": 1, "total": "0.00", ... } }`.

Pedido:

```json
{
  "account_id": "UUID",
  "discount": "0.00",
  "tax": "0.00",
  "notes": "Segundo pedido de la cuenta",
  "items": [{
    "product_id": "UUID",
    "variant_id": "UUID opcional",
    "quantity": "2.000",
    "extras": [{ "product_id": "UUID de extra habilitado" }],
    "notes": "Sin cebolla"
  }]
}
```

Omitir `variant_id` cuando no aplique. Cada extra agrega su precio a cada unidad del producto principal. La variante sustituye el precio base. No enviar importes unitarios ni nombres desde el cliente. El backend valida que productos, variantes y extras estén activos y pertenezcan al producto/empresa. `number` es correlativo por sucursal y no es el UUID.

Los importes son cadenas decimales con dos posiciones; cantidades admiten hasta tres. Moneda operativa BOB. Subtotal de cada línea = precio unitario (incluidos extras) × cantidad, redondeado al centavo con regla mitad hacia arriba. Total de pedido = subtotal − descuento + impuesto. Descuento no negativo ni superior al subtotal; impuesto no negativo. Son ajustes explícitos autorizados: no se calcula IVA/SIAT ni una tasa fiscal implícita.

`PATCH /orders/{order}/items` reemplaza la lista completa y exige `version`. Una línea existente acepta `id`, `quantity`, `notes` y conserva su precio/nombre histórico; una línea nueva omite `id` y utiliza los campos del alta. Las líneas omitidas se retiran; `items: []` vacía el pedido. Se rechaza reducir la cuenta por debajo de importes confirmados o reservados por pagos pendientes. La auditoría conserva el antes/después. Un pedido ya finalizado no se modifica.

## Cuenta, mesa y totales

`GET /accounts/{account}` devuelve, dentro de `data`:

```json
{
  "id": "UUID",
  "branch_id": "UUID",
  "status": "open",
  "version": 3,
  "service_type": "table",
  "opened_by": "UUID",
  "customer_name": "Cliente",
  "customer_tax_id": "123456",
  "guests": 2,
  "subtotal": "50.00",
  "discount": "0.00",
  "tax": "0.00",
  "tip": "0.00",
  "total": "50.00",
  "paid": "20.00",
  "pending": "10.00",
  "balance": "30.00",
  "tables": [],
  "orders": [],
  "payments": []
}
```

Los arrays contienen los registros reales; se muestran vacíos solo para abreviar. `balance = total - paid`; importe disponible para nuevas solicitudes = `balance - pending`. Los pagos pendientes no cuentan como cobro. Un abono o un pedido nuevo nunca cierra ni libera la mesa.

Estados:

| Entidad | Estados |
|---|---|
| Mesa | `free`, `occupied`, `blocked` (calculados) |
| Cuenta | `open`, `paid`, `merged`, `cancelled` |
| Pedido | `draft`, `paid`; los pedidos nuevos de cuenta quedan draft hasta cerrar |
| Pago de cuenta | `pending`, `confirmed`, `rejected`, `expired` |
| Venta final | `completed`, `partially_refunded`, `refunded` |
| Tipo de servicio | `table`, `takeaway` |
| Método de pago | `cash`, `qr`, `card`, `transfer` |

## Abonos, pagos mixtos y cierre

Enviar `Idempotency-Key: UUID` en pagos, cierres y devoluciones. Para cada operación lógica, generar un UUID nuevo y conservarlo ante timeout/reintento. No regenerarlo automáticamente en cada envío. Cambiar payload con una clave ya utilizada devuelve 409. El `idempotencyKey` inicial de Bruno es un marcador: reemplazarlo. Reenviar la misma operación no duplica pagos, propina, movimientos ni venta.

```json
{
  "cash_session_id": "UUID",
  "tip": "2.00",
  "payments": [
    { "method": "cash", "amount": "10.00", "received_amount": "20.00" },
    { "method": "qr", "amount": "17.00", "reference": "QR-123" }
  ]
}
```

`tip` incrementa la propina total de la cuenta en esa operación, una sola vez gracias a la clave. Un reintento no vuelve a sumarla. Los importes `amount` son lo aplicado a la cuenta, no el dinero entregado. Solo efectivo admite cambio: `change = received_amount - amount`. Excedentes se expresan con `received_amount`; no se aceptan pagos aplicados superiores al saldo no reservado. El lote es atómico: se guarda completo o no se guarda nada.

Respuesta 201:

```json
{
  "data": [
    { "id": "UUID", "method": "cash", "amount": "10.00", "received_amount": "20.00", "change": "10.00", "status": "confirmed" },
    { "id": "UUID", "method": "qr", "amount": "17.00", "change": "0.00", "status": "pending", "reference": "QR-123" }
  ],
  "account": { "id": "UUID", "status": "open", "total": "27.00", "paid": "10.00", "pending": "17.00", "balance": "17.00" }
}
```

QR, tarjeta y transferencia requieren referencia y nacen pendientes. Un operador con permiso específico los comprueba externamente y resuelve con `PATCH /account-payments/{id}` y `{ "status": "confirmed" }`, `rejected` o `expired`. No hay conexión bancaria ni confirmación automática. No se puede confirmar un pago rechazado/expirado; se debe emitir un nuevo intento con otra clave. La expiración es explícita, no existe un proceso automático de vencimiento. `GET /account-payments/{id}` consulta su estado.

El movimiento de efectivo se registra solo cuando entra efectivo real y por el importe aplicado, excluyendo el cambio. Los pagos externos no incrementan el efectivo del cajón. No se cierra una sesión de caja con pagos de cuenta aún pendientes.

`POST /accounts/{account}/close` acepta `cash_session_id` opcional si la cuenta ya tiene una válida. Exige al menos un pedido, saldo cero, ningún pago pendiente y sesión abierta. Crea una única venta, copia ítems/precios históricos, enlaza los pagos confirmados, marca pedidos y cuenta como pagados y libera todas las mesas en la misma transacción. Respuesta inicial 201; repetición del mismo cierre 200. `receipt` es comprobante interno, no fiscal.

Los pedidos/cobros nuevos bloquean la cuenta mientras se procesan. Las ediciones de ítems verifican la versión del pedido; transferencias/uniones verifican versiones de ambas cuentas. Ante 409 de versión, recargar y pedir al usuario que revise el cambio; no reintentar sobrescribiendo la versión silenciosamente. Las claves únicas de base de datos evitan duplicar ocupación de mesa, cobros idempotentes y venta por cuenta.

## Unión y transferencia

Los dos endpoints reciben `source_account_id`, `destination_account_id`, `source_version` y `destination_version`. Requieren cuentas abiertas de mesa dentro de la misma sucursal. Para mover a una mesa libre, abrir primero su cuenta vacía y usar su ID.

- Unión: el destino conserva su identidad y datos de cliente. Pedidos y pagos se trasladan conservando sus IDs; cada pago conserva `origin_account_id`, caja y operador original. Las propinas y los comensales registrados se suman. La cuenta fuente queda `merged` y apunta al destino. Todas las mesas permanecen ocupadas por la cuenta destino hasta su cierre.
- Transferencia: agregar `items: [{ "id": "UUID", "quantity": "1.000" }]`; o `transfer_all: true` y omitir `items`. Se conserva el precio histórico; descuentos e impuestos se prorratean por subtotal, dejando el residuo de redondeo en origen para conservar importes. No se duplican los pagos: permanecen en origen. No se puede dejar al origen con consumos inferiores a sus pagos confirmados+pendientes. Si se vacía sin pagos ni propina, la fuente se cancela y libera sus mesas.
- Las dos operaciones devuelven la cuenta destino actualizada y registran auditoría en fuente y destino. No envían pedidos a cocina ni cambian estados de preparación: no existe todavía integración de cocina.

## Historial, exportación y devoluciones

`GET /sales/history` acepta `q`, `branch_id`, `from`, `to`, `status`, `service_type`, `cashier_id`, `method`, `sort`, `direction`, `page`, `per_page`. `q` busca número exacto o coincidencia parcial en cliente/NIT; `from`/`to` son fechas locales de Bolivia, con ambos extremos incluidos. `sort` permite `created_at`, `number`, `total`, con `direction=asc|desc` y desempate estable por UUID. `per_page` admite 1..100. Devuelve `data`, `links`, `meta` y `filters`. El filtro de método consulta pagos confirmados, incluidos los mixtos.

El historial contiene ventas finalizadas de este flujo. Las cuentas abiertas se consultan por separado; no se fabrica una venta al crear un pedido. Los pedidos independientes del flujo anterior no se convierten retroactivamente en ventas. Los listados se restringen a la empresa y sucursales asignadas al usuario.

`GET /sales/{sale}` incluye productos y nombres/precios conservados, pagos, cajero, caja, descuentos, impuestos, propina, fechas, comprobante interno y devoluciones. `total` siempre conserva el valor original; `refunded_total` y `net_total` reflejan devoluciones.

`GET /sales/export` produce `sales.csv`, con los filtros de historial, sin paginación. Sus importes son brutos originales. Los campos de texto se protegen contra fórmulas de hoja de cálculo. No contiene facturas fiscales.

`POST /sales/{sale}/refunds` admite devolución operativa monetaria en efectivo: `cash_session_id`, `amount`, `reason`, más el header de idempotencia. El total acumulado no puede superar el cobro original. Conserva venta, ítems y pagos; registra un egreso en caja, operador y auditoría. Estado `partially_refunded` o `refunded`. Responde 201, también ante un reintento válido. No asigna un estado ficticio de anulación fiscal ni emite notas de crédito.

## Errores

| HTTP | Significado / códigos |
|---|---|
| 401 | Sin autenticación válida |
| 403 | Cuenta desactivada, permiso revocado, sucursal no asignada o caja de otro operador sin autorización |
| 404 | Registro inexistente o de otra empresa |
| 422 | Validación de payload, producto/variante/extra no disponible, importe o cantidad inválidos |
| 409 | `table_occupied`, `table_unavailable`, `account_unsettled`, `cash_session_required`, `version_conflict`, `idempotency_conflict`, `payment_exceeds_balance`, `payments_exceed_total`, `payment_not_pending`, `invalid_state`, `account_required` |

Errores de negocio: `{ "message": "Descripción", "code": "codigo_estable" }`. Validación Laravel: `{ "message": "Descripción", "errors": { "campo": ["Motivo"] } }`. Los errores HTTP de autenticación/autorización/binding utilizan `message` y no necesariamente incluyen `code`.

## Límites explícitos

- Disponibilidad del catálogo = producto activo y opción habilitada; no se garantiza stock físico ni se descuenta inventario. El control de lotes, recetas y reposición requiere integrar inventario.
- No se incluye facturación SIAT, CUF, envío fiscal, anulación fiscal, notas de crédito ni devoluciones de stock por producto/lote. No hay proveedor ni contrato fiscal definido en el documento. `receipt.fiscal=false`, `invoice=null`.
- Se conserva compatibilidad con `POST /orders` sin `account_id` y `/orders/{order}/payments` para pedidos independientes anteriores. Para cuentas, esta última ruta responde 409: todo abono/cobro debe usar `/accounts/{account}/payments`.
- Las garantías de bloqueo se implementan con transacciones y bloqueos de filas para PostgreSQL. Las pruebas automatizadas del entorno disponible usan SQLite en memoria; la concurrencia real entre conexiones requiere validación en PostgreSQL.
