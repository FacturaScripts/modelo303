El plugin Modelo303 expone el cálculo de los modelos 303 (autoliquidación trimestral o mensual del IVA) y 390 (resumen anual) en dos endpoints de solo lectura de la API REST: `GET /api/3/modelo303` y `GET /api/3/modelo390`. Calculan con las mismas clases que la pantalla de la regularización y que los ficheros de la AEAT, así que las cifras coinciden con las que se ven en **menú Informes** → **Fiscalidad España** → **Modelos 303 y 390**. No crean asientos, no guardan regularizaciones y no generan ficheros.

Cada endpoint busca la regularización de IVA guardada de la empresa, el ejercicio y el periodo pedidos. Si existe, calcula sobre ella (con sus casillas de introducción manual y sus datos de presentación). Si no existe, calcula sobre una regularización en memoria con las fechas del periodo, que nunca se guarda, y lo avisa en `avisos`. Cómo se calcula cada casilla está en [Cálculos del plugin Modelo 303](../User/calculos-del-plugin-modelo-303.md).

## 1. Autenticación y permisos

La API tiene que estar activada y hace falta una clave API, que se envía en la cabecera `Token` (también vale `X-Auth-Token`). Una clave con **acceso completo** puede llamar a los dos endpoints. Una clave sin acceso completo necesita el permiso de lectura (`GET`) del recurso `modelo303` para el primero y del recurso `modelo390` para el segundo, que son recursos distintos. El plugin los añade al listado de recursos de la API (`ApiRoot::addCustomResource()` en `Init.php`).

```bash
curl -H "Token: TU_CLAVE_API" \
  "https://tu-servidor/api/3/modelo303?codejercicio=2025&periodo=T4"

curl -H "Token: TU_CLAVE_API" \
  "https://tu-servidor/api/3/modelo390?codejercicio=2025&detalle=1"
```

## 2. Parámetros

Todos van en la query string y son los mismos en los dos endpoints. Solo cambia el significado de `periodo`.

| Parámetro | Descripción |
|---|---|
| `periodo` | En `/modelo303` es **obligatorio**: `T1` a `T4` (también `1T` a `4T`) o `01` a `12` (también `1` a `12`), en mayúsculas o minúsculas. En `/modelo390` es opcional y solo admite `ANUAL` (también `ANNUAL`, `Y`, `A` y `0A`), que es el valor por defecto. |
| `codejercicio` | Código del ejercicio. Si se indica, manda sobre `idempresa` y `anio` y la empresa es la del ejercicio. |
| `idempresa` | Empresa. Por defecto, la predeterminada del programa. Debe ser un número entero. |
| `anio` | Año de cuatro cifras. Por defecto, el actual. Se usa el ejercicio de la empresa cuya fecha de inicio cae en ese año. |
| `detalle` | `1`, `true`, `yes`, `si`, `sí` u `on` para incluir el resumen de partidas de IVA y las facturas sin asiento. Cualquier otro valor se trata como no. Por defecto, no. |

Pedir `/modelo390` con `periodo=T1` devuelve `invalid-period`, y pedir `/modelo303` con `periodo=ANUAL` también: el resumen anual y los periodos tienen cada uno su endpoint.

## 3. Estructura de la respuesta

Los dos endpoints devuelven un objeto JSON con la misma forma:

- `modelo`: `"303"` o `"390"`.
- `plugin_version`: versión del plugin.
- `empresa`: `idempresa`, `nombre` y `cifnif`.
- `ejercicio`: `codejercicio`, `nombre`, `fechainicio`, `fechafin` (formato `Y-m-d`) y `estado`.
- `periodo`: el periodo normalizado (`T1`, `02`, `ANUAL`...). Justo después van `fechainicio` y `fechafin`, las fechas de ese periodo.
- `parametros`: `detalle`, con el valor usado.
- `regularizacion`: datos de la regularización usada (ver abajo).
- `resumen`: importes con nombre, que cambian según el modelo.
- `casillas`: casillas del modelo como importes con dos decimales.
- `resultado`: la casilla `71` en el 303 y la `86` en el 390.
- `periodos`: solo en el 390, con el resultado de cada regularización trimestral o mensual del año.
- `detalle`: solo con `detalle=1`.
- `avisos`: lista de textos sobre los datos del modelo.

### La regularización

`regularizacion` siempre trae `existe`, `idregiva`, `idasiento`, `periodo` (el interno: `T1`, `02` o `Y`), `fechainicio` y `fechafin`. `idregiva` es `null` si la regularización no está guardada, e `idasiento` es `null` si no tiene asiento.

