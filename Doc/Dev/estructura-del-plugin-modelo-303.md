El plugin Modelo303 calcula las casillas del modelo 303 (autoliquidación trimestral o mensual del IVA) y del modelo 390 (resumen anual) **a partir de las partidas de los asientos contables**, y las comparte entre la pantalla, los ficheros de la AEAT, la exportación y la API. Esta página explica cómo se reparte el código, qué añade el plugin a la tabla de regularizaciones y cómo usarlo o extenderlo desde otro plugin.

La pantalla está en **menú Informes** → **Modelos fiscales** → **Modelos 303 y 390**: el listado muestra las regularizaciones de IVA y cada una se abre en su ficha. Las trimestrales y mensuales muestran el modelo 303; la anual (periodo `Y`) muestra el modelo 390. El endpoint de solo lectura está explicado en [API REST de los modelos 303 y 390](api-modelo-303-y-390.md).

## 1. Piezas del plugin

| Archivo | Qué hace |
|---|---|
| `Controller/ListRegularizacionImpuesto.php` | Listado de regularizaciones, con una pestaña para el modelo 303 y otra para el 390. |
| `Controller/EditRegularizacionImpuesto.php` | Ficha de la regularización: pestañas de resumen, partidas, asiento y presentación, y las acciones del plugin (ver más abajo). |
| `Controller/ApiModelo303.php`, `Controller/ApiModelo390.php` | Endpoints de la API de solo lectura. |
| `Lib/Modelo303.php` | Cálculo del modelo 303: reparto de las partidas de IVA en casillas y cadena de resultado. |
| `Lib/Modelo390.php` | Cálculo del modelo 390: casillas propias del resumen anual, resultado de las liquidaciones del año y volumen de operaciones. |
| `Lib/Modelo303File.php`, `Lib/Modelo390File.php` | Generan los ficheros `.303` y `.390` de la AEAT a partir de las casillas. |
| `Lib/Modelo303Api.php`, `Lib/Modelo390Api.php` | Construyen la respuesta de la API. |
| `Lib/Modelo303ApiTrait.php`, `Lib/Modelo303FileTrait.php` | Código común de la API y de los ficheros (ver más abajo). |
| `Lib/Accounting/VatRegularizationToAccounting.php` | Genera el asiento de regularización de IVA de un periodo. |
| `Model/Join/PartidaImpuesto.php`, `Model/Join/PartidaImpuestoResumen.php` | Modelos auxiliares (`JoinModel`) que leen las partidas con IVA: el primero para las pestañas de IVA soportado y devengado y el segundo para el resumen que alimenta las casillas. |
| `Extension/Table/regularizacionimpuestos.xml` | Columnas que el plugin añade a la tabla del core `regularizacionimpuestos`. |
| `Extension/XMLView/ListPartida.xml` | Ventana modal para vincular un asiento existente a la regularización. |
| `XMLView/`, `View/` | Pestañas de la ficha. `View/Modelo303.html.twig` y `View/Modelo390.html.twig` pintan los resúmenes de casillas. |
| `Init.php` | Registra las rutas y los recursos de la API. |

Las clases de `Lib` y `Model` se usan siempre desde el espacio de nombres `Dinamic`, como hace el propio controlador (`FacturaScripts\Dinamic\Lib\Modelo303`), para que otros plugins puedan sustituirlas.

Los dos traits no se despliegan en `Dinamic`: solo se despliegan las clases. Por eso son traits y no una clase base común, y por eso no se pueden pedir con `FacturaScripts\Dinamic\Lib\Modelo303ApiTrait`.

## 2. Cómo se calcula

### El modelo 303

`FacturaScripts\Plugins\Modelo303\Lib\Modelo303` tiene un método estático por cada paso del cálculo, y la pantalla, el fichero y la API usan los mismos para que todos den las mismas cifras:

- `Modelo303::taxWhere(RegularizacionImpuesto $reg, int $group)` construye el filtro de partidas: asientos de la empresa y el ejercicio de la regularización, dentro de sus fechas, de series con IVA, sin asientos de apertura ni de cierre y sin los asientos de ninguna regularización de IVA.
- `Modelo303::taxSummary(RegularizacionImpuesto $reg)` devuelve el resumen de partidas de IVA (`PartidaImpuestoResumen[]`) con ese filtro y sin paginar, agrupado por subcuenta, tipo de IVA, recargo y operación.
- `Modelo303::fromRegularizacion(RegularizacionImpuesto $reg, ?array $resumen = null)` devuelve el modelo ya calculado. Reparte el resumen en casillas, suma las bases exentas de las facturas de venta (casillas 59, 60 y 122), aplica las casillas de introducción manual de la regularización y calcula la cadena de resultado (64 a 87). Si se le pasa el resumen ya cargado, no repite la consulta.
- `$modelo->getSquares()` devuelve todas las casillas (clave: número de casilla de la AEAT) y `$modelo->casilla('71')`, una sola. `getAvisos()` devuelve los importes que no se han podido asignar a ninguna casilla.
- `Modelo303::invoicesWithoutAccounting(RegularizacionImpuesto $reg)` devuelve las facturas de cliente y de proveedor del periodo sin asiento, que no entran en el cálculo.
- `Modelo303::previousRegularization()`, `pendingCarryover()` y `declarationType()` resuelven el arrastre de cuotas a compensar entre periodos y el tipo de declaración.

