# Evolución

Según el historial (159 commits, de 2020 a 2026-11-26).

1. **2020-2023, regularización básica.** Versión inicial (`48e8c2e`), adaptación a JoinModel de 2020.88 (`8aeaf25` y otros), asiento de regularización con test (`e2784f0`, `446525e`), enlaces a subcuentas y facturas.
2. **2024, 303 por meses y 390 separado.** Periodos mensuales (`88ee7d5`) y listado del 390 en su pestaña (`f0fbd58`).
3. **2025, comprobaciones.** Verificación de facturas sin asiento (`3a14c11`), fecha de devengo (`bc3171a`), tests de casillas (`ba3446f` y siguientes), actualización a 2025 (`6bd6435`).
4. **Octubre 2025 a marzo 2026.** Pestañas de facturas (`ceb8507`), resumen de IVA en el PDF (`f5381d3`), `treasury()` para el plugin Informes (`9421c52`).
5. **Junio 2026, casillas y fichero.** Reparto por tipo de operación (`365b1e1`), consultas unificadas (`fa34240`), fichero .303 (`8260438`).
6. **Julio-septiembre 2026.** Versiones 3.x, refactor a core 2026.5 (`a78764f`), vincular y desvincular asientos (`89ff584`, `85bb925`; el método `linkExisting` inicial acabó como `linkAccountingEntry` en el controlador).
7. **Noviembre 2026.** DUA por tipo de IVA (`a7c059e`) y modelo 390 propio con API y ficheros (`f3fb68e`, versión 3.51).

Partes abandonadas: `Txt303Export` (renombrado a `Modelo303File`) y el primer intento de refactor con `JoinModel` y sin `DataBaseWhere`, revertido el mismo día (`651dfc4`).
