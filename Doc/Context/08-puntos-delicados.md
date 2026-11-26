# Puntos delicados

- **Datos de la contabilidad.** Una factura sin asiento no entra en el modelo. Hay avisos en pantalla y la API las devuelve con `detalle`.
- **Consultas que deben coincidir.** Pestañas, resumen, asiento, fichero y API tienen que usar el mismo conjunto de partidas (`taxWhere()`). Fue el origen de `fa34240`.
- **Compatibilidad con el core.** `loadFromData()` de los modelos de unión pasó a ser público con un segundo parámetro `$exclude` y rompió la carga (`8272625`). El refactor a `Where` y `JoinModel` se revirtió y se rehízo (`651dfc4`, `c65e93e`).
- **PostgreSQL.** Hubo errores propios (`98a9c5a`); las sumas se redondean con `CAST ... DECIMAL` por eso.
- **Diseños por año.** Los ficheros dependen del ejercicio; las casillas 112 (desde 2026) y las de tipos suprimidos (desde 2025) cambian según el año.
- **Fechas.** Las fechas de los modelos llegan en formato d-m-Y: hay que normalizarlas antes de compararlas. En SQL escrito a mano usa Y-m-d, que entienden MySQL y PostgreSQL (`treasuryDates()` usaba d-m-Y).
- **Nombres del periodo anual.** La regularización guarda `Y`, la API publica `ANUAL`, el plugin Informes pide `ANNUAL` y el fichero usa `0A`. `Modelo303::ANNUAL_PERIODS` reúne todos y lo usan `normalizePeriod()` y `treasury()`: si aparece otro, añádelo ahí.
- **Varias empresas.** Toda consulta de partidas debe filtrar por ejercicio o empresa; `treasury()` sumaba las de todas las empresas en las mismas fechas.
- **Clases del core.** Usa siempre `Dinamic\Model` y `Dinamic\Lib`, también en `Lib/Accounting`, para no saltarse las extensiones de otros plugins.
- **Título del 390.** La ficha es la misma para el 303 y el 390: `getPageData()` y la vista principal cambian el título a «Modelo 390» cuando el periodo es `Y` (`$annualTitle`). La pestaña Modelo 390 es html y no se exporta sola: `exportModelo390()` la añade como tablas.
- **Datos sensibles en la API.** El IBAN se devuelve enmascarado (`maskIban()`). Los avisos de la API se traducen con `Tools::trans()`, como los del cálculo.
