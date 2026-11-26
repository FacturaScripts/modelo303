# Ficheros de la AEAT (.303 y .390)

Generan los ficheros de texto de posiciones fijas (ISO-8859-1) que se suben a la Sede Electrónica.

## Cómo es hoy

- `Lib/Modelo303File.php`: diseño DR303. Diseño de 2026 (v1.01) para 2026 y siguientes y el de 2025 (v1.02) para los anteriores. Páginas 1, 3 y DID (domiciliación o devolución, solo con IBAN). Nombre de descarga `<NIF>_<año>_<periodo>.303`.
- `Lib/Modelo390File.php`: diseño DR390, dos diseños (2024-2025 y 2026+) en `LAYOUTS`. No genera las páginas del régimen simplificado ni de sectores diferenciados. Desde `FIRST_YEAR` (2024). Nombre `<NIF>_<año>_0A.390`.
- `Lib/Modelo303FileTrait.php`: formateadores comunes (alfanuméricos, importes con y sin signo, porcentajes).
- `EditRegularizacionImpuesto::downloadTxtAction()` y `downloadTxt390Action()` guardan antes los cambios de la pestaña para que el fichero coincida con lo que se ve.
- El .303 no se genera para la anual y el .390 solo para la anual.

## Por qué

- Se añadió el .303 en `8260438` desde el diseño oficial; en `f3fb68e` se cambió `Txt303Export` a `Modelo303File` y se añadió el diseño de 2025.
- Validaciones de la marca SEPA en domiciliación y devolución (`2fcb502`).

## Al cambiarlo

Cada año la AEAT publica un diseño: añade el nuevo y no toques los anteriores. Pruebas: `Modelo303FileTest` y `Modelo390FileTest` (longitudes por página y posición de casillas).