Si la regularización está guardada, se añaden `fechaasiento`, `bloquear` y los datos de presentación del fichero:

- En el 303: `tipodeclaracion`, `iban`, `sinactividad`, `rectificativa`, `justificanteant` y `casillas_manuales`, con las casillas `65`, `68`, `70`, `76`, `77`, `78`, `108`, `109`, `110`, `111` y `112`.
- En el 390: `actividad`, `clave_actividad`, `epigrafe_iae`, `operaciones_347`, `sustitutiva`, `justificanteant` y `casillas_manuales`, con las casillas `96`, `106`, `107` y `112`.

El campo `iban` se devuelve **enmascarado**: el código de país, los dígitos de control y los 4 últimos caracteres, con el resto en asteriscos (`ES91****************1332`). El IBAN completo solo va en el fichero de la AEAT y en la pantalla.

### Modelo 303

Las casillas se devuelven ordenadas por número y con dos dígitos como mínimo (`"01"`, `"27"`, `"110"`). Solo salen las que no valen cero, salvo la `27`, la `45`, la `46`, la `64`, la `69` y la `71`, que salen siempre. Las casillas de porcentaje fijo (como la `02` o la `169`) solo salen si su base no vale cero.

`resumen` contiene `base_imponible_devengado`, `cuota_iva_devengado`, `base_recargo_equivalencia`, `cuota_recargo_equivalencia`, `iva_devengado` (casilla 27), `base_imponible_deducible`, `iva_deducible` (casilla 45), `resultado_regimen_general` (46), `suma_resultados` (64), `atribuible_estado` (66), `cuotas_compensar_periodos_anteriores` (110), `cuotas_compensadas_periodo` (78), `a_compensar_periodos_posteriores` (87), `resultado_autoliquidacion` (69) y `resultado` (71).

El ejemplo siguiente es el de un cuarto trimestre con importes ficticios, sin `detalle`:

```json
{
  "modelo": "303",
  "plugin_version": 3.51,
  "empresa": { "idempresa": 1, "nombre": "Empresa Ejemplo SL", "cifnif": "B00000001" },
  "ejercicio": {
    "codejercicio": "2025",
    "nombre": "Ejercicio 2025",
    "fechainicio": "2025-01-01",
    "fechafin": "2025-12-31",
    "estado": "ABIERTO"
  },
  "periodo": "T4",
  "fechainicio": "2025-10-01",
  "fechafin": "2025-12-31",
  "parametros": { "detalle": false },
  "regularizacion": {
    "existe": true,
    "idregiva": 11,
    "idasiento": null,
    "periodo": "T4",
    "fechainicio": "2025-10-01",
    "fechafin": "2025-12-31",
    "fechaasiento": null,
    "bloquear": false,
    "tipodeclaracion": "I",
    "iban": null,
    "sinactividad": false,
    "rectificativa": false,
    "justificanteant": null,
    "casillas_manuales": {
      "65": 100, "68": 0, "70": 0, "76": 0, "77": 0, "78": 662.4,
      "108": 0, "109": 0, "110": 662.4, "111": 0, "112": 0
    }
  },
  "resumen": {
    "base_imponible_devengado": 17700,
    "cuota_iva_devengado": 3472,
    "base_recargo_equivalencia": 1000,
    "cuota_recargo_equivalencia": 31.2,
    "iva_devengado": 3503.2,
    "base_imponible_deducible": 11400,
    "iva_deducible": 2394,
    "resultado_regimen_general": 1109.2,
    "suma_resultados": 1109.2,
    "atribuible_estado": 1109.2,
    "cuotas_compensar_periodos_anteriores": 662.4,
    "cuotas_compensadas_periodo": 662.4,
    "a_compensar_periodos_posteriores": 0,
    "resultado_autoliquidacion": 446.8,
    "resultado": 446.8
  },
  "casillas": {
    "01": 600, "02": 4, "03": 24, "04": 1300, "05": 10, "06": 130,
    "07": 15500, "08": 21, "09": 3255, "10": 300, "11": 63,
    "19": 300, "20": 1.4, "21": 4.2, "22": 500, "23": 5.2, "24": 26,
    "27": 3503.2, "28": 11100, "29": 2331, "36": 300, "37": 63,
    "45": 2394, "46": 1109.2, "59": 1500, "60": 25000,
    "64": 1109.2, "65": 100, "66": 1109.2, "69": 446.8, "71": 446.8,
    "78": 662.4, "110": 662.4, "168": 200, "169": 0.5, "170": 1
  },
  "resultado": 446.8,
  "avisos": []
}
```

