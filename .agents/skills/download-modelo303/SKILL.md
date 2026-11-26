---
name: download-modelo303
description: Descarga toda la documentación oficial actualizada de los modelos 303 y 390 de la AEAT (ayuda técnica, instrucciones, Pre303, diseños de registro, especificaciones de desarrolladores y normativa del BOE) en Doc/Context/Normativa
---

Eres un asistente que mantiene actualizada la documentación OFICIAL de los modelos 303 (autoliquidación mensual/trimestral del IVA) y 390 (declaración-resumen anual del IVA) de la Agencia Tributaria, tanto la técnica (diseños de registro, especificaciones de presentación) como la de usuario (instrucciones, ayuda de presentación, Pre303, plazos, novedades) y la normativa (BOE).

DOMINIOS PERMITIDOS: sede.agenciatributaria.gob.es, www2.agenciatributaria.gob.es, www.agenciatributaria.es (solo /static_files/AEAT_Desarrolladores/ y el portal de desarrolladores), www.boe.es. Nada de webs de terceros, foros, blogs ni GitHub, aunque aparezcan en buscadores. Los formularios y servicios con identificación (www1.agenciatributaria.gob.es/wlpl/..., Pre303 online, simulador, Cl@ve, certificados) NO se visitan ni se usan.

DESCARGA: usa SIEMPRE /usr/bin/curl (SecureTransport de macOS) con -L y un User-Agent de navegador. NUNCA uses -k/--insecure. Existe un script ya probado que implementa esta tarea; úsalo y revisa su salida:
  python3 -I /Users/daniel89fg/Trabajos/FacturaScripts/facturascripts/Plugins/Modelo303/.agents/skills/download-modelo303/scripts/sincronizar.py /Users/daniel89fg/Trabajos/FacturaScripts/facturascripts/Plugins/Modelo303/Doc/Context/Normativa
Su salida y Doc/Context/Normativa/.log_run.json incluyen: nuevos, modificados, iguales, errores, páginas nuevas/cambiadas/desaparecidas y los enlaces descubiertos. Los errores `SSL certificate problem` de `www.agenciatributaria.es` son los del portal de desarrolladores (ver regla 5); los dos ficheros comunes (`General/...`) ya están registrados a mano y su error en cada ejecución es normal.

PÁGINAS (guardar texto en paginas/, con la URL de origen en la primera línea). El script RASTREA RECURSIVAMENTE las páginas .html de estas secciones a partir de las semillas, así que las páginas nuevas se detectan solas:
- Fichas de los procedimientos (solo se guardan): /Sede/procedimientoini/G414.shtml (modelo 303) y G412.shtml (modelo 390). Son el origen de los enlaces a normativa, instrucciones y ayuda.
- Modelo 303:
  - Ayuda técnica: /Sede/ayuda/consultas-informaticas/presentacion-declaraciones-ayuda-tecnica/modelo-303* (presentación electrónica, por fichero, en papel, rectificativas, incidencias, ejercicios anteriores).
  - Instrucciones por ejercicio, tutorial, autoliquidación rectificativa y ejercicios anteriores: /Sede/todas-gestiones/impuestos-tasas/iva/modelo-303-*
  - Pre303 (servicio de ayuda a la presentación, libros registro, notas aclaratorias por ejercicio): /Sede/iva/pre-303* y /Sede/iva/presentar-declaracion-iva-modelo-303* (formas y plazos de presentación).
- Modelo 390:
  - Ayuda técnica: /Sede/ayuda/consultas-informaticas/presentacion-declaraciones-ayuda-tecnica/modelo-390* (formulario, fichero, simulador).
  - Instrucciones, plazos y ejercicios anteriores: /Sede/todas-gestiones/impuestos-tasas/iva/modelo-390-*
- Diseños de registro: /Sede/ayuda/disenos-registro/modelos-300-399.html y ejercicios-anteriores-modelos-300-399.html (solo se descargan los ficheros del 303 y del 390).
- Ayuda común: consulta de declaraciones presentadas y presentación de documentación complementaria.
- Si se amplía el rastreo, cambia CRAWL_PREFIXES / PAGES / EXCLUIR_PAGINAS / EXCLUIR_ENLACES en sincronizar.py.
- Las páginas guardadas antes que ya no aparecen se listan en "paginas_desaparecidas" (anótalas en el CHANGELOG; no se borran).

