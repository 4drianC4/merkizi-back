# Reglas de negocio: caja y arqueo

Este documento registra las decisiones actuales para el manejo de efectivo físico en caja. No reemplaza las migraciones ni la implementación; sirve como referencia antes de continuar el desarrollo.

## Alcance

Una sesión de caja representa el efectivo manejado en una caja física durante un turno o periodo de operación.

- Una caja solo puede tener una sesión activa a la vez: `open` o `reconciliation_pending`.
- Solo una caja con estado `active` puede abrir una sesión.
- La sesión comienza con un fondo inicial (`opening_balance`), este puede comenzar en 0 actualmente.
- La sesión se cierra con arqueo físico; por ahora no se implementará el flujo de finalizar sin arqueo.

## Pagos y efectivo físico

`payments` registra todos los pagos de una venta, sin importar su método: efectivo, tarjeta o QR.

`cash_movements` registra únicamente cambios de efectivo físico en la caja. No debe registrar pagos QR ni pagos con tarjeta. Cada movimiento identifica al usuario que lo registró mediante `recorded_by`.

Cuando una venta se paga en efectivo se registran ambos hechos:

1. Un pago en `payments` con método `cash`.
2. Un movimiento positivo en `cash_movements` de tipo `sale`, asociado a la sesión de caja.

Cuando una venta se paga por QR o tarjeta, solo se registra el pago en `payments`. No se crea un movimiento de caja, porque no entra dinero físico a la gaveta.

No se deben sumar directamente los pagos en efectivo y los movimientos de caja para calcular el efectivo esperado: una venta en efectivo ya está representada por su movimiento de caja.

## Movimientos de caja

Los importes de `cash_movements` son montos con signo:

- Positivo: efectivo que entra a la caja, por ejemplo una venta en efectivo o un ingreso manual.
- Negativo: efectivo que sale de la caja, por ejemplo un gasto, retiro para depósito o devolución en efectivo.

Cada movimiento debe estar asociado a una sesión de caja.

Los movimientos manuales permitidos son:

| Tipo       | Signo guardado | Uso                                                                                  |
| ---------- | -------------- | ------------------------------------------------------------------------------------ |
| `sale`     | Positivo       | Movimiento automático generado por una venta pagada en efectivo. No se crea manualmente. |
| `cash_in`  | Positivo       | Ingreso manual de efectivo, por ejemplo reposición de cambio.                        |
| `cash_out` | Negativo       | Retiro para depósito, caja fuerte o resguardo; el dinero sigue siendo de la empresa. |
| `expense`  | Negativo       | Gasto pagado desde la gaveta, por ejemplo insumos urgentes o transporte.             |

El usuario siempre ingresa un monto positivo. El backend define el signo según el tipo y exige un concepto; el comprobante es opcional.

`reason` explica el motivo del movimiento y siempre es obligatorio. `reference` es opcional y almacena el identificador del comprobante que respalda el movimiento físico, por ejemplo el número de una factura, recibo, boleta de depósito o vale de reposición.

`payments.reference` tiene un propósito distinto: identifica una operación de pago externa, como el código de una transacción QR, tarjeta o transferencia. No se deben duplicar referencias entre un pago en efectivo y su movimiento automático `sale`, pues ambos ya quedan vinculados a la misma venta.

## Efectivo esperado

El sistema calcula el efectivo esperado con la siguiente fórmula:

```text
efectivo esperado = monto inicial + suma de movimientos de caja
```

La suma incluye solamente movimientos de efectivo físico. El cálculo usa aritmética decimal, nunca `float`.

## Arqueo y cierre ciego

Al cerrar, el usuario cuenta físicamente el dinero de la caja e ingresa el total contado.

```text
diferencia = efectivo contado - efectivo esperado
```

- Diferencia positiva: sobra efectivo.
- Diferencia negativa: falta efectivo.
- Diferencia cero: la caja cuadra.

Los datos de arqueo son:

| Campo                   | Origen                                       |
| ----------------------- | -------------------------------------------- |
| `opening_balance`       | Fondo ingresado al abrir la sesión.          |
| `expected_cash_balance` | Calculado por el sistema.                    |
| `counted_cash_balance`  | Total físico contado por el usuario.         |
| `cash_difference`       | Calculado por el sistema.                    |
| `difference_note`       | Observación del usuario ante una diferencia. |

El arqueo es ciego: antes de registrar el conteo, la pantalla no revela el efectivo esperado al cajero.

El flujo acordado es:

1. Con la sesión en `open`, el cajero cuenta el efectivo físico e ingresa solamente `counted_cash_balance`.
2. El backend bloquea la sesión, calcula el esperado y la diferencia, guarda ambos junto con el contado y cambia el estado a `reconciliation_pending`.
3. La respuesta revela el esperado y la diferencia. Mientras la sesión está en `reconciliation_pending`, no se pueden registrar nuevas ventas, pagos ni movimientos de efectivo.
4. Si la diferencia es distinta de cero, el cajero debe proporcionar `difference_note`.
5. Al confirmar, el backend valida nuevamente el estado y la nota, y cambia la sesión a `closed`.

El importe contado queda congelado al iniciar la conciliación. No se mantiene una transacción de base de datos mientras el cajero escribe la nota: el estado intermedio conserva el resultado y bloquea operaciones entre las dos peticiones HTTP.

Estados de la sesión:

```text
open
  └─ operaciones de caja permitidas

reconciliation_pending
  └─ conteo, esperado y diferencia guardados
  └─ no se permiten nuevas operaciones
  └─ se espera nota si existe diferencia y confirmación final

closed
  └─ sesión finalizada e inmutable
```

La interfaz puede mostrar el resultado solo después de la primera petición. El backend sigue siendo la fuente de verdad: calcula y guarda los importes en el primer paso, y valida que no hayan cambiado en el paso final.

## Actividad de una sesión de caja

El apartado de Cajas necesitará mostrar las operaciones de una caja durante una sesión determinada. Inicialmente se resolverá con una consulta normal y un endpoint de actividad, no con una vista materializada de base de datos.

La actividad reunirá cronológicamente:

- pagos de pedidos de todos los métodos (`cash`, QR y tarjeta);
- movimientos físicos de efectivo de la sesión (`cash_movements`).

Los pagos QR y tarjeta deben aparecer en la auditoría de actividad, pero su efecto sobre la caja física es cero. El cálculo de `expected_cash_balance` continuará usando únicamente `cash_movements`.

Una venta en efectivo tiene un `payment` y un `cash_movement` de tipo `sale`. En la actividad se mostrará la venta desde `payments` y se excluirá el movimiento `sale`, para no mostrar dos veces el mismo hecho. Los movimientos manuales (`cash_in`, `cash_out` y `expense`) sí aparecerán desde `cash_movements`.

La consulta normal mantiene la información en tiempo real. Una vista materializada solo se evaluará más adelante si se necesitan reportes históricos pesados y se acepta actualizar sus datos mediante un proceso de refresco.

## Pendiente para etapas posteriores

- Agregar un flujo excepcional para finalizar sin arqueo.
- Evaluar guardar quién cerró la sesión (`closed_by`), que puede ser diferente de quien la abrió.
- Evaluar un conteo por denominación de billetes y monedas.
- Evaluar registrar quién realizó cada pago para mostrarlo en la actividad de sesión.
- Agregar paginación a la actividad de sesión si el historial puede crecer lo suficiente para requerirla.