### Modelo 390

Las casillas del 390 son las del diseño oficial del resumen anual, que no son las del 303. Solo salen las que no valen cero, salvo la `33`, la `34`, la `47`, la `64`, la `65`, la `84`, la `86`, la `95`, la `97`, la `98`, la `662` y la `108`, que salen siempre. Se ordenan por número.

`resumen` contiene `base_imponible_devengado` (33), `cuota_iva_devengado` (34), `cuota_recargo_equivalencia`, `iva_devengado` (47), `base_imponible_deducible`, `iva_deducible` (64), `resultado_regimen_general` (65), `suma_resultados` (84), `compensacion_ejercicio_anterior` (85), `resultado_liquidacion` (86), `total_a_ingresar_periodos` (95), `devoluciones_mensuales` (96), `a_compensar_ultimo_periodo` (97), `a_devolver_ultimo_periodo` (98), `pendiente_compensar_generado_ejercicio` (662) y `volumen_operaciones` (108).

`resultado` es la casilla `86`, que es informativa: lo que se ingresa o se devuelve está en los modelos 303 del año.

`periodos` lista las regularizaciones trimestrales o mensuales guardadas del ejercicio (la anual no cuenta), ordenadas por fecha. Cada elemento trae `idregiva`, `periodo`, `fechainicio`, `fechafin`, `tipodeclaracion`, `resultado` (la casilla 71 de ese 303) y `pendiente_compensar` (su casilla 87).

Ejemplo abreviado con importes ficticios:

```json
{
  "modelo": "390",
  "plugin_version": 3.51,
  "empresa": { "idempresa": 1, "nombre": "Empresa Ejemplo SL", "cifnif": "B00000001" },
  "periodo": "ANUAL",
  "fechainicio": "2025-01-01",
  "fechafin": "2025-12-31",
  "parametros": { "detalle": false },
  "regularizacion": {
    "existe": true,
    "idregiva": 12,
    "idasiento": null,
    "periodo": "Y",
    "fechainicio": "2025-01-01",
    "fechafin": "2025-12-31",
    "fechaasiento": null,
    "bloquear": false,
    "actividad": "Comercio",
    "clave_actividad": "A03",
    "epigrafe_iae": "6471",
    "operaciones_347": true,
    "sustitutiva": false,
    "justificanteant": null,
    "casillas_manuales": { "96": 0, "106": 0, "107": 0, "112": 0 }
  },
  "resumen": {
    "base_imponible_devengado": 68700,
    "cuota_iva_devengado": 13392,
    "cuota_recargo_equivalencia": 124.8,
    "iva_devengado": 13516.8,
    "base_imponible_deducible": 62500,
    "iva_deducible": 13070,
    "resultado_regimen_general": 446.8,
    "suma_resultados": 446.8,
    "compensacion_ejercicio_anterior": 0,
    "resultado_liquidacion": 446.8,
    "total_a_ingresar_periodos": 446.8,
    "devoluciones_mensuales": 0,
    "a_compensar_ultimo_periodo": 0,
    "a_devolver_ultimo_periodo": 0,
    "pendiente_compensar_generado_ejercicio": 0,
    "volumen_operaciones": 165600
  },
  "casillas": {
    "33": 68700, "34": 13392, "47": 13516.8, "64": 13070, "65": 446.8,
    "84": 446.8, "86": 446.8, "95": 446.8, "97": 0, "98": 0,
    "662": 0, "108": 165600
  },
  "resultado": 446.8,
  "periodos": [
    {
      "idregiva": 8, "periodo": "T1",
      "fechainicio": "2025-01-01", "fechafin": "2025-03-31",
      "tipodeclaracion": "C", "resultado": -2459.8, "pendiente_compensar": 0
    },
    {
      "idregiva": 11, "periodo": "T4",
      "fechainicio": "2025-10-01", "fechafin": "2025-12-31",
      "tipodeclaracion": "I", "resultado": 446.8, "pendiente_compensar": 0
    }
  ],
  "avisos": []
}
```

El ejemplo omite el bloque `ejercicio` y reduce `casillas` y `periodos` para abreviar: la respuesta real trae todos sus campos.

### Detalle

Con `detalle=1` se añade `detalle` con dos listas, iguales en los dos endpoints:

