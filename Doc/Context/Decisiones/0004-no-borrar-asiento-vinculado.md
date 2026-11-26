# 0004. No borrar el asiento vinculado a mano al borrar la regularización

## Contexto

`RegularizacionImpuesto::delete()` del core borra el asiento de la regularización. Con el asiento que crea el
plugin (`VatRegularizationToAccounting`) es lo correcto: sin la regularización ya no tiene sentido. Pero desde
`85bb925` se puede vincular un asiento que el usuario ya había hecho a mano, y borrar la regularización también lo
borraba, aunque al desvincularlo se conserva.

Los dos asientos no se distinguen por sus datos: ninguno tiene operación especial y el concepto depende del idioma.

## Decisión

- Columna `asientomanual` en `regularizacionimpuestos` (Extension/Table): se marca al vincular y se desmarca al
  crear el asiento o al desvincularlo.
- Extensión `Extension/Model/RegularizacionImpuesto.php`, registrada en `Init::init()`: en `deleteBefore()` pone
  `idasiento` a `null` en memoria si `asientomanual` está marcado, de modo que el core borra la regularización y no
  encuentra asiento que borrar.

## Consecuencias

- El asiento del usuario se queda en la contabilidad; el del plugin se sigue borrando como antes.
- Los asientos vinculados antes de la columna no llevan la marca y se siguen borrando: hay que desvincularlos y
  volver a vincularlos. No se migran porque no hay forma fiable de distinguirlos.
- Pruebas en `LinkAccountingEntryTest` (`testDeleteKeepsManuallyLinkedEntry` y `testDeleteRemovesEntryNotLinkedManually`).
