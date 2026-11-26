#!/usr/bin/env python3
"""Sincroniza la documentación oficial de los modelos 303 y 390 de la AEAT en la carpeta Normativa (primer argumento).

Uso:
  python3 -I sincronizar.py <carpeta Normativa>

Descarga, solo de dominios oficiales (Sede de la AEAT, portal de desarrolladores de la AEAT y BOE):
- las páginas de ayuda, instrucciones, Pre303 y plazos de los modelos 303 y 390 (se guarda su texto en paginas/),
- los diseños de registro (303 y 390, ficheros .xlsx), las instrucciones en PDF y los documentos enlazados,
- la normativa del BOE (órdenes que aprueban y desarrollan los modelos, Ley y Reglamento del IVA).

Estructura de salida: actual/ (última versión), historico/AAAA-MM-DD/ (versiones sustituidas), paginas/ (texto de las
páginas), manifest.json (url, sha256, tamaño, fechas y cabeceras) y .log_run.json (resultado de la última ejecución).
La TSL y la LOTL no se descargan aquí: son comunes a los plugins fiscales y las mantiene la skill download-fiscales
del núcleo FiscalES.

@author Daniel Fernández Giménez <contacto@danielfg.es>
"""
import hashlib, json, os, re, shutil, sys, subprocess, html
from datetime import date
from urllib.parse import urljoin, urlparse, unquote

# Nombres cortos, comunes a los plugins fiscales (../FiscalES/.agents/skills/download-fiscales/scripts/nombres.py):
# Windows no admite rutas largas, así que ningún archivo guardado pasa de 60 caracteres.
sys.path.insert(0, os.path.join(os.path.dirname(os.path.realpath(__file__)), *[".."] * 5, "FiscalES", ".agents", "skills",
                                "download-fiscales", "scripts"))
from nombres import avisar, nombre_corto  # noqa: E402

ROOT = os.path.abspath(sys.argv[1])
TODAY = date.today().isoformat()
ALLOWED = {"sede.agenciatributaria.gob.es", "www2.agenciatributaria.gob.es", "www.agenciatributaria.es", "www.boe.es"}
UA = "Mozilla/5.0 (compatible; doc-sync)"
EXT = re.compile(r"\.(pdf|zip|7z|xsd|wsdl|xls|xlsx|xml|json|doc|docx|txt)(\?|$)", re.I)

SEDE = "https://sede.agenciatributaria.gob.es"
SEDE_HOST = urlparse(SEDE).netloc
AYUDA = SEDE + "/Sede/ayuda/consultas-informaticas"
IVA = SEDE + "/Sede/todas-gestiones/impuestos-tasas/iva"

