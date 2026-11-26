<!-- Fuente: https://sede.agenciatributaria.gob.es/Sede/iva/pre-303/2026/operaciones-ejercicio.html -->

Agencia Tributaria: 2026 - Operaciones en el ejercicio

 [Saltar al contenido principal](#acc-main) 

 [Logotipo del Gobierno de EspañaLogotipo Organismo](/Sede/inicio.html)

 Menú móvil[Menu](#)

[Inicio](/Sede/inicio.html)
[IVA](/Sede/iva.html)
[PRE 303 (Servicio ayuda modelo 303)](/Sede/iva/pre-303.html)

 2026
[Saltar índice de la información](#js-nombre-canal-paginado)ÍndiceÍndice:
[IVA devengado](/Sede/iva/pre-303/2026.html)
[IVA deducible](/Sede/iva/pre-303/2026/iva-deducible.html)
[Información adicional](/Sede/iva/pre-303/2026/informacion-adicional.html)
Operaciones en el ejercicio

Operaciones en el ejercicio
Los campos incluidos en tipo de factura, tipo de operación y clave de régimen especial se pueden consultar en el documento "[Bloques Funcionales](/static_files/Sede/Procedimiento_ayuda/G417/FicherosSuministros/V_1_1/DR_v1.1.xls)"

CASILLA
LLAA 
DATOS SII 
LR
TIPO FACTURA
TIPO OPERACIÓN
CLAVE RREE 

80
OPERACIONES EN RÉGIMEN GENERAL. SUMATORIO CASILLAS 150, 01, 04, 07 Y 14 (LRFE) DE TODOS LOS PERIODOS
BI de operaciones con clave 01, 09, 11, 12, 13 ,15 y 16 y con fecha de operación y en su defecto fecha de expedición en la factura correspondientes al ejercicio.
LRFE
F1 F2 F4
S1 S3 (CON CUOTA)
01 09 11 12 13 15 16

BI de operaciones con clave 01, 09, 11, 12, 13 ,15 y 16 y con período de liquidación en la factura correspondientes al ejercicio y sin tener en cuenta las facturas rectificativas por sustitución con base rectificada distinto de cero o nulo.
LRFE
R1 R2 R3 R4 R5
S1 S3 (CON CUOTA)
01 09 11 12 13 15 16

Facturas rectificativas por sustitución con base rectificada distinto de cero/nulo BI de operaciones con clave 01, 09, 11, 12, 13 ,15 y 16 y con periodo de liquidación en la factura correspondientes al ejercicio menos la base rectificada contenida en cada una de ellas.
LRFE
R1 R2 R3 R4 R5
S1 S3 (CON CUOTA)
01 09 11 12 13 15 16

En S1 + S3 (solo las líneas con cuota) del LRFE con fecha de operación y en su defecto fecha de expedición en el periodo autoliquidado, sumar las BI facturas emitidas no identificadas con R y que tengan cuota negativa con FO/FEX en el periodo autoliquidado.
LRFE
F1 F2 F4 <0
S1 S3 (CON CUOTA)
01 09 11 12 13 15 16

Sumar las BI en el tipo de operación S1, clave 02, en comunicación A5 y A6 (tienen signo negativo) y las BI/ Importe exento con clave 02 en comunicación A4 (convertir a signo negativo) registradas todas ellas en el LRFE con periodo correspondiente al de la autoliquidación.
LRFE
COMUNICACIÓN A5 A6 (TIENEN SIGNO NEGATIVO) COMUNICACIÓN A4 (CONVERTIR A SIGNO NEGATIVO)

S1

02

81
NO CONSTA

93
ENTREGAS INTRACOMUNITARIAS EXENTAS. SUMATORIO CASILLA 59 DE TODOS LOS PERIODOS
BI/Importe exento facturas LRFE operaciones sujetas y exentas por causa art. 25 (E5), con fecha de operación y en su defecto fecha de expedición en la factura correspondiente al periodo de autoliquidación.
LRFE
F1 F2 F4
ENTREGAS Y PRESTACIONES. EXENTA
01 15 16

BI/Importe exento facturas LRFE operaciones sujetas y exentas por causa art. 25 (E5) con periodo de liquidación en la factura correspondiente al periodo de autoliquidación, sin tener en cuenta las facturas rectificativas por sustitución con base rectificada distinto de cero o nulo.
LRFE
R1 R2 R3 R4 R5
ENTREGAS Y PRESTACIONES. EXENTA
01 15 16

Facturas rectificativas por sustitución con base rectificada distinto de cero/nulo. BI/Importe exento facturas LRFE operaciones sujetas y exentas por causa art. 25 (E5) con periodo de liquidación en la factura correspondiente al periodo de autoliquidación menos la base rectificada contenida en cada una de ellas.
LRFE
R1 R2 R3 R4 R5
ENTREGAS Y PRESTACIONES. EXENTA
01 15 16

BI/Importe exento facturas del LRFE entregas de bienes sujetas y exentas con motivo exención "otros" (E6) o sin poner nada en la casilla causa cuando el NIF del destinatario sea un NIF IVA válido, con fecha de operación y en su defecto fecha de expedición en la factura correspondiente al período de autoliquidación.
LRFE
F1 F2 F4
ENTREGAS DE BIENES. EXENTA
01 15 16

BI/Importe exento facturas del LRFE entregas de bienes sujetas y exentas con motivo exención "otros" (E6) o sin poner nada en la casilla causa cuando el NIF del destinatario sea un NIF IVA válido, con periodo de liquidación en la factura correspondiente al periodo de autoliquidación, sin tener en cuenta las facturas rectificativas por sustitución con base rectificada distinto de cero o nulo.
LRFE
R1 R2 R3 R4 R5
ENTREGAS DE BIENES. EXENTA
01 15 16

Facturas rectificativas por sustitución con base rectificada distinto de cero/nulo. BI/Importe exento facturas del LRFE entregas de bienes sujetas y exentas con motivo exención "otros" (E6) o sin poner nada en la casilla causa cuando el NIF del destinatario sea un NIF IVA válido, con periodo de liquidación en la factura correspondiente al periodo de autoliquidación menos la base rectificada contenida en cada una de ellas.
LRFE
R1 R2 R3 R4 R5
ENTREGAS DE BIENES. EXENTA
01 15 16

Importe no sujeto por reglas de localización de prestaciones de servicios en las que el NIF del destinatario sea un NIF IVA válido, con fecha de operación y en su defecto fecha de expedición en la factura correspondiente al periodo de autoliquidación.
LRFE
F1 F2 F4
PRESTACIONES DE SERVICIOS. NO SUJETA
01 11 12 13 15 16 09

Importe no sujeto por reglas de localización de prestaciones de servicios en las que el NIF del destinatario sea un NIF IVA válido, con periodo de liquidación en la factura correspondiente al periodo de autoliquidación, sin tener en cuenta las facturas rectificativas por sustitución con base rectificada distinto de cero o nulo.
LRFE
R1 R2 R3 R4 R5
PRESTACIONES DE SERVICIOS. NO SUJETA
01 11 12 13 15 16 09

Facturas rectificativas por sustitución con base rectificada distinto de cero/nulo. Importe no sujeto por reglas de localización de prestaciones de servicios en las que el NIF del destinatario sea un NIF IVA válido, con periodo de liquidación en la factura correspondiente al periodo de autoliquidación menos la base rectificada contenida en cada una de ellas.
LRFE
R1 R2 R3 R4 R5
PRESTACIONES DE SERVICIOS. NO SUJETA
01 11 12 13 15 16 09

94
EXPORTACIONES Y OTRAS OPERACIONES EXENTAS CON DERECHO A DEDUCCIÓN. SUMATORIO CASILLA 60 DE TODOS LOS PERIODOS
BI/Importe exento de las facturas registradas como sujetas y exentas identificadas con artículo 21 (E2), 22 (E3), 23 y 24 (E4) con fecha de operación y en su defecto fecha de expedición en la factura correspondiente al período de autoliquidación.
LRFE
F1 F2 F4
ENTREGAS Y PRESTACIONES. EXENTA
01 02 05 16

BI/Importe exento de las facturas registradas como sujetas y exentas identificadas con artículo 21 (E2), 22 (E3), 23 y 24 (E4) con periodo de liquidación en la factura correspondiente al periodo de autoliquidaciónación y sin tener en cuenta las facturas rectificativas por sustitución con base rectificada distinto de cero o nulo.
LRFE
R1 R2 R3 R4 R5
ENTREGAS Y PRESTACIONES. EXENTA
01 02 05 16

Facturas rectificativas por sustitución con base rectificada distinto de cero/nulo BI/Importe exento de las facturas registradas como sujetas y exentas identificadas con artículo 21 (E2), 22 (E3), 23 y 24 (E4) con periodo de liquidación en la factura correspondiente al periodo de autoliquidación menos la base rectificada contenida en cada una de ellas.
LRFE
R1 R2 R3 R4 R5
ENTREGAS Y PRESTACIONES. EXENTA
01 02 05 16

BI/Importe exento facturas del LRFE entregas de bienes sujetas y exentas con motivo exención "otros" (E6) o sin poner nada en la casilla causa, con fecha de operación y en su defecto fecha de expedición en la factura correspondiente al periodo de autoliquidación. No se incluyen las operaciones de la casilla 59.
LRFE
F1 F2 F4
ENTREGAS DE BIENES. EXENTA
02 16

BI/Importe exento facturas del LRFE entregas de bienes sujetas y exentas con motivo exención "otros" (E6) o sin poner nada en la casilla causa, con periodo de liquidación en la factura correspondiente al periodo de autoliquidación, sin tener en cuenta las facturas rectificativas por sustitución con base rectificada distinto de cero o nulo. No se incluyen las operaciones de la casilla 59.
LRFE
R1 R2 R3 R4 R5
ENTREGAS DE BIENES. EXENTA
02 16

Facturas rectificativas por sustitución con base rectificada distinto de cero/nulo. BI/Importe exento facturas del LRFE entregas de bienes sujetas y exentas con motivo exención "otros" (E6) o sin poner nada en la casilla causa, con periodo de liquidación en la factura correspondiente al periodo de autoliquidación menos la base rectificada contenida en cada una de ellas. No se incluyen las operaciones de la casilla 59.
LRFE
R1 R2 R3 R4 R5
ENTREGAS DE BIENES. EXENTA
02 16

BI de facturas con clave 02 que consten como sujetas y no exentas, S1, en los tipos de comunicación A5 y A6 convirtiendo a signo positivo, y periodo consignado en la factura igual al periodo de autoliquidación.
LRFE
COMUNICACIÓN A5 A6
ENTREGAS Y PRESTACIONES DE SERVICIOS. S1
02 16

BI de facturas con clave 02 que consten como sujetas y no exentas, S1, en el tipo de comunicación A4 donde se tomará la parte identificada con clave 02 importe exento con signo positivo, y periodo consignado en la factura igual al periodo de autoliquidación.
LRFE
COMUNICACIÓN A4
ENTREGAS Y PRESTACIONES DE SERVICIOS. S1
02 16

83

OPERACIONES EXENTAS SIN DERECHO A DEDUCIR

BI/importe exento facturas del LRFE operaciones sujetas y exentas con motivo exención E1, "otros" (E6) o sin poner nada en la casilla causa, con fecha de operación y en su defecto fecha de expedición en la factura corrrespondiente al periodo de autoliquidación.
LRFE
F1 F2 F3 F4
E1 E6 " "
01 15 16

BI/importe exento facturas del LRFE operaciones sujetas y exentas con motivo exención E1, "otros" (E6) o sin poner nada en la casilla causa, con periodo de liquidación en la factura corrrespondiente al periodo de autoliquidación, sin tener en cuenta las facturas rectificativas por sustitución con base rectificada distinto de cero o nulo.
LRFE
R1 R2 R3 R4 R5
E1 E6 " "
01 15 16

Facturas rectificativas por sustitución con base rectificada distinto de cero/nulo, BI/importe exento facturas del LRFE operaciones sujetas y exentas con motivo exención E1, "otros" (E6) o sin poner nada en la casilla causa, con periodo de liquidación en la factura corrrespondiente al periodo de autoliquidación menos la base rectificada contenida en cada una de ellas
LRFE
R1 R2 R3 R4 R5
E1 E6 " "
01 15 16

84

OPERACIONES NO SUJETAS POR REGLAS DE LOCALIZACIÓN EXCEPTO LAS INCLUIDAS EN LA CASILLA 126. SUMATORIO CASILLA 120

Importe no sujeto de facturas en LRFE marcadas como no sujetas, con fecha de operación y en su defecto fecha de expedición en la factura correspondiente al período de autoliquidación. No tener en cuenta las correspondientes prestaciones de servicios incluidas en casilla 59.
LRFE
F1 F2 F4
NO SUJETA (REGLAS LOCALIZACIÓN)
01 08 11 12 13 15 03 05 09

Importe no sujeto de facturas en LRFE marcadas como no sujetas, con periodo de liquidación en la factura correspondiente al periodo de autoliquidación, sin tener en cuenta las facturas rectificativas por sustitución con base rectificada distinto de cero o nulo. No tener en cuenta las correspondientes prestaciones de servicios incluidas en casilla 59.
LRFE
R1 R2 R3 R4 R5
NO SUJETA (REGLAS LOCALIZACIÓN)
01 08 11 12 13 15 03 05 09 

Facturas rectificativas por sustitución con base rectificada distinto de cero/nulo importe no sujeto de facturas en LRFE marcadas como no sujetas, con periodo de liquidación en la factura correspondiente al período de autoliquidación menos la base rectificada contenida en cada una de ellas. No tener en cuenta las correspondientes prestaciones de servicios incluidas en casilla 59.
LRFE
R1 R2 R3 R4 R5
NO SUJETA (REGLAS LOCALIZACIÓN)
01 08 11 12 13 15 03 05 09

125
OPERACIONES SUJETAS CON INVERSIÓN DEL SUJETO PASIVO. SUMATORIO CASILLA 122
BI facturas S2 + S3 (de las S3 solo las líneas sin cuota) en el LRFE, con fecha de operación y en su defecto fecha de expedición en la factura correspondiente al período de autoliquidación.
LRFE
F1 F3
S2 S3 (SIN CUOTA)
01 11 12 13 15 09

BI facturas S2 + S3 (de las S3 solo las líneas sin cuota) en el LRFE, con periodo de liquidación en la factura correspondiente al periodo de autoliquidación, sin tener en cuenta las facturas rectificativas por sustitución con base rectificada distinto de cero o nulo.
LRFE
R1 R2 R3 R4
S2 S3 (SIN CUOTA)
01 11 12 13 15 09

Facturas rectificativas por sustitución con base rectificada distinto de cero/nulo: (S2) BI facturas S2 con periodo de liquidación en la factura correspondiente al periodo de autoliquidación menos la base rectificada contenida en cada una de ellas. (S3) BI integra facturas S3 (de las S3 solo las líneas sin cuota) en el LRFE, con periodo de liquidación en la factura correspondiente al período de autoliquidación cuyas líneas no tengan cuotas.
LRFE
R1 R2 R3 R4
S2 S3 (SIN CUOTA)
01 11 12 13 15 09

126
OSS. OPERACIONES NO SUJETAS POR REGLAS DE LOCALIZACIÓN ACOGIDAS A LA OSS. SUMATORIO CASILLA 123
Importe no sujeto de facturas en LRFE marcadas como no sujetas, con fecha de operación y en su defecto fecha de expedición en la factura correspondiente al período de autoliquidación.
LRFE
F1 F2 F4
NO SUJETA (REGLAS LOCALIZACIÓN)
17

Importe no sujeto de facturas en LRFE marcadas como no sujetas, con periodo de liquidación en la factura correspondiente al periodo de autoliquidación, sin tener en cuenta las facturas rectificativas por sustitución con base rectificada distinto de cero o nulo.
LRFE
R1 R2 R3 R4 R5
NO SUJETA (REGLAS LOCALIZACIÓN)
17

Facturas rectificativas por sustitución con base rectificada distinto de cero/nulo importe no sujeto de facturas en LRFE marcadas como no sujetas, con periodo de liquidación en la factura correspondiente al periodo de autoliquidación menos la base rectificada contenida en cada una de ellas.
LRFE
R1 R2 R3 R4 R5
NO SUJETA (REGLAS LOCALIZACIÓN)
17

127
OSS. OPERACIONES SUJETAS Y ACOGIDAS A LA OSS. SUMATORIO CASILLA 124
Tipo operación S1. Sumar las BI de S1 con cuota repercutida mayor que cero en el LRFE con fecha de operación y en su defecto fecha de expedición en el período autoliquidado.
LRFE
F1 F2 F4
S1
17

En S1 del LRFE con periodo de liquidación declarado igual al periodo autoliquidado, sumar las BI de facturas emitidas rectificativas (R1 R2 R3 R4 R5) neteadas con el signo que corresponda, sin tener en cuenta las facturas rectificativas por sustitución con base rectificada distinto de cero o nulo.
LRFE
R1 R2 R3 R4 R5
S1
17

Facturas rectificativas por sustitución con base rectificada distinto de cero/nulo en S1 del LRFE con período de liquidación declarado igual al periodo autoliquidado, sumar las BI de facturas emitidas rectificativas (R1 R2 R3 R4 R5) menos base rectificada contenida en cada una de ellas.
LRFE
R1 R2 R3 R4 R5
S1
17

En S1 del LRFE, sumar las BI facturas emitidas no identificadas con R (rectificativas) y que tengan cuota negativa con fecha de operación y en su defecto fecha de expedición en la factura corrrespondiente al periodo de autoliquidación  
LRFE
F1 F2 F4 <0
S1
17

128
NO CONSTA
NO CONSTA

86
NO CONSTA
NO CONSTA

95
NO CONSTA
NO CONSTA

96
NO CONSTA
NO CONSTA

97
OPERACIONES REBU*
Facturas que contengan operaciones con clave 03 y con fecha de operación y en su defecto fecha de expedición en la factura correspondientes al ejercicio, calcular importe total menos cuota repercutida.
LRFE
F1 F2 F4 AJ
S1
03

Facturas que contengan operaciones con clave 03 y con periodo de liquidación en la factura correspondientes al ejercicio y sin tener en cuenta las facturas rectificativas por sustitución con base rectificada distinto de cero o nulo, calcular importe total menos cuota repercutida, calcular importe total menos cuota repercutida.
LRFE
R1 R2 R3 R4 R5
S1
03

Facturas rectificativas por sustitución con base rectificada distinto de cero/nulo que contengan operaciones con clave 03 y con periodo de liquidación en la factura correspondientes al ejercicio calcular menos la base rectificada contenida en cada una de ellas, calcular importe total menos cuota repercutida.
LRFE
 R1 R2 R3 R4 R5
S1
03

98
OPERACIONES REGIMEN* ESPECIAL AGENCIAS VIAJES
Facturas que contengan operaciones con clave 05 y con fecha de operación y en su defecto fecha de expedición en la factura correspondientes al ejercicio calcular importe total menos cuota repercutida.
LRFE
F1 F2 F4 AJ
S1
05

Facturas que contengan operaciones con clave 05 y con periodo de liquidación en la factura correspondientes al ejercicio y sin tener en cuenta las facturas rectificativas por sustitución con base rectificada distinto de cero o nulo calcular importe total menos cuota repercutida.
LRFE
R1 R2 R3 R4 R5
S1
05

Facturas rectificativas por sustitución con base rectificada distinto de cero/nulo que contengan operaciones con clave 05 y con periodo de liquidación en la factura correspondientes al ejercicio menos la base rectificada contenida en cada una de ellas calcular importe total menos cuota repercutida.
LRFE
R1 R2 R3 R4 R5
S1
05

79
NO CONSTA
NO CONSTA

99
NO CONSTA
NO CONSTA

88
TOTAL VOLUMEN DE OPERACIONES
SUMATORIO

Generar PDF
Cerrar
La generación del PDF puede tardar varios minutos dependiendo de la cantidad de información.
Seleccione la información que desee incluir en el PDF:
Sólo la página actual

Todas las páginas del índice

Puede cancelar la generación del PDF en cualquier momento.

CancelarContinuar

Generar PDF
[AnteriorInformación adicional](/Sede/iva/pre-303/2026/informacion-adicional.html)

Página actualizada: 13/octubre/2026
