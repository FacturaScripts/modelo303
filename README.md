# Modelo303
Modelo 303 y 390 de la Hacienda española para la declaración trimestral y anual de IVA. También conocida como regularización de IVA (o regularizacion).
- https://facturascripts.com/plugins/modelo303

Calcula automáticamente el IVA soportado y el repercutido a partir de la contabilidad, estima lo que debes pagar a Hacienda en concepto de regularización de IVA y genera los asientos contables correspondientes.

## Funcionalidades
- Genera la autoliquidación de IVA **modelo 303** (trimestral o mensual) y el resumen anual **modelo 390**.
- Calcula el importe de cada casilla oficial según el tipo de operación (inversión del sujeto pasivo, operaciones intracomunitarias, importaciones, ventas exentas, etc.) y avisa de los importes que no encajan en ninguna casilla en lugar de ignorarlos.
- Contabiliza la regularización de IVA generando el asiento correspondiente.
- Genera el **fichero oficial `.303`** listo para subir a la Sede Electrónica de la AEAT (diseño de registro DR303 de 2026 para ese ejercicio y siguientes, y el de 2025 para los anteriores), con una sección de datos de presentación (tipo de declaración, IBAN, justificante anterior, casillas de introducción manual...).
- Muestra en el resumen la cadena completa del resultado de la autoliquidación (casillas 64 a 71), con las cuotas a compensar de periodos anteriores.
- En el resumen anual calcula las **casillas oficiales del modelo 390**: IVA devengado y deducible por tipo, resultado de la liquidación anual, resultado de las liquidaciones del año (a partir de las regularizaciones trimestrales o mensuales guardadas) y volumen de operaciones.
- Genera el **fichero oficial del modelo 390** (diseño de registro DR390 de los ejercicios 2024 a 2026) con su sección de datos de presentación (actividad, clave, epígrafe IAE, declaración sustitutiva y casillas manuales). No calcula la prorrata, los sectores diferenciados, el régimen simplificado, el criterio de caja ni los demás regímenes especiales, y declara todo el IVA deducible como de bienes y servicios corrientes.
- Exporta el informe a **PDF y Excel**.

## Uso
1. Instala el plugin y ve al menú **Informes → Fiscalidad España → Modelos 303 y 390**.
2. Pulsa **Nuevo**, selecciona la empresa, el ejercicio y el periodo (trimestre para el 303, o la pestaña Modelo 390 para el resumen anual) y guarda.
3. Revisa el resumen y el desglose de compras y ventas, y en la pestaña **Asiento** pulsa **Crear Asiento Contable** para generar el asiento del pago a Hacienda.

> El informe extrae los datos de la **contabilidad**, no de la facturación: todas las facturas deben tener su asiento contable registrado para aparecer.

## Estructura y API
Cada modelo tiene la misma estructura: una clase de cálculo, una de respuesta de la API y una del fichero de la AEAT.

| Modelo | Cálculo | API | Fichero | Endpoint |
|---|---|---|---|---|
| 303 (trimestral o mensual) | `Lib/Modelo303` | `Lib/Modelo303Api` | `Lib/Modelo303File` (.303) | `GET /api/3/modelo303` |
| 390 (resumen anual) | `Lib/Modelo390` | `Lib/Modelo390Api` | `Lib/Modelo390File` (.390) | `GET /api/3/modelo390` |

Las dos clases de la API comparten `Lib/Modelo303ApiTrait` (empresa, ejercicio, regularización, detalle y avisos) y los dos ficheros, `Lib/Modelo303FileTrait` (formato de los campos). Son traits para que no se desplieguen en `Dinamic/Lib`.

Los dos endpoints son de solo lectura: calculan sobre la regularización guardada del periodo o, si no existe, sobre una en memoria que no se guarda.

- `GET /api/3/modelo303`: `periodo` T1-T4 o 01-12 (obligatorio), `codejercicio` o `idempresa` y `anio`, y `detalle` (resumen de partidas de IVA y facturas sin asiento). Devuelve la regularización, el resumen, las casillas y el resultado (casilla 71).
- `GET /api/3/modelo390`: `periodo` opcional (solo `ANUAL`), `codejercicio` o `idempresa` y `anio`, y `detalle`. Devuelve la regularización anual con sus datos de presentación, el resumen, las casillas del 390, las regularizaciones periódicas del año y el resultado de la liquidación anual (casilla 86).

Una clave API sin acceso completo necesita el permiso de lectura del recurso `modelo303` o `modelo390`.

## Documentación
- [Autoliquidación trimestral - Modelo 303](https://facturascripts.com/publicaciones/crear-informe-de-autoliquidacion-trimestral)
- [Autoliquidación anual - Modelo 390](https://facturascripts.com/publicaciones/crear-informe-de-autoliquidacion-anual)
- [Exportar Modelo 303 a Excel](https://facturascripts.com/publicaciones/exportar-informe-a-excel)

## Links
- [Curso de FacturaScripts](https://www.youtube.com/watch?v=rGopZA3ErzE&list=PLNxcJ5CWZ8V6nfeVu6vieKI_d8a_ObLfY)
- [Programa de contabilidad gratis para autónomos](https://facturascripts.com/software-contabilidad)
- [Programa para hacer facturas gratis](https://facturascripts.com/programa-para-hacer-facturas)
- [Programa para hacer presupuestos gratis](https://facturascripts.com/programa-de-presupuestos)