# Páginas semilla: desde ellas se rastrean recursivamente las de CRAWL_PREFIXES.
PAGES = [
    # fichas de los procedimientos (.shtml): solo se guardan, no se rastrean
    SEDE + "/Sede/procedimientoini/G414.shtml",  # modelo 303
    SEDE + "/Sede/procedimientoini/G412.shtml",  # modelo 390
    # modelo 303
    AYUDA + "/presentacion-declaraciones-ayuda-tecnica/modelo-303.html",
    IVA + "/modelo-303-iva-autoliquidacion_/instrucciones-2026.html",
    IVA + "/modelo-303-iva-autoliquidacion_/instrucciones-2025.html",
    IVA + "/modelo-303-iva-autoliquidacion_/instrucciones.html",
    IVA + "/modelo-303-iva-autoliquidacion_/tutorial-presentacion.html",
    IVA + "/modelo-303-iva-autoliquidacion_/autoliquidacion-rectificativa.html",
    IVA + "/modelo-303-iva-autoliquidacion_/ejercicios-anteriores.html",
    SEDE + "/Sede/iva/pre-303.html",
    SEDE + "/Sede/iva/presentar-declaracion-iva-modelo-303.html",
    # modelo 390
    AYUDA + "/presentacion-declaraciones-ayuda-tecnica/modelo-390.html",
    IVA + "/modelo-390-iva-declaracion-resumen-anual_/ejercicios-anteriores.html",
    IVA + "/modelo-390-iva-declaracion-resumen-anual_/plazos-presentacion.html",
    # diseños de registro: solo se descargan los de los modelos 303 y 390 (ver FILTROS_ENLACES)
    SEDE + "/Sede/ayuda/disenos-registro/modelos-300-399.html",
    SEDE + "/Sede/ayuda/disenos-registro/ejercicios-anteriores-modelos-300-399.html",
    # ayuda técnica común a las declaraciones y a la presentación
    AYUDA + "/otros-servicios-ayuda-tecnica/consulta-declaraciones-presentadas.html",
    AYUDA + "/presentacion-declaraciones-ayuda-tecnica/presentacion-documentacion-complementaria.html",
]
# Secciones oficiales que se rastrean completas: se siguen los enlaces .html cuya ruta empieza por uno de estos prefijos.
CRAWL_PREFIXES = (
    "/Sede/ayuda/consultas-informaticas/presentacion-declaraciones-ayuda-tecnica/modelo-303",
    "/Sede/ayuda/consultas-informaticas/presentacion-declaraciones-ayuda-tecnica/modelo-390",
    "/Sede/todas-gestiones/impuestos-tasas/iva/modelo-303-",
    "/Sede/todas-gestiones/impuestos-tasas/iva/modelo-390-",
    "/Sede/iva/pre-303",
    "/Sede/iva/presentar-declaracion-iva-modelo-303",
)
# Solo interesan los modelos 303 y 390 y las páginas generales: se descartan las de otros modelos (/modelo-308.html, etc.).
EXCLUIR_PAGINAS = re.compile(r"/modelos?-(?!303|390)\d{3}")
# Ficheros enlazados que no son del 303 ni del 390 (SII, otros modelos, etc.).
EXCLUIR_ENLACES = re.compile(r"(?i)G417|FaqGral|FAQs_SII|SII_|Traslado_Libros_a_Casillas_(130|IRPF)|Epigrafes")
# Fichas .shtml: se guardan y se descargan sus ficheros, pero no se rastrean sus páginas.
SOLO_GUARDAR = re.compile(r"\.shtml$")
# De algunas páginas (listas de diseños de registro de todos los modelos) solo se descargan los enlaces que cumplan el filtro.
FILTROS_ENLACES = {
    "/Sede/ayuda/disenos-registro/modelos-300-399.html": re.compile(r"(?i)(?<!\d)(303|390)(?!\d)"),
    "/Sede/ayuda/disenos-registro/ejercicios-anteriores-modelos-300-399.html": re.compile(r"(?i)(?<!\d)(303|390)(?!\d)"),
}
MAX_PAGES = 300
DEV = "https://www.agenciatributaria.es/static_files/AEAT_Desarrolladores/EEDD/"

