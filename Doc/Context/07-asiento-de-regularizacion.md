# Asiento de regularización

`Lib/Accounting/VatRegularizationToAccounting.php` crea el asiento que salda el IVA del periodo.

## Cómo es hoy

1. `generate()` se niega si hay facturas de compra o venta del periodo sin asiento y con total distinto de 0.
2. Crea el asiento con concepto «Regularización de IVA <periodo>» y fecha de fin del periodo.
3. Una línea por subcuenta de IVA: repercutido al debe, soportado al haber (cuota más recargo), con los mismos datos que `Modelo303::taxWhere()`.
4. Línea de resultado en la subcuenta de acreedores (a pagar) o deudores (a devolver). Si no cuadra, se borra el asiento.
5. El controlador bloquea la regularización tras crear el asiento.

Un asiento manual existente se puede **vincular** (`linkAccountingEntry()`): misma empresa, sin operación especial, fecha igual o posterior al fin del periodo y no vinculado a otra (`85bb925`). Al desvincular no se borra. La anual (`Y`) no admite asiento.

Al vincular se marca `asientomanual`; al crear o desvincular se desmarca. El core borra el asiento al borrar la regularización (`RegularizacionImpuesto::delete()`), así que `Extension/Model/RegularizacionImpuesto.php` olvida el `idasiento` en `deleteBefore()` cuando `asientomanual` está marcado: el asiento del usuario se queda en la contabilidad. Los asientos vinculados antes de existir la columna no llevan la marca y se siguen borrando.

## Por qué

Los asientos de regularización se excluyen del cálculo de los periodos siguientes, incluido el vinculado a mano (por eso `taxWhere()` lee `idasiento`). La apertura y el cierre también se excluyen (`ad65695`). Las facturas a cero no generan asiento (`abe068e`).

## Al cambiarlo

`getSubtotals()` repite el filtro de `Modelo303::taxWhere()`: si cambias uno, cambia el otro. Prueba con `VatRegularizationToAccountingTest` y `LinkAccountingEntryTest`.
