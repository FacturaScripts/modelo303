# API de solo lectura

Dos endpoints para fs-mcp (`f3fb68e`): `GET /api/3/modelo303` y `GET /api/3/modelo390`.

## Cómo es hoy

- `Init.php` registra las rutas con `Kernel::addRoute` y las añade a los recursos de `/api/3`.
- Los controladores (`Controller/ApiModelo303.php`, `ApiModelo390.php`) solo admiten GET y pasan la query a `Modelo303Api::build()` o `Modelo390Api::build()`.
- Parámetros: `periodo` (T1-T4 o 01-12 en el 303; solo ANUAL en el 390), `codejercicio` o `idempresa` y `anio`, `detalle`.
- Si la regularización del periodo existe, calcula sobre ella; si no, sobre una en memoria que no se guarda.
- Una clave sin acceso completo necesita permiso de lectura del recurso `modelo303` o `modelo390`.

## Al cambiarlo

Nunca guardes ni modifiques registros en estos endpoints. Prueba con `Modelo303ApiTest` y `Modelo390ApiTest`.
