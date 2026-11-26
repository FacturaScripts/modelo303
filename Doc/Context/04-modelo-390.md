# Modelo 390 (resumen anual)

Es una regularización con `periodo = Y`. Se calcula con casillas propias del 390, no sumando los 303.

## Cómo es hoy

`Lib/Modelo390.php` (casillas de 2024 a 2026):

- Régimen general: mismo resumen que el 303 (`Modelo303::taxSummary()`) sobre todo el año, repartido por tipo de IVA con las tablas `DEV_*` y `DED_*`. Todo el deducible va como bienes y servicios corrientes.
- Resultado de la liquidación y de las liquidaciones: de las regularizaciones trimestrales o mensuales guardadas del ejercicio (`periodicResults()`).
- Volumen de operaciones y operaciones específicas: de partidas y facturas de venta.
- Casillas manuales: 96, 106, 107 y 112.
- `ZERO_SQUARES_SINCE_2025`: casillas que desde 2025 el diseño exige a 0.
- `SECTIONS` define las filas de la pantalla y del resumen de la API.

**No** calcula prorrata, sectores diferenciados, régimen simplificado, criterio de caja ni otros regímenes especiales. La regularización anual no genera asiento (el de los periodos ya lo tiene).

## Por qué

Antes el 390 solo mostraba un resumen del 303 y descargaba un .303; en `f3fb68e` se hizo un modelo propio con su fichero.

## Al cambiarlo

Prueba con `Modelo390SquaresTest`. En `computeTotals()` hay una errata de las instrucciones de 2025 documentada en un comentario (casilla 646).
