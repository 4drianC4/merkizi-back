# Reglas de negocio: caja y arqueo

Este documento registra las decisiones actuales para el manejo de efectivo físico en caja. No reemplaza las migraciones ni la implementación; sirve como referencia antes de continuar el desarrollo.

## Alcance

Una sesión de caja representa el efectivo manejado en una caja física durante un turno o periodo de operación.

- Una caja solo puede tener una sesión activa a la vez: `open` o `reconciliation_pending`.
- La sesión comienza con un fondo inicial (`opening_balance`).
- La sesión se cierra con arqueo físico; por ahora no se implementará el flujo de finalizar sin arqueo.

## Pagos y efectivo físico

`payments` registra todos los pagos de una venta, sin importar su método: efectivo, tarjeta o QR.

`cash_movements` registra únicamente cambios de efectivo físico en la caja. No debe registrar pagos QR ni pagos con tarjeta.

Cuando una venta se paga en efectivo se registran ambos hechos:

1. Un pago en `payments` con método `cash`.
2. Un movimiento positivo en `cash_movements` asociado a la sesión de caja.

Cuando una venta se paga por QR o tarjeta, solo se registra el pago en `payments`. No se crea un movimiento de caja, porque no entra dinero físico a la gaveta.

No se deben sumar directamente los pagos en efectivo y los movimientos de caja para calcular el efectivo esperado: una venta en efectivo ya está representada por su movimiento de caja.

## Movimientos de caja

Los importes de `cash_movements` son montos con signo:

- Positivo: efectivo que entra a la caja, por ejemplo una venta en efectivo o un ingreso manual.
- Negativo: efectivo que sale de la caja, por ejemplo un gasto, retiro para depósito o devolución en efectivo.

Cada movimiento debe estar asociado a una sesión de caja.

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

La consulta normal mantiene la información en tiempo real. Una vista materializada solo se evaluará más adelante si se necesitan reportes históricos pesados y se acepta actualizar sus datos mediante un proceso de refresco.

## Pendiente para etapas posteriores

- Agregar un flujo excepcional para finalizar sin arqueo.
- Evaluar guardar quién cerró la sesión (`closed_by`), que puede ser diferente de quien la abrió.
- Evaluar un conteo por denominación de billetes y monedas.
