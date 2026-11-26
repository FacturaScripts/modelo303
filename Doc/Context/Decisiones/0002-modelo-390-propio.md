# 0002. Modelo 390 propio con sus casillas

- Fecha: 2026-11-26
- Commits: `f3fb68e`
- Estado: vigente

## Contexto

El 390 era un resumen del 303 y la regularización anual descargaba un .303, que no es su formato.

## Decisión

`Modelo390` calcula las casillas del diseño oficial y `Modelo390File` genera el .390. La anual no genera asiento.

## Consecuencias

No cubre prorrata, simplificado, caja ni otros regímenes: esas casillas quedan a 0.
