# 0001. Calcular los modelos desde la contabilidad

- Fecha: 2020-04-16
- Commits: `48e8c2e`, `fa34240`
- Estado: vigente

## Contexto

El IVA a ingresar debe coincidir con lo contabilizado, no con la facturación.

## Decisión

Los modelos leen las partidas de los asientos (`partidas`, `subcuentas`, cuentas especiales). Las facturas solo aportan el tipo de operación y las bases exentas de venta.

## Consecuencias

Una factura sin asiento no cuenta; el plugin la avisa. Todas las vistas deben usar `Modelo303::taxWhere()`.
