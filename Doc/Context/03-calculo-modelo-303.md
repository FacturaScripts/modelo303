# Cálculo del modelo 303

Reparte los importes de las partidas de IVA a las casillas oficiales y calcula la cadena de resultado.

## Cómo es hoy

`Lib/Modelo303.php`:

1. `taxWhere()` define el conjunto de partidas: misma empresa y ejercicio, fechas de la regularización, series con IVA, sin asientos de apertura ni cierre y sin los asientos de **ninguna** regularización de IVA. Lo usan las pestañas, el resumen y el asiento.
2. `taxSummary()` carga el resumen completo (sin paginar) y `loadFromResumen()` lo reparte.
3. `addMovimiento()` decide la casilla: primero por el tipo de operación de la factura (`addMovimientoEspecial()`), después por cuenta especial y tipo en `casillaMap`.

| Caso | Casillas |
|---|---|
| Compra con inversión del sujeto pasivo | 12/13 devengado y 28/29 deducible |
| Compra intracomunitaria | 10/11 y 36/37 |
| Importación | 32/33, solo si el IVA es mayor de 0 (el DUA); decide el tipo, no la cuenta IVASIM (`a7c059e`) |
| Venta exenta | Base informativa: intracomunitaria 59, exportación 60, inversión 122, cargadas desde las facturas |
| Tipo 0 % en IVAREP | Base 150 sin cuota |
| Compra al 0 % | No va a la 28 |
| Recargo de equivalencia | 0,5 % a 168-170; 1,4 %, 1,75 % y 5,2 % a sus filas |

Un importe sin casilla genera un aviso (`registrarNoMapeado()`), no se pierde en silencio.

`computeResultado()` calcula 64, 66, 69, 71 y 87 con las casillas manuales. La 112 solo cuenta desde 2026; antes se pone a 0 con aviso. `Modelo303::pendingCarryover()` y `previousRegularization()` calculan la casilla 110 («Rellenar casilla 110»): suman el resultado negativo del periodo anterior si se pidió compensar y nunca toman la regularización anual como anterior.

## Por qué

- El reparto solo por cuenta y tipo mandaba las compras con inversión del sujeto pasivo a las casillas de ventas (`365b1e1`).
- Las pestañas usaban consultas distintas y no cuadraban (`fa34240`); el resumen usaba la lista paginada y se corrigió en `f3fb68e`.
- La casilla 27 no sumaba varias cuotas y el recargo del 0,5 % no tenía casilla (`f3fb68e`).

## Al cambiarlo

Prueba con `Modelo303SquaresTest`, `Modelo303CarryoverTest` y `ComprobarFechaDevengoTest` (la fecha de devengo la fija la fecha del asiento).