La regularización no tiene por qué estar guardada: basta con que tenga `idempresa`, `codejercicio`, `fechainicio` y `fechafin`. El cálculo nunca la guarda ni la modifica.

Las casillas de introducción manual (`Modelo303::MANUAL_SQUARES`: 65, 68, 70, 76, 77, 78, 108, 109, 110, 111 y 112) se guardan en la propia regularización como `c65`, `c68`... y entran en la cadena de resultado.

### El modelo 390

`FacturaScripts\Plugins\Modelo303\Lib\Modelo390` calcula el resumen anual a partir de la regularización del periodo `Y`. `Modelo390::fromRegularizacion(RegularizacionImpuesto $reg, ?array $resumen = null)` reparte el mismo resumen de partidas de IVA (`Modelo303::taxSummary()`, sobre todo el año) en las casillas del 390 y añade:

- El resultado de las liquidaciones del año, a partir de las regularizaciones trimestrales o mensuales guardadas del mismo ejercicio (`Modelo390::periodicResults()` y `getPeriodos()`).
- El volumen de operaciones y las operaciones específicas, a partir de las partidas de IVA y de las facturas de venta por tipo de operación.
- Las casillas de introducción manual (`Modelo390::MANUAL_SQUARES`: 96, 106, 107 y 112), guardadas en las columnas `c390_96`, `c390_106`, `c390_107` y `c390_112`.

Quedan a cero la prorrata, los sectores diferenciados, el régimen simplificado, el criterio de caja y el resto de regímenes especiales, que el plugin no contabiliza por separado.

### La API y los ficheros

`Modelo303Api::build()` y `Modelo390Api::build()` llaman a `fromRegularizacion()` y dan formato al resultado. Los ficheros usan las casillas de `getSquares()` y los datos de identificación de la empresa y de la regularización:

- `Modelo303File::generate(RegularizacionImpuesto $reg, array $squares, Empresa $company)` devuelve el contenido del fichero `.303`, en ISO-8859-1, con el diseño de registro de 2026 para los ejercicios de 2026 en adelante y el de 2025 para los anteriores.
- `Modelo390File::generate(RegularizacionImpuesto $reg, array $squares, Empresa $company)` hace lo mismo con el fichero `.390`. Lanza una excepción si la regularización no es anual o si el ejercicio es anterior a 2024 (`Modelo390File::FIRST_YEAR`), el primero con diseño de registro.

El controlador nombra los ficheros `<NIF>_<año>_<periodo>.303` y `<NIF>_<año>_0A.390`. `Modelo303FileTrait` reúne los formateadores comunes de campos de posiciones fijas: alfanuméricos en mayúsculas y sin acentos, importes con y sin signo y porcentajes.

## 3. El controlador de la ficha

`FacturaScripts\Plugins\Modelo303\Controller\EditRegularizacionImpuesto` extiende `EditController`, cuelga del menú `reports` y el submenú `tax-models` y trabaja sobre el modelo `RegularizacionImpuesto` del core. En `execPreviousAction()` atiende estas acciones:

| Acción | Qué hace |
|---|---|
| `create-accounting-entry` | Crea el asiento de regularización con `VatRegularizationToAccounting::generate()` y bloquea la regularización. No se puede crear en la anual. |
| `download-303`, `download-390` | Guardan los cambios de la pestaña y descargan el fichero. Cada una avisa si la regularización no es del tipo que le corresponde. |
| `fill-previous-carryover` | Rellena la casilla 110 con lo que dejó pendiente la regularización anterior de la misma empresa. |
| `link-accounting-entry`, `unlink-accounting-entry` | Vinculan o desvinculan un asiento que ya existe, sin crear ninguno. |

El controlador carga el cálculo en `loadData()`: en la pestaña `Modelo303` crea `$fsc->modelo303` con `Modelo303::fromRegularizacion()` y, en la anual, `$fsc->modelo390` con `Modelo390::fromRegularizacion()`. Las plantillas `View/Modelo303.html.twig` y `View/Modelo390.html.twig` leen esas propiedades.

