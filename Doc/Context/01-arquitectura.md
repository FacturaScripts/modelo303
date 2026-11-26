# Arquitectura

El plugin calcula el modelo 303 (autoliquidación trimestral o mensual de IVA) y el modelo 390 (resumen anual) a partir de la **contabilidad**, no de la facturación. Los dos viven sobre el modelo del core `RegularizacionImpuesto`, ampliado con columnas propias.

## Cómo es hoy

No tiene modelos ni tablas propias, ni `require` en `facturascripts.ini` (versión mínima del core 2026.5). Las piezas:

| Pieza | Ruta | Qué hace |
|---|---|---|
| Listado | `Controller/ListRegularizacionImpuesto.php` | Dos pestañas sobre `RegularizacionImpuesto`: 303 (`periodo` distinto de `Y`) y 390 (`periodo = Y`). Menú Informes, submenú modelos fiscales |
| Ficha | `Controller/EditRegularizacionImpuesto.php` | Pestañas de resumen, compras, ventas, presentación AEAT, asiento y modelo 390. Descarga de ficheros, vincular/desvincular asiento, rellenar casilla 110, exportación |
| API | `Controller/ApiModelo303.php`, `ApiModelo390.php` | Endpoints de solo lectura. Rutas registradas en `Init.php` |
| Cálculo | `Lib/Modelo303.php`, `Lib/Modelo390.php` | Reparto de importes a casillas y cadena de resultado |
| Ficheros AEAT | `Lib/Modelo303File.php`, `Lib/Modelo390File.php` | Ficheros de posiciones fijas (.303 y .390) |
| API (respuesta) | `Lib/Modelo303Api.php`, `Lib/Modelo390Api.php` | Construyen el cuerpo JSON y el código HTTP |
| Traits | `Lib/Modelo303ApiTrait.php`, `Lib/Modelo303FileTrait.php` | Código común de API y de formato de campos |
| Asiento | `Lib/Accounting/VatRegularizationToAccounting.php` | Genera el asiento de regularización de IVA |
| Modelos de unión | `Model/Join/PartidaImpuesto.php`, `PartidaImpuestoResumen.php` | Partidas con IVA y su resumen agrupado |
| Extensiones | `Extension/Table/regularizacionimpuestos.xml`, `Extension/XMLView/ListPartida.xml` | Columnas nuevas y modal para vincular asiento |
| Vistas | `XMLView/*.xml`, `View/Modelo303.html.twig`, `View/Modelo390.html.twig` | Pantallas y tablas de casillas |

Cada modelo tiene la misma estructura: clase de cálculo, clase de respuesta de la API y clase del fichero. La pantalla, el fichero y la API llaman al mismo método (`Modelo303::fromRegularizacion()` o `Modelo390::fromRegularizacion()`) para dar siempre las mismas cifras.

Las dos clases de la API comparten un trait y los dos ficheros otro, y no una clase base, para que no se despliegue en `Dinamic/Lib`, común a todos los plugins.

## Por qué

- Una regularización no tiene por qué estar guardada para calcular su modelo: basta con empresa, ejercicio y fechas, y nunca se guarda (`f3fb68e`).
- Las consultas de las pestañas, del resumen y del asiento se unificaron porque no cuadraban entre sí (`fa34240`).

## Al cambiarlo

- Un cambio en el reparto a casillas afecta a pantalla, fichero y API a la vez: revisa `Test/main/Modelo303SquaresTest.php`, `Modelo390SquaresTest.php` y los de fichero y API.
- `Dinamic` no se reconstruye solo: tras añadir clases o XMLView hay que reconstruirlo.
- Las clases usan `FacturaScripts\Dinamic\...` para poder extenderse desde otros plugins.
