# 0003. Código común de API y ficheros en traits

- Fecha: 2026-11-26
- Commits: `f3fb68e`
- Estado: vigente

## Contexto

Los modelos 303 y 390 comparten código de API y de formato de campos.

## Decisión

`Modelo303ApiTrait` y `Modelo303FileTrait` son traits y no clases base, para que no se desplieguen en `Dinamic/Lib`, común a todos los plugins.

## Consecuencias

Los métodos privados del trait se ven desde cada clase que lo usa; añadir un tercer modelo requiere usar los mismos traits.
