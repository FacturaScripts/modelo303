Plugin **Modelo303** para FacturaScripts: Modelo 303 y 390 de la Hacienda española para la declaración trimestral y anual de IVA.

Antes de tocar nada lee [`Doc/Context/README.md`](Doc/Context/README.md): es el índice del contexto (temas y
decisiones) y dice qué archivo abrir para cada tema; no hace falta leerlo todo.

## Documentación

| Ruta | Para quién | Qué guarda |
|---|---|---|
| [`Doc/User/`](Doc/User/) | Quien usa el programa | Páginas `.md` que explican cómo se usa |
| [`Doc/Dev/`](Doc/Dev/) | Quien programa | Páginas `.md` que explican cómo personalizarlo desde otro plugin |
| [`Doc/Context/`](Doc/Context/) | El equipo y los agentes | Por qué el código es como es: las decisiones tomadas, con su índice en `README.md` |
| [`Doc/Assets/`](Doc/Assets/) | Toda la documentación | Imágenes, vídeos y otros archivos enlazados desde las páginas |
| [`Doc/forja.json`](Doc/forja.json) | La forja de facturascripts.com | Datos de cada página de `User` y `Dev` para publicarlas, y el id de su publicación |

- La documentación se actualiza en el mismo trabajo que cambia el código; una decisión nueva se anota en
  `Doc/Context/` y en su índice.
- `Doc/forja.json` agrupa en `User` y `Dev` las páginas de esas carpetas por su nombre de archivo, con campos
  opcionales: `title`, `title_short`, `permalink` (enlace en la web, máx. 200 caracteres; no se puede cambiar una vez
  publicada la página), `parent` (nombre de archivo de la página padre, con `.md`), `ordernum`, `youtubeid`,
  `imageurl`, `icon`, `color`, `keywords` y `controllers` (máx. 300 caracteres cada uno; `controllers`, separados por
  comas sin espacios) y `visibility` (`true` o `false`, sin comillas). Al publicar una página, `forja_publish_doc_page`
  de fs-forja añade `idpublication` y `assets` (las imágenes ya subidas): se versionan con la página.
- `CLAUDE.md` solo importa este archivo (`@AGENTS.md`). Las instrucciones locales que no se versionan van en
  `CLAUDE.local.md` (Claude Code) o `AGENTS.override.md` (Codex).