FICHEROS DIRECTOS (a actual/):
- Diseños de registro (se descubren en las páginas de diseños y se fijan en DIRECT por si cambian; los de 2022, 2024 y 2025 ya no figuran en la lista de la Sede, pero siguen publicados en su carpeta). El plugin usa el 303 de 2025 y 2026 y el 390 de 2024 a 2026:
  - 303: DR303e26v101.xlsx (2026 y siguientes, v 1.01), DR303e25.xlsx (2025, v 1.02), DR303e24.xlsx, DR303e24v200.xlsx, DR303e23.xlsx, DR303e22.xlsx, DR303e21v200.xlsx, DR303e21v103.xlsx y los de 2012 a 2020.
  - 390: dr390e2026.xlsx, dr390e2025.xlsx, dr390e2024.xlsx, dr390e2023v100.xlsx, dr390e2022.xlsx, dr390e2021.xlsx y los de 2015 a 2019.
- Instrucciones en PDF: https://sede.agenciatributaria.gob.es/static_files/Sede/Procedimiento_ayuda/G414/Inst_mod_303_2025.pdf, .../G412/Instrucciones_modelo_390-2025.pdf y .../G412/instr390.pdf (las del 303 de 2026 son una página HTML, que se guarda en paginas/).
- Portal de desarrolladores (base https://www.agenciatributaria.es/static_files/AEAT_Desarrolladores/EEDD/), páginas `Documentacion/IVA/Modelo_303` y `Documentacion/IVA/Modelo_390`:
  - Especificaciones de los servicios de ayuda a la presentación del 303: `IVA/Mod_303/Especificaciones_modelo_303-2026.pdf` (periodos 02-12M y 2T-4T de 2026), `IVA/Mod_303/2026/Especif_m303_26.pdf` (periodo 01-1T de 2026), `IVA/Mod_303/2025/Especif_303_2025.pdf`, `IVA/Mod_303/Especif_m303_24.pdf`, `IVA/Mod_303/2023/esp303_2023.pdf`, `IVA/Mod_303/2022/esp303_2022.doc` y las de 2011 a 2021, la novedad de validación de cuotas a compensar y los dos ficheros de ejemplo `.303` de 2014 y 2015.
  - Especificaciones para la firma y envío del 390 (`IVA/Mod_390/2021/esp390_2021.docx` para 2021 y siguientes, y las de 2011 a 2019) y los esquemas XML anteriores a 2015.
  - General/EspecificacionesServiciosDeclaracionesAEAT_ES.pdf y General/consultas.xsd (servicios comunes de declaraciones).
- Cualquier otro fichero (.pdf .zip .xls .xlsx .xsd .xml .doc .docx .txt) enlazado desde las páginas anteriores, salvo los de otros modelos (EXCLUIR_ENLACES): manual de IVA 2026, notas aclaratorias de Pre303 por ejercicio, plantillas y formato electrónico de los libros registro del IVA, cálculo de casillas de Pre303, etc.

NORMATIVA (guardar el texto consolidado en actual/normativa/, desde https://www.boe.es/buscar/act.php?id=BOE-A-...): las normas que la propia Sede enlaza en las fichas G414 y G412:
- 303: Orden EHA/3786/2008 (BOE-A-2008-20953), que aprueba el modelo 303 (y el 308), y la Orden HAP/2194/2013 (BOE-A-2013-12385, procedimientos de presentación).
- 390: Orden EHA/3111/2009 (BOE-A-2009-18472), que aprueba el modelo 390.
- Régimen simplificado (casillas del 303 y 390 de módulos): Orden HAC/1425/2025 (BOE-A-2025-25272, ejercicio 2026) y Orden HAC/1347/2024 (BOE-A-2024-24949, ejercicio 2025).
- Base legal: Ley 37/1992 del IVA (BOE-A-1992-28740) y Reglamento del IVA, RD 1624/1992 (BOE-A-1992-28925).
- Las órdenes que ordenan el diseño de registro de cada ejercicio se citan en las propias hojas de los diseños (por ejemplo, HAC/646/2021 para el 303 de 2021). En cada ejecución, mira en las fichas G414 y G412 y en las páginas de instrucciones si aparece una orden nueva que no esté en la lista (p. ej. la del régimen simplificado del ejercicio siguiente o una modificación de la orden del 303) y añádela a DIRECT en sincronizar.py. Anota en el CHANGELOG si el texto consolidado muestra nuevas modificaciones.

REGLAS COMUNES
1. Fuentes: SOLO los dominios oficiales listados en esta tarea.
2. Estructura dentro de la carpeta Doc/Context/Normativa del plugin:
   - actual/            → última versión de cada fichero (nombre original conservado)
   - actual/normativa/  → textos consolidados del BOE
   - Nombres: ningún nombre de archivo pasa de 60 caracteres (el script los acorta con `nombre_corto()` de `../FiscalES/.agents/skills/download-fiscales/scripts/nombres.py`, que añade una huella del nombre completo; el oficial queda en la URL del manifest), para que el plugin se pueda descargar en Windows. Si el script avisa de un `NOMBRE LARGO`, corrígelo antes de terminar.
   - historico/AAAA-MM-DD/ → versiones anteriores sustituidas (mover aquí, nunca borrar)
   - paginas/           → copia en texto/Markdown de cada página (primera línea: URL de origen) para detectar cambios
   - manifest.json      → por fichero: url, ruta, sha256, tamaño, fechas de descarga y de comprobación, cabeceras ETag/Last-Modified
   - CHANGELOG.md       → añade una entrada con fecha por cada ejecución
3. Procedimiento en cada ejecución:
   a) Descarga cada URL de la lista. Calcula SHA-256 y compáralo con manifest.json.
   b) Si el hash cambió: mueve la versión vieja a historico/AAAA-MM-DD/ y guarda la nueva en actual/.
   c) En cada página, extrae TODOS los enlaces a documentos y descarga los que no estén en el manifest (así detectas documentos nuevos con nombre distinto).
   d) Anota en el CHANGELOG: ficheros nuevos, modificados (con versión/fecha que figure dentro del documento), desaparecidos (404) y páginas cuyo texto cambió. Si una página muestra "Página actualizada: fecha", guárdala.
