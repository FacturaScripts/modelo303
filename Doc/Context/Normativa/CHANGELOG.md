# CHANGELOG – Documentación oficial de los modelos 303 y 390

## 2026-11-26 (primera ejecución, carpeta vacía)

### Qué se ha descargado
- **Páginas (119)** en `paginas/`, con la URL de origen en la primera línea: fichas del procedimiento G414 (303) y G412
  (390), ayuda técnica del 303 (presentación electrónica, por fichero, papel, rectificativas, incidencias) y del 390
  (formulario, fichero, simulador), instrucciones del 303 de 2024, 2025 y 2026, tutorial, autoliquidación rectificativa,
  ejercicios anteriores del 303 y del 390, plazos del 390, y el servicio **Pre303** (formas y plazo de presentación,
  libros registro, notas aclaratorias de 2020 a 2026).
- **Diseños de registro** (`actual/`): 303 de 2012 a 2026 (`DR303e26v101.xlsx` = 2026 y siguientes, v 1.01; `DR303e25.xlsx` =
  2025, que el plugin cita como v 1.02) y 390 de 2015 a 2026 (`dr390e2026.xlsx`, `dr390e2025.xlsx`, `dr390e2024.xlsx`, `dr390e2023v100.xlsx`...). Los
  de 2025, 2024 y 2022 ya no figuran en la lista de la Sede, pero siguen publicados en su carpeta (`archivos_25`,
  `archivos_24`, `archivos_22`), por lo que están fijados en `DIRECT`.
- **Instrucciones en PDF**: `Inst_mod_303_2025.pdf`, `Instrucciones_modelo_390-2025.pdf` e `instr390.pdf` (390 de 2024). Las
  del 303 de 2026 son páginas HTML (`paginas/`).
- **Otros documentos**: `Manual_IVA_2026.pdf`, notas aclaratorias de Pre303 (2021 a 2025), plantillas y formato electrónico de
  los libros registro del IVA, `CALCULO_CASILLAS_IVA_PRE303-LSI.xlsx`. No se guardan los del IRPF (`Traslado_Libros_a_Casillas_*`,
  modelo 130) ni el listado de epígrafes, que no son del IVA.
- **Normativa del BOE** (`actual/normativa/`, 7 textos consolidados): Orden EHA/3786/2008 (modelo 303), Orden EHA/3111/2009
  (modelo 390), Orden HAP/2194/2013 (procedimientos de presentación), Órdenes HAC/1425/2025 y HAC/1347/2024 (régimen
  simplificado de 2026 y 2025), Ley 37/1992 del IVA y Reglamento del IVA (RD 1624/1992).
- **Comunes del portal de desarrolladores** (`actual/`): `EspecificacionesServiciosDeclaracionesAEAT_ES.pdf` y `consultas.xsd`,
  copiados de la carpeta de Modelo111 (`--origen manual`): su SHA-256 coincide con el publicado hoy (comprobado con el navegador).

### Versiones vigentes comprobadas
- Modelo 303: diseño de registro **2026 y siguientes, v 1.01** (actualizado el 28/01/2026); ayuda técnica actualizada el
  05/11/2026, ficha G414 el 28/09/2026, instrucciones 2026 el 26/10/2026. Especificaciones del portal: 303 de 2026 del 13/02/2026
  (una para 01-1T y otra para 02-12M y 2T-4T).
- Modelo 390: diseño de registro **2026 y siguientes** (`dr390e2026.xlsx`); ayuda técnica actualizada el 19/11/2026, ficha G412
  el 13/10/2026. Instrucciones vigentes: 2025 (para el ejercicio 2025, que se presenta en enero de 2026). Especificaciones de
  firma y envío: v 1.2 (28/11/2022), válida para 2021 y siguientes.
- Presentación: el 303 se presenta por formulario web (con ayuda Pre303), por fichero o, en 2022 y anteriores, con predeclaración
  en papel; el 390 por formulario o por fichero (también por lotes).

### Descarga manual (el portal de desarrolladores no se pudo leer con curl) – HECHA el 2026-11-26, salvo 1 fichero
`www.agenciatributaria.es` devuelve «SSL certificate problem: self signed certificate in certificate chain» (la raíz
«AC RAIZ FNMT-RCM SERVIDORES SEGUROS» no está en la lista de confianza de este equipo) y no se desactiva la verificación ni se
añade ninguna raíz. Con el navegador integrado se comprobó que los 33 ficheros de las páginas `Documentacion/IVA/Modelo_303` y
`Documentacion/IVA/Modelo_390` existen (HTTP 200) y se calculó su SHA-256; el navegador no los guarda en disco, así que **hay
que bajarlos a mano** (hecho: el usuario bajó 30 de 31 y se registraron con `--origen manual`; los 30 SHA-256 coinciden; `303_2015_89890001K_1T.303` llegó como `.txt` y se guardó con su nombre oficial) y registrarlos con `registrar_descarga.py`. Cada nombre es un enlace directo
(base `https://www.agenciatributaria.es/static_files/AEAT_Desarrolladores/EEDD/`). Las dos primeras filas son las vigentes.