- `resumen_impuestos`: el resumen de partidas de IVA que alimenta las casillas, una fila por subcuenta, tipo de IVA, recargo y operación. Cada elemento trae `codsubcuenta`, `descripcion`, `codcuentaesp`, `operacion`, `tipodoc` (`venta` o `compra`), `iva`, `recargo`, `baseimponible` y `cuota`.
- `facturas_sin_asiento`: facturas de cliente y de proveedor del periodo sin asiento contable y con total distinto de cero, que no se tienen en cuenta en el cálculo. Cada elemento trae `tipo` (`venta` o `compra`), `codigo`, `fecha` y `total`.

### Avisos

`avisos` es una lista de textos que se pueden mostrar tal cual, traducidos al idioma del servidor como el resto de mensajes de FacturaScripts. Se informa de estos casos (los textos son los de español):

- No hay regularización guardada: «Cálculo provisional: no hay regularización guardada, las casillas manuales valen 0.»
- Importes que no se han podido asignar a ninguna casilla, regularizaciones del año que faltan o resultados que no cuadran (los genera el cálculo del modelo).
- Hay facturas de cliente o de proveedor del periodo sin asiento contable, con su cantidad: «Hay 1 factura de cliente del periodo sin asiento contable: no se tiene en cuenta.» o «Hay 3 facturas de proveedor...», en singular o en plural según la cantidad.

## 4. Errores

Los errores de los endpoints tienen la forma `{"status": "error", "message": "..."}`, y algunos añaden datos extra.

| Código | `message` | Cuándo |
|---|---|---|
| 400 | `missing-period` | Falta `periodo` en `/modelo303` (`allowed` lista los valores válidos). |
| 400 | `invalid-period` | Periodo no válido (`allowed` lista los válidos). En `/modelo303`, `ANUAL` tampoco lo es. |
| 400 | `invalid-year` | `anio` no tiene cuatro cifras (`anio` devuelve el valor recibido). |
| 404 | `exercise-not-found` | No existe el ejercicio (`codejercicio`) o la empresa no tiene ejercicio en ese año (`idempresa` y `anio`). |
| 404 | `company-not-found` | No existe la empresa (`idempresa`) o no es un número entero. |
| 405 | `method-not-allowed` | Cualquier método distinto de `GET`. |

Los errores de acceso los genera el programa con su formato habitual: 401 si la clave no es válida, 403 si la clave no tiene permiso de lectura sobre el recurso, 409 si la API está desactivada y 429 si la IP está bloqueada o se supera el límite de peticiones por minuto.

## 5. Usarlo desde otro plugin

Desde PHP, otro plugin puede llamar a las clases de la API sin pasar por HTTP. `build()` recibe los mismos parámetros de la query string y devuelve el código HTTP y el cuerpo ya construido:

```php
use FacturaScripts\Dinamic\Lib\Modelo303Api;
use FacturaScripts\Dinamic\Lib\Modelo390Api;

$response = Modelo303Api::build([
    'codejercicio' => '2025',
    'periodo' => 'T4',
    'detalle' => '1',
]);

if ($response['code'] === 200) {
    $resultado = $response['body']['casillas']['71'];
}

$anual = Modelo390Api::build(['codejercicio' => '2025']);
```

Las clases no escriben datos: nunca crean ni modifican regularizaciones, aunque no exista la del periodo.

## 6. Estructura del código

| Archivo | Qué hace |
|---|---|
| `Init.php` | Registra las rutas con `Kernel::addRoute('/api/3/modelo303', 'ApiModelo303', -1)` y `Kernel::addRoute('/api/3/modelo390', 'ApiModelo390', -1)`, y los recursos con `ApiRoot::addCustomResource()`. |
| `Controller/ApiModelo303.php`, `Controller/ApiModelo390.php` | Extienden `ApiController`: validan que el método sea `GET`, leen los parámetros `codejercicio`, `idempresa`, `anio`, `periodo` y `detalle` de la query string y responden con el resultado de `build()`. |
| `Lib/Modelo303Api.php`, `Lib/Modelo390Api.php` | Validan el periodo, resuelven el ejercicio, calculan el modelo y construyen la respuesta con `build(array $params)`. |
| `Lib/Modelo303ApiTrait.php` | Código común de las dos: resolver empresa y ejercicio, normalizar el periodo, cargar la regularización guardada o crear una en memoria, dar formato a fechas e importes y componer los avisos. |
| `Lib/Modelo303.php`, `Lib/Modelo390.php` | El cálculo de las casillas, que comparten la pantalla, el fichero y la API. |

La estructura completa del plugin está en [Estructura del plugin Modelo 303](estructura-del-plugin-modelo-303.md).

## Ver también

- [Estructura del plugin Modelo 303](estructura-del-plugin-modelo-303.md)
- [Cálculos del plugin Modelo 303](../User/calculos-del-plugin-modelo-303.md)