## 4. Las columnas que añade el plugin

`Extension/Table/regularizacionimpuestos.xml` fusiona sus columnas con la tabla `regularizacionimpuestos` del core, así que están en el modelo `RegularizacionImpuesto` como propiedades:

| Columnas | Contenido |
|---|---|
| `tipodeclaracion`, `iban`, `sinactividad`, `rectificativa`, `justificanteant` | Datos de presentación del fichero. |
| `c65`, `c68`, `c70`, `c76`, `c77`, `c78`, `c108`, `c109`, `c110`, `c111`, `c112` | Casillas de introducción manual del 303 (la `c65` vale 100 por defecto). |
| `actividad390`, `claveactividad390`, `epigrafeiae390`, `operaciones347` | Datos de identificación del fichero del 390. |
| `c390_96`, `c390_106`, `c390_107`, `c390_112` | Casillas de introducción manual del 390. |
| `asientomanual` | El asiento de la regularización es uno ya existente que se vinculó a mano. `Extension/Model/RegularizacionImpuesto.php` lo consulta en `deleteBefore()` para que, al eliminar la regularización, el core no elimine ese asiento. |

Se leen y se escriben como cualquier otra propiedad del modelo:

```php
use FacturaScripts\Dinamic\Model\RegularizacionImpuesto;

$reg = new RegularizacionImpuesto();
if ($reg->load($idregiva)) {
    $reg->c110 = 250.0;
    $reg->save();
}
```

## 5. Usarlo desde otro plugin

El cálculo se puede reutilizar sin pasar por la pantalla ni por HTTP. El ejemplo calcula el 303 de una regularización guardada y lista los periodos del 390 de un ejercicio:

```php
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Lib\Modelo303;
use FacturaScripts\Dinamic\Lib\Modelo390;
use FacturaScripts\Dinamic\Model\RegularizacionImpuesto;

$reg = new RegularizacionImpuesto();
$where = [
    Where::eq('codejercicio', '2025'),
    Where::eq('periodo', 'T4'),
];
if ($reg->loadWhere($where)) {
    $modelo = Modelo303::fromRegularizacion($reg);
    $resultado = $modelo->casilla('71');
    $avisos = $modelo->getAvisos();
}

$anual = new RegularizacionImpuesto();
$where = [
    Where::eq('codejercicio', '2025'),
    Where::eq('periodo', 'Y'),
];
if ($anual->loadWhere($where)) {
    $modelo390 = Modelo390::fromRegularizacion($anual);
    $periodos = $modelo390->getPeriodos();
}
```

Para obtener la misma respuesta que da la API, sin HTTP, usa `Modelo303Api::build()` o `Modelo390Api::build()` (ver [API REST de los modelos 303 y 390](api-modelo-303-y-390.md)).

Otros plugins también pueden pedir los saldos de IVA de un periodo con `Modelo303::treasury(string $codejercicio, string $period)`, que devuelve un array con las claves `iva-repercutido`, `iva-soportado` e `iva-devolver`, o un array vacío si el ejercicio no existe. Solo suma los asientos de ese ejercicio, y por tanto de su empresa. `$period` admite `T1` a `T4` y, para el año completo, cualquiera de los nombres de `Modelo303::ANNUAL_PERIODS` (`ANNUAL`, `ANUAL`, `Y`, `A` o `0A`).

## 6. Extenderlo desde otro plugin

Como las clases se usan desde `Dinamic`, otro plugin puede sustituir una de ellas con una clase del mismo nombre en su carpeta `Lib` que extienda la del plugin (`FacturaScripts\Plugins\Modelo303\Lib\Modelo303`, por ejemplo) y se active después. Las clases con métodos estáticos y `static` en lugar de `self` (como `Modelo303::fromRegularizacion()`, que crea el modelo con `new static()`) devuelven entonces la clase sustituta.

Para añadir columnas propias a la regularización, otro plugin puede usar su propia `Extension/Table/regularizacionimpuestos.xml`, igual que este plugin.

Al cambiar o sustituir una clase, hay que mantener la coincidencia entre pantalla, fichero y API: las tres leen las casillas de `fromRegularizacion()`, y las pruebas del plugin (`Modelo303SquaresTest`, `Modelo390SquaresTest`, `Modelo303ApiTest`, `Modelo390ApiTest`) comprueban esa coincidencia.

## Ver también

- [API REST de los modelos 303 y 390](api-modelo-303-y-390.md)
- [Cálculos del plugin Modelo 303](../User/calculos-del-plugin-modelo-303.md)
