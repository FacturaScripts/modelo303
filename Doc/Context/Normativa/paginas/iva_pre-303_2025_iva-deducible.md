<!-- Fuente: https://sede.agenciatributaria.gob.es/Sede/iva/pre-303/2025/iva-deducible.html -->

Agencia Tributaria: 2025 - IVA deducible

 [Saltar al contenido principal](#acc-main) 

 [Logotipo del Gobierno de EspañaLogotipo Organismo](/Sede/inicio.html)

 Menú móvil[Menu](#)

[Inicio](/Sede/inicio.html)
[IVA](/Sede/iva.html)
[PRE 303 (Servicio ayuda modelo 303)](/Sede/iva/pre-303.html)

 2025
[Saltar índice de la información](#js-nombre-canal-paginado)ÍndiceÍndice:
[IVA devengado](/Sede/iva/pre-303/2025.html)
IVA deducible
[Información adicional](/Sede/iva/pre-303/2025/informacion-adicional.html)
[Operaciones en el ejercicio](/Sede/iva/pre-303/2025/operaciones-ejercicio.html)

IVA deducible
Los campos incluidos en tipo de factura, tipo de operación y clave de régimen especial se pueden consultar en el documento "[Bloques Funcionales](/static_files/Sede/Procedimiento_ayuda/G417/FicherosSuministros/V_1_1/DR_v1.1.xls)"

CASILLA
LLAA 
DATOS SII

 OPERACIÓN
LR 
TIPO FACTURA
TIPO OPERACIÓN
CLAVE RREE
NO INCLUIR: DEDUCIBLE EN PERIODO POSTERIOR
 INCLUIR: DEDUCIBLE EN PERIODO POSTERIOR

28
BI cuotas soportadas operaciones interiores corrientes
SUMA las BI de facturas en el LRFR registradas con línea de cuota deducible/soportada mayor que cero con período consignado en la factura igual al periodo de autoliquidación. Excluir las contrastadas y parcialmente contrastadas cuando la factura expedida es rectificativa. Nota: Si cuota deducible es cero, BI es cero en todo caso.
LRFR
F1 F3 F4 F6
CON/SIN DESGLOSE ISP
01 12
"SI"
"SI" + PERIODO+EJERCICIO DE AUTOLIQUIDACIÓN

29
CI cuotas soportadas operaciones interiores corrientes
SUMAR las cuotas deducible mayor que cero de facturas en el LRFR registradas con periodo consignado en la factura igual al periodo de autoliquidación. Excluir las facturas contrastadas y parcialmente contrastadas cuando la factura expedida es rectificativa.
LRFR
F1 F3 F4 F6
CON/SIN DESGLOSE ISP
01 12
"SI"
"SI" + PERIODO+EJERCICIO DE AUTOLIQUIDACIÓN

30
BI cuotas soportadas operaciones interiores con bienes de inversión
SUMA las BI de facturas en el LRFR registradas con línea de cuota deducible/soportada mayor que cero con periodo consignado en la factura igual al período de autoliquidación. Excluir las contrastadas y parcialmente contrastadas cuando la factura expedida es rectificativa. Nota: Si cuota deducible es cero, BI es cero en todo caso.
LRFR
F1 F3 F4 F6 CON LA MARCA BS INVERSIÓN "SI"
CON/SIN DESGLOSE ISP
01 12
"SI"
"SI" + PERIODO+EJERCICIO DE AUTOLIQUIDACIÓN

31
CI cuotas soportadas operaciones interiores con bienes de inversión
SUMAR las cuotas deducible mayor que cero de facturas en el LRFR registradas con periodo consignado en la factura igual al periodo de autoliquidación. Excluir las contrastadas y parcialmente contrastadas cuando la factura expedida es rectificativa.
LRFR
F1 F3 F4 F6 CON LA MARCA BS INVERSIÓN "SI"
CON/SIN DESGLOSE ISP
01 12
"SI"
"SI" + PERIODO+EJERCICIO DE AUTOLIQUIDACIÓN

32
BI cuotas soportadas en importaciones de bienes corrientes
SUMAR las BI de facturas en el LRFR registradas con línea de cuota deducible/soportada mayor que cero, Tipo factura importaciones DUA y LC con período consignado en la factura igual al período de autoliquidación. Nota: Si cuota deducible es cero, BI es cero en todo caso.
LRFR
F5 LC
SIN DESGLOSE ISP
O1
"SI"
"SI" + PERIODO+EJERCICIO DE AUTOLIQUIDACIÓN

33
CI cuotas soportadas en importaciones de bienes corrientes
SUMAR las CI deducible mayor que cero de facturas registradas tipo factura importaciones DUA y LC con periodo consignado en la factura igual al periodo de autoliquidación. 
LRFR
F5 LC
SIN DESGLOSE ISP
O1
"SI"
"SI" + PERIODO+EJERCICIO DE AUTOLIQUIDACIÓN

34
BI cuotas soportadas en importaciones de bienes de inversión
SUMAR las BI de facturas en el LRFR registradas con línea de cuota deducible/soportada mayor que cero tipo factura importaciones DUA y LC con periodo consignado en la factura igual al periodo de autoliquidación. Nota: Si cuota deducible es cero, BI es cero en todo caso.
LRFR
F5 LC CON LA MARCA BS INVERSIÓN "SI"
SIN DESGLOSE ISP
O1
"SI"
"SI" + PERIODO+EJERCICIO DE AUTOLIQUIDACIÓN

35
CI cuotas soportadas en importaciones de bienes de inversión
SUMAR las CI deducible mayor que cero de facturas registradas tipo factura importaciones DUA y LC con periodo consignado en la factura igual al periodo de autoliquidación.    
LRFR
F5 LC CON LA MARCA BS INVERSIÓN "SI"
SIN DESGLOSE ISP
O1
"SI"
"SI" + PERIODO+EJERCICIO DE AUTOLIQUIDACIÓN

36
BI cuotas soportadas en AIB de bienes y servicios corrientes
SUMAR las BI de facturas en el LRFR registradas con clave 09 con línea de cuota deducible/soportada mayor que cero con periodo consignado en la factura igual al periodo de autoliquidación. Nota: Si cuota deducible es cero, BI es cero en todo caso. Si el tipo es cero hay que añadirlo en IVADevengado por el contribuyente.
LRFR
F1
SIN DESGLOSE ISP
O9
"SI"
"SI" + PERIODO+EJERCICIO DE AUTOLIQUIDACIÓN

37
CI cuotas soportadas en AIB de bienes y servicios corrientes
SUMAR las CI deducible mayor que cero de facturas registradas con clave 09 con periodo consignado en la factura igual al periodo de autoliquidación.
LRFR
F1
SIN DESGLOSE ISP
O9
"SI"
"SI" + PERIODO+EJERCICIO DE AUTOLIQUIDACIÓN

38
BI cuotas soportadas en AIB de bienes de inversión
SUMAR las BI de facturas en el LRFR registradas con clave 09 con línea de cuota deducible/soportada mayor que cero con período consignado en la factura igual al período de autoliquidación. Nota: Si cuota deducible es cero, BI es cero en todo caso. Nota: Si cuota deducible es cero, BI es cero en todo caso.
LRFR
F1 F3 F4 F6 CON LA MARCA BS INVERSIÓN "SI"
SIN DESGLOSE ISP
O9
"SI"
"SI" + PERIODO+EJERCICIO DE AUTOLIQUIDACIÓN

39
CI cuotas soportadas en AIB de bienes de inversión
SUMAR las CI deducible mayor que cero de facturas registradas con clave 09 con periodo consignado en la factura igual al periodo de autoliquidación.
LRFR
F1 F3 F4 F6 CON LA MARCA BS INVERSIÓN "SI"
SIN DESGLOSE ISP
O9
"SI"
"SI" + PERIODO+EJERCICIO DE AUTOLIQUIDACIÓN

40
BI rectificaciones de deducciones
SUMAR las BI de facturas en el LRFR registradas con línea de cuota deducible/soportada distinta de cero/nula con tipo de factura R1 a R5 que se han registrado con periodo consignado en la factura igual al periodo de autoliquidación, sin tener en cuenta las facturas rectificativas por sustitución con base rectificada distinto de cero o nulo.
LRFR
R1 R2 R3 R4 R5
CON/SIN DESGLOSE ISP
01 12
"SI"
"SI" + PERIODO+EJERCICIO DE AUTOLIQUIDACIÓN

Facturas rectificativas por sustitución con base rectificada distinto de cero/nulo sumar las BI de facturas en el LRFR registradas con tipo de factura R1 a R5 que se han registrado con periodo consignado en la factura igual al periodo de autoliquidación menos la base rectificada contenida en cada una de ellas.
LRFR
R1 R2 R3 R4 R5
CON/SIN DESGLOSE ISP
01 12
"SI"
"SI" + PERIODO+EJERCICIO DE AUTOLIQUIDACIÓN

SUMAR las BI de las facturas recibidas identificadas como rectificativas (R1 a R5) y clave 09 que se han registrado con cuota deducible/soportada y periodo de liquidación consignado en la factura igual al periodo de autoliquidación, sin tener en cuenta las facturas rectificativas por sustitución con base rectificada distinto de cero o nulo.
LRFR
R1 R2 R3 R4 R5
SIN DESGLOSE ISP
O9
"SI"
"SI" + PERIODO+EJERCICIO DE AUTOLIQUIDACIÓN

Facturas rectificativas por sustitución con base rectificada distinto de cero/nulo del LRFR con periodo de liquidación declarado igual al periodo autoliquidado, sumar las BI de las facturas recibidas identificadas como rectificativas (R1 a R5) y clave 09 menos base rectificada contenida en cada una de ellas.
LRFR
R1 R2 R3 R4 R5
SIN DESGLOSE ISP
O9
"SI"
"SI" + PERIODO+EJERCICIO DE AUTOLIQUIDACIÓN

SUMAR las BI de facturas que sin estar identificadas con R tienen cuota deducible/soportada negativa, que se han registrado con periodo consignado en la factura igual al periodo de autoliquidación.
LRFR
F1 F3 F4 F6 <0
CON/SIN DESGLOSE ISP
O1 12
"SI"
"SI" + PERIODO+EJERCICIO DE AUTOLIQUIDACIÓN

SUMAR las BI de importaciones con cuota deducible/soportada negativa, que se han registrado con periodo consignado en la factura igual al periodo de autoliquidación.
LRFR
F5 LC<0
SIN DESGLOSE ISP
O1
"SI"
"SI" + PERIODO+EJERCICIO DE AUTOLIQUIDACIÓN

SUMAR las BI de AIB con cuota deducible/soportada negativa, que se han registrado con periodo consignado en la factura igual al periodo de autoliquidación.
LRFR
F1 F3 F4 F6<0
SIN DESGLOSE ISP
O9
"SI"
"SI" + PERIODO+EJERCICIO DE AUTOLIQUIDACIÓN

SUMAR las BI de las facturas recibidas contrastadas/parcialmente contrastadas no identificadas como R con cuota deducible/soportada positiva (distinta de cero/nula) con que se han emitido como rectificativas y registrado con periodo consignado en la factura igual al periodo de autoliquidación.
LRFR
F1 F3 F4 F6>0
CON/SIN DESGLOSE ISP
O1 12
"SI"
"SI" + PERIODO+EJERCICIO DE AUTOLIQUIDACIÓN

41
CI rectificaciones de deducciones
SUMAR las CI de facturas en el LRFR registradas con linea de cuota deducible distinta de cero/nula con tipo de factura R1 a R5 que se han registrado con periodo consignado en la factura igual al periodo de autoliquidación, sin tener en cuenta las facturas rectificativas por sustitución con base rectificada distinto de cero o nulo.
LRFR
R1 R2 R3 R4 R5
CON/SIN DESGLOSE ISP
01 12
"SI"
"SI" + PERIODO+EJERCICIO DE AUTOLIQUIDACIÓN

Facturas rectificativas por sustitución con base rectificada distinto de cero/nulo: sumar las CI deducibles de facturas en el LRFR registradas con tipo de factura R1 a R5 que se han registrado con período consignado en la factura igual al periodo de autoliquidación, menos la cuota rectificada contenida en cada una de ellas.
LRFR
R1 R2 R3 R4 R5
CON/SIN DESGLOSE ISP
01 12
"SI"
"SI" + PERIODO+EJERCICIO DE AUTOLIQUIDACIÓN

SUMAR las CI deducibles de las facturas recibidas identificadas como rectificativas (R1 a R5) en caso de AIB que se han registrado con período de liquidación consignado en la factura igual al período de autoliquidación, sin tener cuenta las facturas rectificativas por sustitución con base rectificada distinto de cero o nulo.
LRFR
R1 R2 R3 R4 R5
SIN DESGLOSE ISP
O9
"SI"
"SI" + PERIODO+EJERCICIO DE AUTOLIQUIDACIÓN

Facturas rectificativas por sustitución con base rectificada distinto de cero/nulo en LRFR, sumar las CI deducibles de las facturas recibidas identificadas como rectificativas (R1 a R5) en caso de AIB que se han registrado con período de liquidación consignado en la factura igual al período de autoliquidación menos la cuota rectificada contenida en cada una de ellas.
LRFR
R1 R2 R3 R4 R5
SIN DESGLOSE ISP
O9
"SI"
"SI" + PERIODO+EJERCICIO DE AUTOLIQUIDACIÓN

SUMAR las CI de facturas que sin estar identificadas con R tienen cuota deducible negativa, que se han registrado con periodo consignado en la factura igual al periodo de autoliquidación.
LRFR
F1 F3 F4 F6 <0
CON/SIN DESGLOSE ISP
O1 12
"SI"
"SI" + PERIODO+EJERCICIO DE AUTOLIQUIDACIÓN

SUMAR las CI de importaciones con cuota deducible negativa, que se han registrado con periodo consignado en la factura igual al periodo de autoliquidación.
LRFR
F5 LC<0
SIN DESGLOSE ISP
O1 12
"SI"
"SI" + PERIODO+EJERCICIO DE AUTOLIQUIDACIÓN

SUMAR las CI de AIB con cuota deducible negativa que se han registrado con periodo consignado en la factura igual al periodo de autoliquidación.
LRFR
F1 F3 F4 F6<0
SIN DESGLOSE ISP
O9
"SI"
"SI" + PERIODO+EJERCICIO DE AUTOLIQUIDACIÓN

SUMAR las CI deducible positivas de las facturas recibidas contrastadas/parcialmente contrastadas no identificadas como R que se han emitido como rectificativas que se han registrado con periodo consignado en la factura igual al periodo de autoliquidación.
LRFR
F1 F3 F4 F6>0
CON/SIN DESGLOSE ISP
O1 12
"SI"
"SI" + PERIODO+EJERCICIO DE AUTOLIQUIDACIÓN

42
CI compensaciones Régimen Especial Agricultura, Ganadería y Pesca
CI deducible de facturas registradas con clave 02 con periodo consignado en la factura igual al periodo de autoliquidación.
LRFR
F1 F3 F6
SIN DESGLOSE ISP
O2
"SI"
"SI" + PERIODO+EJERCICIO DE AUTOLIQUIDACIÓN

SUMAR las CI deducibles de las facturas recibidas identificadas como rectificativas (R1 a R5) en caso de operaciones en las que los empresarios satisfacen compensaciones en las adquisiciones a personas acogidas a REAGP, que se han registrado con período de liquidación consignado en la factura igual al período de autoliquidación, sin tener en cuenta las facturas rectificativas por sustitución con base rectificada distinto de cero o nulo.
LRFR
R1 R2 R3 R4 R5
SIN DESGLOSE ISP
O2
"SI"
"SI" + PERIODO+EJERCICIO DE AUTOLIQUIDACIÓN

Facturas rectificativas por sustitución con base rectificada distinto de cero/nulo en LRFR SUMAR las CI deducibles de las facturas recibidas identificadas como rectificativas (R1 a R5) en caso de operaciones en las que los empresarios satisfacen compensaciones en las adquisiciones a personas acogidas a REAGP, que se han registrado con periodo de liquidación consignado en la factura igual al periodo de autoliquidación menos la cuota rectificada contenida en cada una de ellas.
LRFR
R1 R2 R3 R4 R5
SIN DESGLOSE ISP
O2
"SI"
"SI" + PERIODO+EJERCICIO DE AUTOLIQUIDACIÓN

43
NO CONSTA

44*

CUMPLIMENTACIÓN MANUAL EN CASO DE PRORRATA.Regularización Aplicación Porcentaje Definitivo Prorrata

Ajuste en cuota consecuencia de la regularización de las deducciones provisionales practicadas durante el ejercicio (Pd-Pp)*cuota soportada
LRFR
F1 F3 F4 F6 R1 R2 R3 R4 R5
CON/SIN DESGLOSE ISP
01 02 09 12

45
Cuota total a deducir
CI DEDUCIBLE de facturas registradas en LRFR.

Generar PDF
Cerrar
La generación del PDF puede tardar varios minutos dependiendo de la cantidad de información.
Seleccione la información que desee incluir en el PDF:
Sólo la página actual

Todas las páginas del índice

Puede cancelar la generación del PDF en cualquier momento.

CancelarContinuar

Generar PDF
[AnteriorIVA devengado](/Sede/iva/pre-303/2025.html)[SiguienteInformación adicional](/Sede/iva/pre-303/2025/informacion-adicional.html)

Página actualizada: 13/octubre/2026
