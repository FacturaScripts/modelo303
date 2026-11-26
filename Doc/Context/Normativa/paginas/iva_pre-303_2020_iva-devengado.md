<!-- Fuente: https://sede.agenciatributaria.gob.es/Sede/iva/pre-303/2020/iva-devengado.html -->

Agencia Tributaria: 2020 - IVA devengado

 [Saltar al contenido principal](#acc-main) 

 [Logotipo del Gobierno de EspañaLogotipo Organismo](/Sede/inicio.html)

 Menú móvil[Menu](#)

[Inicio](/Sede/inicio.html)
[IVA](/Sede/iva.html)
[PRE 303 (Servicio ayuda modelo 303)](/Sede/iva/pre-303.html)

 2020
[Saltar índice de la información](#js-nombre-canal-paginado)ÍndiceÍndice:
[Información adicional](/Sede/iva/pre-303/2020.html)
[IVA deducible](/Sede/iva/pre-303/2020/iva-deducible.html)
IVA devengado
[Operaciones en el ejercicio](/Sede/iva/pre-303/2020/operaciones-ejercicio.html)

IVA devengado
Los campos incluidos en tipo de factura, tipo de operación y clave de régimen especial se pueden consultar en el documento "[Bloques Funcionales](/static_files/Sede/Procedimiento_ayuda/G417/FicherosSuministros/V_1_1/DR_v1.1.xls)"

CASILLA LLAA DATOS SII 

 OPERACIÓN LR 
TIPO FACTURA 

TIPO OPERACIÓN (TO)CLAVE RREE 

00001
BI AL 4%
Tipo operación S1 y S3. Sumar las BI al 4% de S1 con cuota repercutida mayor que cero + BI al 4% de S3 (solo las líneas con cuota repercutida mayor que cero) en el LRFE con fecha de operación y en su defecto fecha de expedición en el periodo autoliquidado

LRFE

F1 F2 F4
S1 S3 (CON CUOTA)
01 11 12 13 15

00002
4%
Tipo 4%
LRFE

00003
CI AL 4%
CI al 4% de S1 con cuota repercutida mayor que cero + CI al 4% de S3 (solo líneas con cuota repercutida mayor que cero)
LRFE
F1 F2 F4
S1 S3 (CON CUOTA)
01 11 12 13 15

00004
BI AL 10%
Tipo operación S1 y S3. Sumar las BI al 10% de S1 con cuota repercutida mayor que cero + BI al 10% de S3 (solo las líneas con cuota repercutida mayor que cero) en el LRFE con fecha de operación y en su defecto fecha de expedición en el periodo autoliquidado
LRFE
F1 F2 F4
S1 S3 (CON CUOTA)
01 11 12 13 15

00005
10%
Tipo 10%
LRFE

00006
CI AL 10%
CI al 10% de S1 con cuota repercutida mayor que cero + CI al 10% de S3 (solo líneas con cuota repercutida mayor que cero)
LRFE
F1 F2 F4
S1 S3 (CON CUOTA)
01 11 12 13 15

00007
BI AL 21%
Tipo operación S1 y S3. Sumar las BI al 21% de S1 con cuota repercutida de mayor que cero + BI al 21% de S3 (solo las líneas con cuota repercutida mayor que cero) en el LRFE con fecha de operación y en su defecto fecha de expedición en el periodo autoliquidado
LRFE
F1 F2 F4
S1 S3(CON CUOTA)
01 11 12 13 15

00008
21%
Tipo 21%
LRFE

00009
CI AL 21%
CI al 21% de S1 con cuota repercutida mayor que cero + CI al 21% de S3 (solo líneas con cuota repercutida mayor que cero)
LRFE
F1 F2 F4
S1 S3 (CON CUOTA)
01 11 12 13 15

00010
BI AIB BIENES Y SERVICIOS
LRFR . Sumar BI facturas con clave 09, solo líneas con cuota soportada mayor que cero y periodo consignado en la factura igual al periodo de autoliquidación  
LRFR
F1 F3 F4 F6
SIN DESGLOSE ISP
09

00011
CI AIB BIENES Y SERVICIOS
LRFR. CI facturas con clave 09, solo líneas con cuota soportada mayor que cero y periodo consignado en la factura igual al periodo de autoliquidación.
LRFR
F1 F3 F4 F6
SIN DESGLOSE ISP
09

00012
BI OTRAS OPERACIONES ISP
LRFR. Sumar BI facturas con desglose ISP con línea de cuota soportada mayor que cero y periodo consignado en la factura igual al periodo de autoliquidación. Excepto facturas última línea casilla 14
LRFR
F1 F2 F4 F6
CON DESGLOSE ISP
01 12

00013
CI OTRAS OPERACIONES ISP
LRFR. CI facturas con desglose ISP con línea de cuota soportada mayor que cero y periodo consignado en la factura igual al periodo de autoliquidación. Excepto facturas última línea casilla 15
LRFR
F1 F2 F4 F6
CON DESGLOSE ISP
01 12

00014
BI. MODIFICACIONES BASES Y CUOTAS
En S1 + S3 (solo las líneas con cuota) del LRFE con periodo de liquidación declarado igual al periodo autoliquidado, sumar las BI de facturas emitidas rectificativas (R1 R2 R3 R4 R5) neteadas con el signo que corresponda, sin tener en cuenta las facturas rectificativas por sustitución con base rectificada distinto de cero o nulo
LRFE
R1 R2 R3 R4 R5
S1 S3 (CON CUOTA)
01 11 12 13 15

Facturas rectificativas en LRFE por sustitución con base rectificada distinto de cero/nulo: en S1 BI de facturas emitidas rectificativas (R1 R2 R3 R4 R5) menos base rectificada contenida en cada una de ellas. En S3 (solo las líneas con cuota) con periodo de liquidación declarado igual al periodo autoliquidado, sumar las BI de facturas emitidas rectificativas (R1 R2 R3 R4 R5) menos base rectificada contenida en cada una de ellas de las líneas con cuota
LRFE
R1 R2 R3 R4 R5
S1 S3 (CON CUOTA)
01 11 12 13 15

En S1 + S3 (solo las líneas con cuota) del LRFE con fecha de operación y en su defecto fecha de expedición en el periodo autoliquidado, sumar las BI facturas emitidas no identificadas con R y que tengan cuota negativa con fecha de operación o en su defecto fecha de expedición en el periodo autoliquidado
LRFE
F1 F2 F4 <0
S1 S3 (CON CUOTA)
01 11 12 13 15

Sumar las BI en el tipo de operación S1, clave 02, en comunicación A5 y A6 (tienen signo negativo) y las BI/ Importe exento con clave 02 en comunicación A4 (convertir a signo negativo) registradas todas ellas en el LRFE con periodo correspondiente al de la autoliquidación
LRFE
COMUNICACIÓN A5 A6 (TIENEN SIGNO NEGATIVO) COMUNICACIÓN A4 (CONVERTIR A SIGNO NEGATIVO)
S1
02

Sumar las BI de las facturas recibidas identificadas como rectificativas (R1 A R5) y clave RREE AIB que se han registrado con cuota soportada y periodo de liquidación consignado en la factura igual al periodo de autoliquidación, sin tener en cuenta las facturas rectificativas por sustitución con base rectificada distinto de cero o nulo
LRFR
R1 R2 R3 R4 R5
SIN DESGLOSE ISP
09

Facturas rectificativas por sustitución con base rectificada distinto de cero/nulo del LRFR con periodo de liquidación declarado igual al periodo autoliquidado, sumar las BI de las facturas recibidas identificadas como rectificativas (R1 A R5) y clave RREE AIB menos base rectificada contenida en cada una de ellas
LRFR
R1 R2 R3 R4 R5
SIN DESGLOSE ISP
09

Sumar las BI de las facturas recibidas identificadas como rectificativas (R1 A R5) y con desglose ISP con cuota soportado que se han registrado con periodo de liquidación consignado en la factura igual al periodo de autoliquidación, sin tener en cuenta las facturas rectificativas por sustitución con base rectificada distinto de cero o nulo
LRFR
R1 R2 R3 R4 R5
CON DESGLOSE ISP
01 12

Facturas rectificativas por sustitución con base rectificada distinto de cero/nulo del LRFR con periodo de liquidación declarado igual al periodo autoliquidado, sumar las BI de las facturas recibidas identificadas como rectificativas (R1 A R5) y con desglose ISP menos base rectificada contenida en cada una de ellas
LRFR
R1 R2 R3 R4 R5
CON DESGLOSE ISP
01 12

Sumar las BI de AIB con cuota soportada negativa distintas de rectificativas que se han registrado con periodo de liquidación consignado en la factura igual al periodo de autoliquidación
LRFR
F1 F3 F4 F6 <0
SIN DESGLOSE ISP
09

Sumar las BI de operaciones ISP con cuota soportada negativa distintas de rectificativas que se han registrado con periodo de liquidación consignado en la factura igual al periodo de autoliquidación
LRFR
F1 F3 F4 F6 <0
CON DESGLOSE ISP
01 12

Sumar las BI de las facturas recibidas contrastadas / parcialmente contrastadas no identificadas como R con cuota soportada positiva que se han emitido como rectificativas y registrado con periodo consignado en la factura igual al periodo de autoliquidación
LRFR
F1 F3 F4 F6 > 0
CON DESGLOSE ISP
01 12

00015
CI MODIFICACIONES BASES Y CUOTAS
CI facturas emitidas del LRFE con periodo de liquidación declarado igual al periodo autoliquidado, sumar las CI de facturas emitidas rectificativas (R1 R2 R3 R4 R5) neteadas con el signo que corresponda, sin tener en cuenta las facturas rectificativas por sustitución con base rectificada distinto de cero o nulo
LRFE
R1 R2 R3 R4 R5
S1 S3 (CON CUOTA)
01 11 12 13 15

Facturas rectificativas por sustitución con base rectificada distinto de cero/nulo, en LRFE con periodo de liquidación declarado igual al periodo autoliquidado, sumar en S1 las CI de facturas emitidas rectificativas (R1 R2 R3 R4 R5) menos la cuota rectificada contenida en cada una de ellas y en S3 las CI de facturas emitidas rectificativas (R1 R2 R3 R4 R5) menos la cuota rectificada contenida en cada una de ellas de las líneas con cuota
LRFE
R1 R2 R3 R4 R5
S1 S3 (CON CUOTA)
01 11 12 13 15

Tipo operación S1 + S3 (solo las líneas con cuota) en el LRFE con fecha de operación o en su defecto fecha de expedición, en el periodo autoliquidado sumar las CI facturas emitidas no identificadas con R que tengan cuota negativa en el LRFE con fecha de operación y en su defecto fecha de expedición en el periodo autoliquidado
LRFE
F1 F2 F4 <0
S1 S3 (CON CUOTA)
01 11 12 13 15

Sumar las CI en el tipo de operación S1, clave 02, en comunicación A5 Y A6 (tienen signo negativo) y las CI con clave 02 en comunicación A4 (calcular poniendo signo negativo BI/importe exento * 0,21) registradas todas ellas en el LRFE con periodo correspondiente al de la autoliquidación
LRFE
COMUNICACIÓN A5 A6 (TIENEN SIGNO NEGATIVO) COMUNICACIÓN A4 (PONER CON SIGNO NEGATIVO BI*0,21)
S1
02

Sumar las CI soportadas de las facturas recibidas identificadas como rectificativas (R1 a R5) en caso de AIB que se han registrado con periodo de liquidación consignado en la factura igual al periodo de autoliquidación, sin tener en cuenta las facturas rectificativas por sustitución con base rectificada distinto de cero o nulo
LRFR
R1 R2 R3 R4 R5
SIN DESGLOSE ISP
09

Facturas rectificativas por sustitución con base rectificada distinto de cero/nulo en LRFR: sumar las CI soportadas de las facturas recibidas identificadas como rectificativas (R1 a R5) en caso de AIB que se han registrado con periodo de liquidación consignado en la factura igual al periodo de autoliquidación menos la cuota rectificada contenida en cada una de ellas
LRFR
R1 R2 R3 R4 R5
SIN DESGLOSE ISP
09

Sumar las CI soportadas de las facturas recibidas identificadas como rectificativas (R1 a R5) en caso de ISP que se han registrado con periodo de liquidación consignado en la factura igual al periodo de autoliquidación, sin tener en cuenta las facturas rectificativas por sustitución con base rectificada distinto de cero o nulo
LRFR
R1 R2 R3 R4 R5
CON DESGLOSE ISP
01 12

Facturas rectificativas por sustitución con base rectificada distinto de cero/nulo en LRFR :sumar las CI soportadas de las facturas recibidas identificadas como rectificativas (R1 a R5) en caso de ISP que se han registrado con periodo de liquidación consignado en la factura igual al periodo de autoliquidación menos la cuota rectificada contenida en cada una de ellas
LRFR
R1 R2 R3 R4 R5
CON DESGLOSE ISP
01 12

Sumar las CI de AIB con cuota soportada negativa que se han registrado con periodo de liquidación consignado en la factura igual al periodo de autoliquidación
LRFR
F1 F3 F4 F6 <0
SIN DESGLOSE ISP
09

Sumar las CI de operaciones ISP con cuota soportada negativa que se han registrado con periodo de liquidación consignado en la factura igual al periodo de autoliquidación
LRFR
F1 F3 F4 F6 <0
CON DESGLOSE ISP
01 12

Sumar las CI soportadas de las facturas recibidas contrastadas /parcialmente contrastadas no identificadas como R con cuotas soportada positiva (distinto de cero/nulo) que se han emitido como rectificativas y registrado con periodo consignado en la factura igual al periodo de autoliquidación
LRFR
F1 F3 F4 F6 > 0
CON DESGLOSE ISP
01 12

00016
BI CON RECARGO AL 5,2 %
Tipo de operación S1 con cuota repercutida mayor que cero + S3 (solo las líneas con cuota repercutida mayor que cero) en el LRFE con fecha de operación y en su defecto fecha de expedición en el periodo autoliquidado sumar la BI recargo facturas en el LRFE con línea de cuota repercutida mayor que cero al 5,2%
LRFE
F1 F2 F4
S1 S3 (CON CUOTA)
01 11 12 13 15

00017
5,2%
TIPO 5,2%

00018
CI RECARGO AL 5,2%
CI recargo facturas en el LRFE con fecha de operación y en su defecto fecha de expedición en el periodo autoliquidado al 5,2% de S1 con cuota repercutida mayor que cero + S3 (solo líneas con cuota repercutida mayor que cero)
LRFE
F1 F2 F4
S1 S3 (CON CUOTA)
01 11 12 13 15

00019
BI CON RECARGO AL 1,4 %
Tipo de operación S1 con cuota repercutida mayor que cero + S3 (solo las líneas con cuota repercutida mayor que cero) en el LRFE con fecha de operación y en su defecto fecha de expedición en el periodo autoliquidado sumar las BI recargo facturas en el LRFE con línea de cuota repercutida mayor que cero al 1,4%
LRFE
F1 F2 F4
S1 S3 (CON CUOTA)
01 11 12 13 15

00020
1,4%
TIPO 1,4%

00021
CI RECARGO AL 1,4%
CI recargo facturas en el LRFE con fecha de operación y en su defecto fecha de expedición en el periodo autoliquidado al 1,4% de S1 con cuota repercutida mayor que cero + S3 (solo líneas con cuota repercutida mayor que cero)
LRFE
F1 F2 F4
S1 S3 (CON CUOTA)
01 11 12 13 15

00022
BI CON RECARGO AL 0,5 %
Tipo de operación S1 con cuota repercutida mayor que cero + S3 (solo las líneas con cuota repercutida mayor que cero) en el LRFE con fecha de operación o en su defecto fecha de expedición en el periodo autoliquidado, sumar las BI recargo facturas en el LRFE con línea de cuota repercutida mayor que cero al 0,5%
LRFE
F1 F2 F4
S1 S3 (CON CUOTA)
01 11 12 13 15

00023
0,5%
TIPO 0,5%

00024
CI RECARGO AL 0,5%
CI recargo facturas en el LRFE con fecha de operación y en su defecto fecha de expedición en el periodo autoliquidado al 0,5% de S1 con cuota repercutida mayor que cero + S3 (solo líneas con cuota repercutida mayor que cero)
LRFE
F1 F2 F4
S1 S3 (CON CUOTA)
01 11 12 13 15

00025
BI MODIFICACIONES BASES Y CUOTAS RECARGO EQUIVALENCIA
Sumar BI de líneas con recargo S1 + S3 (solo las líneas con cuota ) emitidas del LRFE con periodo de liquidación declarado igual al periodo autoliquidado, sumar las BI de recargo de facturas emitidas rectificativas (R1 R2 R3 R4 R5) neteadas con el signo que corresponda, sin tener en cuenta las facturas rectificativas por sustitución con base rectificada distinto de cero o nulo
LRFE
R1 R2 R3 R4 R5
S1 S3 (CON CUOTA)
01 11 12 13 15

Facturas rectificativas por sustitución con base rectificada distinto de cero/nulo en S1 + S3 (solo las líneas con cuota) del LRFE con periodo de liquidación declarado igual al periodo autoliquidado, sumar las BI de líneas con recargo de facturas emitidas rectificativas (R1 R2 R3 R4 R5) menos base rectificada contenida en cada una de ellas
LRFE
R1 R2 R3 R4 R5
S1 S3 (CON CUOTA)
01 11 12 13 15

En S1 + S3 (solo las líneas con cuota) emitidas del LRFE con fecha de operación y en su defecto fecha de expedición en el periodo autoliquidado sumar BI de líneas con cuota recargo negativa en el LRFE
LRFE
F1 F2 F4 < 0
S1 S3 (CON CUOTA)
01 11 12 13 15

00026
CI MODIFICACIONES BASES Y CUOTAS RECARGO EQUIVALENCIA
CI recargo facturas emitidas del LRFE con periodo de liquidación declarado igual al periodo autoliquidado, sumar las CI de recargo de facturas emitidas rectificativas (R1 R2 R3 R4 R5) neteadas con el signo que corresponda sin tener en cuenta las facturas rectificativas por sustitución con base rectificada distinto de cero o nulo
LRFE
R1 R2 R3 R4 R5
S1 S3 (CON CUOTA)
01 11 12 13 15

Facturas rectificativas por sustitución con base rectificada distinto de cero/nulo, en LRFE con periodo de liquidación declarado igual al periodo autoliquidado, sumar las CI de recargo de facturas emitidas rectificativas (R1 R2 R3 R4 R5) menos cuotas rectificadas correspondientes
LRFE
R1 R2 R3 R4 R5
S1 S3 (CON CUOTA)
01 11 12 13 15

En S1 + S3 (solo las líneas con cuota) emitidas del LRFE con fecha de operación y en su defecto fecha de expedición en el periodo autoliquidado sumar CI de líneas con cuota negativa de recargo en facturas
LRFE
F1 F2 F4 < 0
S1 S3 (CON CUOTA)
01 11 12 13 15

00027
CUOTA TOTAL
Sumatorio cuotas casillas anteriores

Generar PDF
Cerrar
La generación del PDF puede tardar varios minutos dependiendo de la cantidad de información.
Seleccione la información que desee incluir en el PDF:
Sólo la página actual

Todas las páginas del índice

Puede cancelar la generación del PDF en cualquier momento.

CancelarContinuar

Generar PDF
[AnteriorIVA deducible](/Sede/iva/pre-303/2020/iva-deducible.html)[SiguienteOperaciones en el ejercicio](/Sede/iva/pre-303/2020/operaciones-ejercicio.html)

Página actualizada: 13/octubre/2026