DIRECT = [
    # diseños de registro (se descubren también desde la página de diseños, pero se fijan aquí por si cambia)
    (SEDE + "/static_files/Sede/Disenyo_registro/DR_300_399/archivos_26/DR303e26v101.xlsx", "actual", None),
    (SEDE + "/static_files/Sede/Disenyo_registro/DR_300_399/archivos_26/dr390e2026.xlsx", "actual", None),
    # diseños de ejercicios anteriores (ya no están en la lista de la Sede, pero siguen publicados en la misma carpeta;
    # el plugin usa el del 303 de 2025 y los del 390 de 2024 y 2025)
    (SEDE + "/static_files/Sede/Disenyo_registro/DR_300_399/archivos_25/DR303e25.xlsx", "actual", None),
    (SEDE + "/static_files/Sede/Disenyo_registro/DR_300_399/archivos_22/DR303e22.xlsx", "actual", None),
    (SEDE + "/static_files/Sede/Disenyo_registro/DR_300_399/archivos_25/dr390e2025.xlsx", "actual", None),
    (SEDE + "/static_files/Sede/Disenyo_registro/DR_300_399/archivos_24/dr390e2024.xlsx", "actual", None),
    (SEDE + "/static_files/Sede/Disenyo_registro/DR_300_399/archivos_22/dr390e2022.xlsx", "actual", None),
    (SEDE + "/static_files/Sede/Disenyo_registro/DR_300_399/archivos_21/dr390e2021.xlsx", "actual", None),
    # instrucciones de cumplimentación en PDF
    (SEDE + "/static_files/Sede/Procedimiento_ayuda/G414/Inst_mod_303_2025.pdf", "actual", None),
    (SEDE + "/static_files/Sede/Procedimiento_ayuda/G412/Instrucciones_modelo_390-2025.pdf", "actual", None),
    (SEDE + "/static_files/Sede/Procedimiento_ayuda/G412/instr390.pdf", "actual", None),
    # portal de desarrolladores (www.agenciatributaria.es), páginas Documentacion/IVA/Modelo_303 y Modelo_390: su certificado
    # (cadena FNMT) no lo verifica siempre curl; lo que falle va a la lista de descarga manual de SKILL.md.
    # Especificaciones de ayuda a la presentación del 303
    (DEV + "IVA/Mod_303/Especificaciones_modelo_303-2026.pdf", "actual", None),
    (DEV + "IVA/Mod_303/2026/Especif_m303_26.pdf", "actual", None),
    (DEV + "IVA/Mod_303/2025/Especif_303_2025.pdf", "actual", None),
    (DEV + "IVA/Mod_303/Especif_m303_24.pdf", "actual", None),
    (DEV + "IVA/Mod_303/2023/esp303_2023.pdf", "actual", None),
    (DEV + "IVA/Mod_303/2022/esp303_2022.doc", "actual", None),
    (DEV + "IVA/Mod_303/303_NovedValidCuotasCompensPeriodosAnter.pdf", "actual", None),
    (DEV + "IVA/Mod_303/2021/IVA303-CoordColabora.pdf", "actual", None),
    (DEV + "IVA/Mod_303/IVA303-CoordColabora_7.02.pdf", "actual", None),
    (DEV + "IVA/Mod_303/IVA303-CoordColabora_2018-6.01.pdf", "actual", None),
    (DEV + "IVA/Mod_303/IVA303-CoordColabora_2017-5.00.doc", "actual", None),
    (DEV + "IVA/Mod_303/IVA303-CoordColabora-4.2.doc", "actual", None),
    (DEV + "Especificaciones/2011/Ficheros/IVA303-CoordColabora-2.3.doc", "actual", None),
    (DEV + "IVA/Mod_303/303_2015_89890001K_1T.303", "actual", None),
    (DEV + "Especificaciones/2014/ficheros/completo89_1T.303", "actual", None),
    # Especificaciones de firma y envío del 390 y esquemas de ejercicios anteriores
    (DEV + "IVA/Mod_390/2021/esp390_2021.docx", "actual", None),
    (DEV + "IVA/Mod_390/2019/esp390_2019.doc", "actual", None),
    (DEV + "IVA/Mod_390/2018/esp390_2018.v1.2.pdf", "actual", None),
    (DEV + "IVA/Mod_390/esp390_2017.v1.0.doc", "actual", None),
    (DEV + "IVA/Mod_390/esp390_2016.v1.0.doc", "actual", None),
    (DEV + "IVA/Mod_390/390v01e2015_v1.00.xlsx", "actual", None),
    (DEV + "IVA/Mod_390/esp390_2015v1.2.doc", "actual", None),
    (DEV + "2014/IVA2014.pdf", "actual", None),
    (DEV + "Especificaciones/2014/ficheros/esp390_2014v1.1.doc", "actual", None),
    (DEV + "2013/GraficoIVA2013.pdf", "actual", None),
    (DEV + "Especificaciones/2013/ficheros/esp390_2013v1.1.doc", "actual", None),
    (DEV + "2012/IVA2012.pdf", "actual", None),
    (DEV + "Especificaciones/2012/Ficheros/esp390_2012v1.2.docx", "actual", None),
    (DEV + "IVA2011_ESQUEMA.pdf", "actual", None),
    (DEV + "Especificaciones/2011/Ficheros/esp390_2011v1.2.doc", "actual", None),
    (DEV + "MIIVA2011.pdf", "actual", None),
    # comunes a todas las declaraciones
    (DEV + "General/EspecificacionesServiciosDeclaracionesAEAT_ES.pdf", "actual", None),
    (DEV + "General/consultas.xsd", "actual", None),
    # normativa: órdenes que aprueban y desarrollan los modelos 303 y 390 (los enlaces que da la Sede en sus fichas)
    ("https://www.boe.es/buscar/act.php?id=BOE-A-2008-20953", "actual/normativa", "BOE-A-2008-20953_Orden-EHA-3786-2008_mod303.html"),
    ("https://www.boe.es/buscar/act.php?id=BOE-A-2009-18472", "actual/normativa", "BOE-A-2009-18472_Orden-EHA-3111-2009_mod390.html"),
    ("https://www.boe.es/buscar/act.php?id=BOE-A-2013-12385", "actual/normativa", "BOE-A-2013-12385_Orden-HAP-2194-2013.html"),
    ("https://www.boe.es/buscar/act.php?id=BOE-A-2025-25272", "actual/normativa", "BOE-A-2025-25272_Orden-HAC-1425-2025_simplificado-2026.html"),
    ("https://www.boe.es/buscar/act.php?id=BOE-A-2024-24949", "actual/normativa", "BOE-A-2024-24949_Orden-HAC-1347-2024_simplificado-2025.html"),
    # base legal del IVA
    ("https://www.boe.es/buscar/act.php?id=BOE-A-1992-28740", "actual/normativa", "BOE-A-1992-28740_Ley-37-1992-IVA.html"),
    ("https://www.boe.es/buscar/act.php?id=BOE-A-1992-28925", "actual/normativa", "BOE-A-1992-28925_RD-1624-1992-Reglamento-IVA.html"),
]