4. Seguridad: no ejecutes ni abras macros de ningún fichero descargado; no instales nada; no rellenes formularios ni inicies sesión; no accedas a nada que requiera certificado o contraseña. No desactives nunca la verificación SSL. Los ficheros de ejemplo `.303` del portal son datos de prueba: no se suben a ningún servicio.
5. Hay que intentar descargar siempre todo (decisión del usuario del 27/10/2026). El portal de desarrolladores (www.agenciatributaria.es) usa la cadena de certificados de la FNMT (raíz «AC RAIZ FNMT-RCM SERVIDORES SEGUROS»), que `curl` no verifica en este equipo («SSL certificate problem: self signed certificate in certificate chain»): no se usa -k ni se añade ninguna raíz de confianza. Sigue con el navegador integrado y, si tampoco, con la lista de descarga manual del usuario, que se registra con `registrar_descarga.py` del núcleo (`<base>` = `Doc/Context/Normativa`). Procedimiento común a las skills de descarga: `../FiscalES/.agents/skills/download-fiscales/NAVEGADOR.md`. El navegador entra como un visitante normal: no se resuelven ni se saltan CAPTCHAs ni comprobaciones anti-bots, no se falsean cabeceras ni el agente de usuario y no se inicia sesión. Apunta en el CHANGELOG qué se comprobó con el navegador y qué queda en "Descarga manual".
   Páginas del portal de desarrolladores a revisar con el navegador (su texto no se guarda en paginas/): `AEAT.desarrolladores/Desarrolladores/_menu_/Documentacion/IVA/Modelo_303/Modelo_303.html`, `.../IVA/Modelo_390/Modelo_390.html`, `.../Documentacion/General/General.html` y `.../IVA/IVA.html`; el tablón de anuncios (`Desarrolladores.html`) avisa cuando un modelo cambia de diseño. Con `javascript_tool` comprueba cada fichero (estado, tamaño y SHA-256) y compara con el manifest; si es nuevo o cambió, va a la lista manual.
6. Resumen final (máx. 20 líneas): nº de ficheros comprobados, nuevos, modificados, errores y una lista destacada de los cambios relevantes para un desarrollador (nuevo diseño de registro del 303 o del 390, versión del diseño, casillas nuevas, órdenes nuevas, plazos, formas de presentación). Sección "DESCARGA MANUAL" con lo que el usuario tiene que bajar (URL, motivo y dónde guardarlo), o "Nada que descargar a mano". Si no hay cambios, dilo en una línea.
7. Si algún documento nuevo o modificado menciona un nuevo diseño de registro, nuevas casillas, cambios en la forma de presentación o fechas de entrada en vigor, ponlo en una sección "ALERTA" al inicio del resumen, y avisa de que el código del plugin (Doc/Context/05-ficheros-aeat.md: `Lib/Modelo303File.php` y `Lib/Modelo390File.php`) puede necesitar ajustes: cada año se añade el diseño nuevo y no se tocan los anteriores.
