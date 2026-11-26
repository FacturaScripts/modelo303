# Modelo303: contexto del equipo

Esta carpeta guarda **por qué el código es como es**: las decisiones que no se ven leyendo una sola clase, para que
todo el equipo y los agentes trabajen con el mismo contexto. Lee este índice y abre solo el tema o la decisión que
necesites.

Se puede dividir en tantos archivos y subcarpetas como haga falta; este índice dice qué hay en cada uno y se actualiza
en el mismo trabajo que cambia el código.

## Temas

- [`01-arquitectura.md`](01-arquitectura.md): piezas del plugin, cómo encajan y qué comparten pantalla, fichero y API.
- [`02-modelo-de-datos.md`](02-modelo-de-datos.md): columnas añadidas a `regularizacionimpuestos` y modelos de unión.
- [`03-calculo-modelo-303.md`](03-calculo-modelo-303.md): cómo se reparten los importes a las casillas del 303.
- [`04-modelo-390.md`](04-modelo-390.md): cálculo del resumen anual y lo que no cubre.
- [`05-ficheros-aeat.md`](05-ficheros-aeat.md): ficheros .303 y .390 y sus diseños por año.
- [`06-api.md`](06-api.md): endpoints de solo lectura `/api/3/modelo303` y `/api/3/modelo390`.
- [`07-asiento-de-regularizacion.md`](07-asiento-de-regularizacion.md): generar y vincular el asiento.
- [`08-puntos-delicados.md`](08-puntos-delicados.md): lo que se rompe con facilidad.
- [`09-evolucion.md`](09-evolucion.md): etapas del código según el historial.

## Decisiones

- [`0001-calcular-desde-la-contabilidad.md`](Decisiones/0001-calcular-desde-la-contabilidad.md): por qué los modelos leen asientos y no facturas.
- [`0002-modelo-390-propio.md`](Decisiones/0002-modelo-390-propio.md): por qué el 390 tiene casillas y fichero propios.
- [`0003-traits-en-lib.md`](Decisiones/0003-traits-en-lib.md): por qué el código común son traits.
- [`0004-no-borrar-asiento-vinculado.md`](Decisiones/0004-no-borrar-asiento-vinculado.md): por qué el asiento vinculado a mano no se borra con la regularización.