| Fichero (bajo la base) | Qué es | Tamaño | Last-Modified | SHA-256 publicado |
|---|---|---|---|---|
| [`IVA/Mod_303/Especificaciones_modelo_303-2026.pdf`](https://www.agenciatributaria.es/static_files/AEAT_Desarrolladores/EEDD/IVA/Mod_303/Especificaciones_modelo_303-2026.pdf) | 303, 2026 (02-12M y 2T-4T) | 1.898.091 | 13/02/2026 | `8212de130f7e9bae5916163df861eff96d2584d8d4095e4b73f0aa7bdefedb26` |
| [`IVA/Mod_303/2026/Especif_m303_26.pdf`](https://www.agenciatributaria.es/static_files/AEAT_Desarrolladores/EEDD/IVA/Mod_303/2026/Especif_m303_26.pdf) | 303, 2026 (01-1T) | 1.838.604 | 13/02/2026 | `8850323fa4a86136bd2dcf5d37b84085f8df7183071afc0273625cdcccdc1474` |
| [`IVA/Mod_303/2025/Especif_303_2025.pdf`](https://www.agenciatributaria.es/static_files/AEAT_Desarrolladores/EEDD/IVA/Mod_303/2025/Especif_303_2025.pdf) | 303, 2025 | 1.879.791 | 04/12/2025 | `93093f5be780149ef24a6d8d6f312dc65b820171bc79138b29396cc8500826b1` |
| [`IVA/Mod_303/Especif_m303_24.pdf`](https://www.agenciatributaria.es/static_files/AEAT_Desarrolladores/EEDD/IVA/Mod_303/Especif_m303_24.pdf) | 303, 2024 | 2.040.663 | 25/09/2024 | `bfbd457ea6bb5aeabb54826fafdea5b3eb9a70e0ace1b41d520f0b498258ecd7` |
| [`IVA/Mod_303/2023/esp303_2023.pdf`](https://www.agenciatributaria.es/static_files/AEAT_Desarrolladores/EEDD/IVA/Mod_303/2023/esp303_2023.pdf) | 303, 2023 (v 1.00) | 580.305 | 30/01/2023 | `227e43a9c741dd97497c689bfe56b93eec99a1019d500554bedb6ea619969022` |
| [`IVA/Mod_303/2022/esp303_2022.doc`](https://www.agenciatributaria.es/static_files/AEAT_Desarrolladores/EEDD/IVA/Mod_303/2022/esp303_2022.doc) | 303, 2022 (v 1.1) | 2.475.520 | 15/02/2022 | `44f95a51a3e90e0a8b446e27c01be65553439893fc2ab266f794e0ad7517be2c` |
| [`IVA/Mod_303/303_NovedValidCuotasCompensPeriodosAnter.pdf`](https://www.agenciatributaria.es/static_files/AEAT_Desarrolladores/EEDD/IVA/Mod_303/303_NovedValidCuotasCompensPeriodosAnter.pdf) | 303, novedad de validación 2021 | 133.798 | 23/02/2021 | `ea96f405932ca859441083514dc09d9302a7990534a24118d462c456975928cb` |
| [`IVA/Mod_303/2021/IVA303-CoordColabora.pdf`](https://www.agenciatributaria.es/static_files/AEAT_Desarrolladores/EEDD/IVA/Mod_303/2021/IVA303-CoordColabora.pdf) | 303, 2021 (v 8.01) | 527.220 | 15/10/2021 | `d9245832b6e331ba70c5cfbb21870b9b0a983979f4942b8d2c936927fce9c49e` |
| [`IVA/Mod_303/IVA303-CoordColabora_7.02.pdf`](https://www.agenciatributaria.es/static_files/AEAT_Desarrolladores/EEDD/IVA/Mod_303/IVA303-CoordColabora_7.02.pdf) | 303, 2019 y siguientes (v 7.02) | 535.261 | 25/03/2022 | `2dce35831ee47b34b2f35d2adaca1c135d429ea2e534d3861d177eaabb2e3813` |
| [`IVA/Mod_303/IVA303-CoordColabora_2018-6.01.pdf`](https://www.agenciatributaria.es/static_files/AEAT_Desarrolladores/EEDD/IVA/Mod_303/IVA303-CoordColabora_2018-6.01.pdf) | 303, 2018 (v 6.01) | 539.045 | 15/03/2018 | `b0d7d880eceb991546f8932b790c34ed834cf95c95b43a0de7a376a08c6de379` |
| [`IVA/Mod_303/IVA303-CoordColabora_2017-5.00.doc`](https://www.agenciatributaria.es/static_files/AEAT_Desarrolladores/EEDD/IVA/Mod_303/IVA303-CoordColabora_2017-5.00.doc) | 303, 2017 (v 5.00) | 2.331.136 | 13/01/2017 | `a0d4c26b41a2fe0632c71981bba47cba77467c12a16e501104ab7096b9407bc7` |
| [`IVA/Mod_303/IVA303-CoordColabora-4.2.doc`](https://www.agenciatributaria.es/static_files/AEAT_Desarrolladores/EEDD/IVA/Mod_303/IVA303-CoordColabora-4.2.doc) | 303, 2014 a 2016 (v 4.2) | 2.368.512 | 02/12/2022 | `53e7c2cee18b151e0c427af70f2ed5114fbad688408cb9f4936600c45abb4707` |
| [`Especificaciones/2011/Ficheros/IVA303-CoordColabora-2.3.doc`](https://www.agenciatributaria.es/static_files/AEAT_Desarrolladores/EEDD/Especificaciones/2011/Ficheros/IVA303-CoordColabora-2.3.doc) | 303, anteriores a 2014 | 703.488 | 02/12/2022 | `cda8ea0de5ab52aa8a34e80e52630ac4fd296c86dcbe80cf8cca803f0aa974c4` |
| [`IVA/Mod_303/303_2015_89890001K_1T.303`](https://www.agenciatributaria.es/static_files/AEAT_Desarrolladores/EEDD/IVA/Mod_303/303_2015_89890001K_1T.303) | 303, fichero de ejemplo 2015 | 4.546 | 02/12/2022 | `5d591dfd1b73f580a515c11a869bb53c43949d39978e9dbb4e003621b03e5244` |
| [`Especificaciones/2014/ficheros/completo89_1T.303`](https://www.agenciatributaria.es/static_files/AEAT_Desarrolladores/EEDD/Especificaciones/2014/ficheros/completo89_1T.303) | 303, fichero de ejemplo 2014 (simplificado) | 5.763 | 02/12/2022 | `991d7b7ca51906dc1dacab267353a65af81c728be9c3d7003eec8c1345135545` |
| [`IVA/Mod_390/2021/esp390_2021.docx`](https://www.agenciatributaria.es/static_files/AEAT_Desarrolladores/EEDD/IVA/Mod_390/2021/esp390_2021.docx) | 390, firma y envío 2021 y siguientes (v 1.2) | 35.838 | 28/11/2022 | `a64e01502722502f1a0537d6e58593ec983d03b3e4db911277c44c2d1b314bee` |
| [`IVA/Mod_390/2019/esp390_2019.doc`](https://www.agenciatributaria.es/static_files/AEAT_Desarrolladores/EEDD/IVA/Mod_390/2019/esp390_2019.doc) | 390, firma y envío 2019 y 2020 (v 1.1) | 181.248 | 14/10/2021 | `9e24659377c8c000d3cee66b6716e0750fa992a6a7a074ca8c87be6940f5aa3e` |
| [`IVA/Mod_390/2018/esp390_2018.v1.2.pdf`](https://www.agenciatributaria.es/static_files/AEAT_Desarrolladores/EEDD/IVA/Mod_390/2018/esp390_2018.v1.2.pdf) | 390, firma y envío 2018 (v 1.2) | 97.827 | 14/11/2019 | `df0ee5a26ac6bca91ff27f1b89fa80d42a8cfa47443ce16fabeec39e943c7886` |
| [`IVA/Mod_390/esp390_2017.v1.0.doc`](https://www.agenciatributaria.es/static_files/AEAT_Desarrolladores/EEDD/IVA/Mod_390/esp390_2017.v1.0.doc) | 390, firma y envío 2017 | 194.048 | 04/12/2017 | `612d08a98601b2c27fcd74540933df4ec807e077510a74c6d4595f65c3d06244` |
| [`IVA/Mod_390/esp390_2016.v1.0.doc`](https://www.agenciatributaria.es/static_files/AEAT_Desarrolladores/EEDD/IVA/Mod_390/esp390_2016.v1.0.doc) | 390, firma y envío 2016 | 160.256 | 16/12/2016 | `9a4b44ab65dd97842100d062226699ae14006acadc8ffb4fcdb960eb05feb2bd` |
| [`IVA/Mod_390/390v01e2015_v1.00.xlsx`](https://www.agenciatributaria.es/static_files/AEAT_Desarrolladores/EEDD/IVA/Mod_390/390v01e2015_v1.00.xlsx) | 390, diseño 2015 en Excel | 517.345 | 01/02/2016 | `e6caddad441f11406dab2c3bafbb4c77828e33375a3d13330fe95bdbd552c7c0` |
| [`IVA/Mod_390/esp390_2015v1.2.doc`](https://www.agenciatributaria.es/static_files/AEAT_Desarrolladores/EEDD/IVA/Mod_390/esp390_2015v1.2.doc) | 390, firma y envío 2015 (v 1.2) | 158.208 | 28/04/2016 | `be0cea140e607f57af6e9b7b075c86390307f92e5036f30997c3b0c64a399027` |
| [`2014/IVA2014.pdf`](https://www.agenciatributaria.es/static_files/AEAT_Desarrolladores/EEDD/2014/IVA2014.pdf) | 390, esquema XML 2014 | 99.432 | 18/01/2023 | `da42a856f70fc9cf0aaf89a056a2fab079398985bed7bed95cfe84a38345ee20` |
| [`Especificaciones/2014/ficheros/esp390_2014v1.1.doc`](https://www.agenciatributaria.es/static_files/AEAT_Desarrolladores/EEDD/Especificaciones/2014/ficheros/esp390_2014v1.1.doc) | 390, firma y envío 2014 | 153.088 | 18/01/2023 | `c2d2529414c4580cd65460b755cc41ce71a458bba36da0351c1c085cfa108965` |
| [`2013/GraficoIVA2013.pdf`](https://www.agenciatributaria.es/static_files/AEAT_Desarrolladores/EEDD/2013/GraficoIVA2013.pdf) | 390, esquema XML 2013 | 130.009 | 18/01/2023 | `d235ab2480447cd90947888f96c27525427010cb5e5f580c2a8a36e2d3f65d23` |
| [`Especificaciones/2013/ficheros/esp390_2013v1.1.doc`](https://www.agenciatributaria.es/static_files/AEAT_Desarrolladores/EEDD/Especificaciones/2013/ficheros/esp390_2013v1.1.doc) | 390, firma y envío 2013 | 158.720 | 18/01/2023 | `5834f556c0bcf31f21b6a5dd5416c536da1fad592cac17dad2c612ac3bfaa437` |
| [`2012/IVA2012.pdf`](https://www.agenciatributaria.es/static_files/AEAT_Desarrolladores/EEDD/2012/IVA2012.pdf) | 390, esquema XML 2012 | 90.125 | 18/01/2023 | `440697d83f73951413eb94645b3cacbc9dff6f98c27067f96b99459cf718d791` |
| [`Especificaciones/2012/Ficheros/esp390_2012v1.2.docx`](https://www.agenciatributaria.es/static_files/AEAT_Desarrolladores/EEDD/Especificaciones/2012/Ficheros/esp390_2012v1.2.docx) | 390, firma y envío 2012 | 59.467 | 18/01/2023 | `66d5f9dd5ef31f6eb0b26c2ecfef863ac441ec55fc78a48c64455d4e5184a80c` |
| [`IVA2011_ESQUEMA.pdf`](https://www.agenciatributaria.es/static_files/AEAT_Desarrolladores/EEDD/IVA2011_ESQUEMA.pdf) | 390, esquema XML 2011 | 88.267 | 18/01/2023 | `a73d57960e936cbd04ebd80a055d9538354724d25f3e6c7bf0a60bd8ea22dc61` |
| [`Especificaciones/2011/Ficheros/esp390_2011v1.2.doc`](https://www.agenciatributaria.es/static_files/AEAT_Desarrolladores/EEDD/Especificaciones/2011/Ficheros/esp390_2011v1.2.doc) | 390, firma y envío 2011 | 138.240 | 18/01/2023 | `97bd9018a69adca36f61462b4c60cf92b9e31dd8c49fef61a29dffa4552b6d86` |
| [`MIIVA2011.pdf`](https://www.agenciatributaria.es/static_files/AEAT_Desarrolladores/EEDD/MIIVA2011.pdf) | 390, módulo de impresión 2011 | 115.160 | 18/01/2023 | `f399e0b3746668d79e63cb4b1e538b66d427987ac42a275659339bc39e8b7fe0` |

Páginas del portal leídas con el navegador (su texto no se guarda en `paginas/`): `Documentacion/IVA/Modelo_303`,
`Documentacion/IVA/Modelo_390` y `Documentacion/Documentacion` (índice). Los enlaces «Diseño de Registro» de esas páginas
llevan a las páginas de diseños de la Sede, que sí se guardan. El 390 no tiene especificación propia para 2022 y siguientes
distinta de la de 2021.

### Resultado
- 82 ficheros en el manifest (50 por script y 32 manuales, incluidos los 2 comunes).
- Pendiente de descarga manual: solo `Especificaciones/2014/ficheros/completo89_1T.303` (ejemplo de 303 de 2014 con régimen simplificado, 5.763 bytes).