mpath = os.path.join(ROOT, "manifest.json")
manifest = json.load(open(mpath)) if os.path.exists(mpath) else {}
log = {"nuevos": [], "modificados": [], "iguales": 0, "errores": [], "paginas_cambiadas": [], "paginas_nuevas": [],
       "paginas_desaparecidas": [], "enlaces": {}}


def fetch(url):
    """Descarga con /usr/bin/curl (SecureTransport de macOS). Devuelve código HTTP, cuerpo, cabeceras y error."""
    url = url.replace(" ", "%20")  # algunos enlaces de la Sede llevan espacios en el nombre del fichero
    hdr = os.path.join(ROOT, ".hdr.tmp"); body = os.path.join(ROOT, ".body.tmp")
    r = subprocess.run(["/usr/bin/curl", "-sS", "-L", "-m", "120", "-A", UA, "-D", hdr, "-o", body, "-w", "%{http_code}", url],
                       capture_output=True, text=True)
    code = r.stdout.strip()
    heads = {}
    if os.path.exists(hdr):
        blocks = [b for b in open(hdr, errors="ignore").read().split("\r\n\r\n") if b.strip()]
        for ln in (blocks[-1] if blocks else "").splitlines():
            if ":" in ln:
                k, v = ln.split(":", 1); heads[k.strip().lower()] = v.strip()
    data = open(body, "rb").read() if os.path.exists(body) else b""
    for f in (hdr, body):
        if os.path.exists(f): os.remove(f)
    return code, data, heads, r.stderr.strip()


def store(url, data, heads, dest_dir, fname):
    """Guarda el fichero en dest_dir; si ya existía y cambió, mueve el anterior a historico/AAAA-MM-DD/."""
    if fname.lower().endswith(".html"):
        # el BOE añade parámetros aleatorios (?rnd=NNN) a sus scripts: se eliminan para no detectar cambios falsos
        data = re.sub(rb"\?rnd=\d+", b"", data)
    sha = hashlib.sha256(data).hexdigest()
    os.makedirs(os.path.join(ROOT, dest_dir), exist_ok=True)
    rel = os.path.join(dest_dir, fname)
    old = manifest.get(url)
    if old and old["sha256"] == sha:
        old["comprobado"] = TODAY
        log["iguales"] += 1
        return "igual"
    status = "nuevo"
    if old and os.path.exists(os.path.join(ROOT, old["ruta"])):
        hd = os.path.join(ROOT, "historico", TODAY, os.path.dirname(old["ruta"]))
        os.makedirs(hd, exist_ok=True)
        shutil.move(os.path.join(ROOT, old["ruta"]), os.path.join(hd, os.path.basename(old["ruta"])))
        status = "modificado"
    open(os.path.join(ROOT, rel), "wb").write(data)
    manifest[url] = {"url": url, "ruta": rel, "sha256": sha, "tamano": len(data), "descargado": TODAY, "comprobado": TODAY,
                     "etag": heads.get("etag"), "last_modified": heads.get("last-modified")}
    log["nuevos" if status == "nuevo" else "modificados"].append(rel)
    return status


def to_text(h):
    """Convierte el HTML de una página en Markdown sencillo conservando los enlaces."""
    h = re.sub(r"(?is)<(script|style|noscript)[^>]*>.*?</\1>", "", h)
    h = re.sub(r"(?i)<br\s*/?>|</(p|div|li|tr|h\d|ul|ol|table)>", "\n", h)
    h = re.sub(r"(?is)<a\s[^>]*href=[\"']([^\"']+)[\"'][^>]*>(.*?)</a>",
               lambda m: "[%s](%s)" % (re.sub(r"<[^>]+>", "", m.group(2)).strip(), m.group(1)), h)
    h = re.sub(r"<[^>]+>", "", h)
    h = html.unescape(h)
    h = re.sub(r"[ \t\r\f\v]+", " ", h)
    h = re.sub(r"\n\s*\n+", "\n\n", h)
    return h.strip() + "\n"


