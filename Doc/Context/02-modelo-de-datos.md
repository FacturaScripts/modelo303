# Modelo de datos

El plugin no crea tablas: añade columnas a `regularizacionimpuestos` del core mediante `Extension/Table/regularizacionimpuestos.xml`.

## Cómo es hoy

`RegularizacionImpuesto` (core) guarda empresa, ejercicio, `periodo` (`T1`-`T4`, `01`-`12` o `Y` para el anual), fechas, subcuentas de acreedores y deudores, y el asiento (`idasiento`, `fechaasiento`, `bloquear`).

Columnas añadidas por el plugin:

| Columnas | Uso |
|---|---|
| `tipodeclaracion`, `iban`, `sinactividad`, `rectificativa`, `justificanteant` | Datos de presentación del fichero .303. `tipodeclaracion` vacío significa que se deduce del resultado (`Modelo303::declarationType()`) |
| `c65`, `c68`, `c70`, `c76`-`c78`, `c108`-`c112` | Casillas del 303 de introducción manual, sin origen en la contabilidad. `c65` (% a la Administración del Estado) vale 100 por defecto |
| `c390_96`, `c390_106`, `c390_107`, `c390_112`, `actividad390`, `claveactividad390`, `epigrafeiae390`, `operaciones347` | Casillas manuales y datos de presentación del 390 (en la regularización anual) |
| `asientomanual` | El asiento se vinculó a mano y no lo creó el plugin: no se borra al borrar la regularización (ver `07-asiento-de-regularizacion.md`) |

Las listas de casillas manuales están en `Modelo303::MANUAL_SQUARES` y `Modelo390::MANUAL_SQUARES`.

Los modelos de unión leen `partidas`, `asientos`, `subcuentas`, `cuentas`, `cuentasesp`, `series` y las facturas (para conocer `operacion`):

- `PartidaImpuestoResumen`: partidas agrupadas por subcuenta, tipo de IVA, recargo, operación de la factura y tipo de documento (`compra` o `venta`). La cuota es el importe contabilizado (debe más haber), con el signo invertido en bases negativas.
- `PartidaImpuesto`: el detalle partida a partida; busca la factura por el asiento si la partida no la indica.

## Por qué

- La cuota sale siempre del importe contabilizado y nunca de base por tipo, para evitar desfases de céntimos (`fa34240`).
- El recargo se reconoce por `recargo > 0`, no por la cuenta especial, porque el core lo contabiliza en IVAREP cuando no hay subcuenta IVARRE (`b6040a0`).
- El JOIN con las facturas usa subconsultas que solo exponen `idasiento` y `operacion` para no duplicar columnas como `fecha` (`365b1e1`).

## Al cambiarlo

Añadir una casilla manual obliga a tocar la tabla XML, la lista de casillas manuales, el `XMLView` de presentación y la API (`Modelo303ApiTrait`).