def slug(url):
    """Nombre del Markdown de una página: su ruta en la sede, acortada con nombre_corto() si pasa de 60 caracteres."""
    ruta = urlparse(url).path.split("/Sede/")[-1]
    ruta = re.sub(r"\.s?html$", "", ruta)
    return nombre_corto(re.sub(r"[^A-Za-z0-9._-]+", "_", ruta) + ".md")


links = {}
# 1) páginas: rastreo recursivo dentro de CRAWL_PREFIXES a partir de las semillas
queue, visited, saved = list(PAGES), set(), set()
while queue and len(visited) < MAX_PAGES:
    url = queue.pop(0)
    if url in visited: continue
    visited.add(url)
    code, data, heads, err = fetch(url)
    if code != "200" or not data:
        log["errores"].append((url, code, err)); continue
    h = data.decode("utf-8", errors="ignore")
    txt = "<!-- Fuente: %s -->\n\n" % url + to_text(h)
    fname = slug(url)
    saved.add(fname)
    pth = os.path.join(ROOT, "paginas", fname)
    os.makedirs(os.path.dirname(pth), exist_ok=True)
    prev = open(pth).read() if os.path.exists(pth) else None
    if prev is None: log["paginas_nuevas"].append(fname)
    elif prev != txt: log["paginas_cambiadas"].append(fname)
    open(pth, "w").write(txt)
    m = re.search(r"(?i)p[aá]gina actualizada[^\n]{0,80}", txt)
    if m: log.setdefault("actualizada", {})[fname] = re.sub(r"\s+", " ", m.group(0)).strip()
    seguir = not SOLO_GUARDAR.search(urlparse(url).path)
    filtro = FILTROS_ENLACES.get(urlparse(url).path)
    for href in re.findall(r"href=[\"']([^\"'#]+)[\"']", h):
        full = urljoin(url, html.unescape(href))
        pu = urlparse(full)
        if seguir and pu.netloc == SEDE_HOST and pu.path.endswith(".html") and pu.path.startswith(CRAWL_PREFIXES) \
                and not EXCLUIR_PAGINAS.search(pu.path):
            clean = SEDE + pu.path
            if clean not in visited and clean not in queue: queue.append(clean)
        elif pu.netloc in ALLOWED and EXT.search(pu.path):
            if (filtro is None or filtro.search(os.path.basename(pu.path))) and not EXCLUIR_ENLACES.search(pu.path):
                links.setdefault(full, url)
if queue:
    log["errores"].append(("rastreo", "", "se alcanzó MAX_PAGES=%d; quedan %d páginas sin revisar" % (MAX_PAGES, len(queue))))
# páginas guardadas en ejecuciones anteriores que ya no se encuentran (borradas o sin enlace)
if os.path.isdir(os.path.join(ROOT, "paginas")):
    for f in sorted(os.listdir(os.path.join(ROOT, "paginas"))):
        if f.endswith(".md") and f not in saved:
            log["paginas_desaparecidas"].append(f)

# 2) ficheros directos
direct_urls = set()
for item in DIRECT:
    url, d = item[0], item[1]
    fname = nombre_corto(item[2] if len(item) > 2 and item[2] else unquote(os.path.basename(urlparse(url).path)))
    direct_urls.add(url)
    code, data, heads, err = fetch(url)
    if code != "200" or not data:
        log["errores"].append((url, code, err)); continue
    if fname.lower().endswith((".xsd", ".wsdl", ".xml")) and not data.lstrip(b"\xef\xbb\xbf \r\n\t").startswith(b"<?xml"):
        log["errores"].append((url, code, "no empieza por <?xml (posible HTML de mantenimiento); no guardado")); continue
    store(url, data, heads, d, fname)


# 3) enlaces extraídos de las páginas que no estén ya en la lista de ficheros directos
def norm(u): return u.split("?")[0].lower()


known = {norm(u) for u in direct_urls}
for full, origen in sorted(links.items()):
    if norm(full) in known: continue
    fname = nombre_corto(unquote(os.path.basename(urlparse(full).path)).replace(" ", "_"))
    code, data, heads, err = fetch(full)
    if code != "200" or not data:
        log["errores"].append((full, code, err)); continue
    store(full, data, heads, "actual", fname)
    log["enlaces"][full] = origen

json.dump(manifest, open(mpath, "w"), indent=1, ensure_ascii=False)
log["nombres_largos"] = [rel for rel, _ in avisar(ROOT)]
json.dump(log, open(os.path.join(ROOT, ".log_run.json"), "w"), indent=1, ensure_ascii=False)
print(json.dumps({k: (v if k not in ("enlaces", "actualizada") else len(v)) for k, v in log.items()}, indent=1, ensure_ascii=False))
