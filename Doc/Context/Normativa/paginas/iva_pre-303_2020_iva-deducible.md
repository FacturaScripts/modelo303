<!-- Fuente: https://sede.agenciatributaria.gob.es/Sede/iva/pre-303/2020/iva-deducible.html -->

Agencia Tributaria: 2020 - IVA deducible

 [Saltar al contenido principal](#acc-main) 

 [Logotipo del Gobierno de EspañaLogotipo Organismo](/Sede/inicio.html)

 Menú móvil[Menu](#)

[Inicio](/Sede/inicio.html)
[IVA](/Sede/iva.html)
[PRE 303 (Servicio ayuda modelo 303)](/Sede/iva/pre-303.html)

 2020
[Saltar índice de la información](#js-nombre-canal-paginado)ÍndiceÍndice:
[Información adicional](/Sede/iva/pre-303/2020.html)
IVA deducible
[IVA devengado](/Sede/iva/pre-303/2020/iva-devengado.html)
[Operaciones en el ejercicio](/Sede/iva/pre-303/2020/operaciones-ejercicio.html)

IVA deducible
Los campos incluidos en tipo de factura, tipo de operación y clave de régimen especial se pueden consultar en el documento "[Bloques Funcionales](/static_files/Sede/Procedimiento_ayuda/G417/FicherosSuministros/V_1_1/DR_v1.1.xls)"

CASILLALLAA DATOS SII 

 OPERACIÓNLR TIPO FACTURATIPO OPERACIÓNCLAVE RREE  

28

BI CUOTAS SOPORTADAS OPERACIONES INTERIORES
Suma las BI de facturas en el LRFR registradas con línea de cuota deducible/soportada mayor que cero con periodo consignado en la factura igual al periodo de autoliquidación. Excluir las facturas contrastadas y parcialmente contrastadas cuando la factura expedida es rectificativa
LRFR
F1 F3 F4 F6
CON/SIN DESGLOSE ISP
01 12

29
CI CUOTAS SOPORTADAS OPERACIONES INTERIORES
Sumar las cuotas deducible mayor que cero de facturas en el LRFR registradas con periodo consignado en la factura igual al periodo de autoliquidación. Excluir las facturas contrastadas y parcialmente contrastadas cuando la factura expedida es rectificativa
LRFR
F1 F3 F4 F6
CON/SIN DESGLOSE ISP
01 12

30
NO CONSTA

31
NO CONSTA

32
BI CUOTAS SOPORTADAS EN IMPORTACIONES DE BIENES
Sumar las BI de facturas en el LRFR registradas con línea de cuota deducible /soportada mayor que cero en tipo factura importaciones DUA y LC con periodo consignado en la factura igual al periodo de autoliquidación
LRFR
F5 LC
SIN DESGLOSE ISP
01

33
CI CUOTAS SOPORTADAS EN IMPORTACIONES DE BIENES
Sumar las CI deducible mayor que cero de facturas registradas tipo factura importaciones DUA y sin clave 13 con periodo consignado en la factura igual al periodo de autoliquidación
LRFR
F5 LC
SIN DESGLOSE ISP
01

34
NO CONSTA

35
NO CONSTA

36
BI CUOTAS SOPORTADAS EN AIB DE BIENES Y SERVICIOS
Sumar las BI de facturas en el LRFR registradas con clave 09 con línea de cuota deducible/soportada mayor que cero con periodo consignado en la factura igual al periodo de autoliquidación
LRFR
F1 F3 F4 F6
SIN DESGLOSE ISP
09

37
CI CUOTAS SOPORTADAS EN AIB DE BIENES Y SERVICIOS
Sumar las CI deducible mayor que cero de facturas registradas con clave 09 con periodo consignado en la factura igual al periodo de autoliquidación
LRFR
F1 F3 F4 F6
SIN DESGLOSE ISP
09

38
NO CONSTA

39
NO CONSTA

40
BI RECTIFICACIONES DE DEDUCCIONES
Sumar las BI de facturas en el LRFR registradas con línea de cuota deducible / soportada distinta de cero/nula con tipo de factura R1 a R5 que se han registrado con periodo consignado en la factura igual al periodo de autoliquidación, sin tener en cuenta las facturas rectificativas por sustitución con base rectificada distinto de cero o nulo
LRFR
R1 R2 R3 R4 R5
CON/SIN DESGLOSE ISP
01 12

Facturas rectificativas por sustitución con base rectificada distinto de cero/nulo sumar las BI de facturas en el LRFR registradas con tipo de factura R1 a R5 que se han registrado con periodo consignado en la factura igual al periodo de autoliquidación menos la base rectificada contenida en cada una de ellas
LRFR
R1 R2 R3 R4 R5
CON/SIN DESGLOSE ISP
01 12

Sumar las BI de las facturas recibidas identificadas como rectificativas (R1 a R5) y clave 09 que se han registrado con cuota deducible/soportada y periodo de liquidación consignado en la factura igual al periodo de autoliquidación, sin tener en cuenta las facturas rectificativas por sustitución con base rectificada distinto de cero o nulo
LRFR
R1 R2 R3 R4 R5
SIN DESGLOSE ISP
09

Facturas rectificativas por sustitución con base rectificada distinto de cero/nulo del LRFR con periodo de liquidación declarado igual al periodo autoliquidado, sumar las BI de las facturas recibidas identificadas como rectificativas (R1 a R5) y clave 09 menos base rectificada contenida en cada una de ellas
LRFR
R1 R2 R3 R4 R5
SIN DESGLOSE ISP
09

Sumar las BI de facturas que sin estar identificadas con R tienen cuota deducible/ soportada negativa, que se han registrado con periodo consignado en la factura igual al periodo de autoliquidación
LRFR
F1 F3 F4 F6 <0
CON/SIN DESGLOSE ISP
01 12

Sumar las BI de importaciones con cuota deducible/ soportada negativa, que se han registrado con periodo consignado en la factura igual al periodo de autoliquidación
LRFR
F5 LC<0
SIN DESGLOSE ISP
01

Sumar las BI de AIB con cuota deducible/ soportada negativa que se han registrado con periodo consignado en la factura igual al periodo de autoliquidación
LRFR
F1 F3 F4 F6<0
SIN DESGLOSE ISP
09

Sumar las BI de las facturas recibidas contrastadas / parcialmente contrastadas no identificadas como R con cuota deducible/ soportada positiva (distinta de cero/nula) con que se han emitido como rectificativas y registrado con periodo consignado en la factura igual al periodo de autoliquidación
LRFR
F1 F3 F4 F6>0
CON/SIN DESGLOSE ISP
01 12

41
CI RECTIFICACIONES DE DEDUCCIONES
Sumar las CI de facturas en el LRFR registradas con línea de cuota deducible distinta de cero/nula con tipo de factura R1 a R5 que se han registrado con periodo consignado en la factura igual al periodo de autoliquidación, sin tener en cuenta las facturas rectificativas por sustitución con base rectificada distinto de cero o nulo
LRFR
R1 R2 R3 R4 R5
CON/SIN DESGLOSE ISP
01 12

Facturas rectificativas por sustitución con base rectificada distinto de cero/nulo: sumar las CI deducibles de facturas en el LRFR registradas con tipo de factura R1 a R5 que se han registrado con periodo consignado en la factura igual al periodo de autoliquidación, menos la cuota rectificada contenida en cada una de ellas
LRFR
R1 R2 R3 R4 R5
CON/SIN DESGLOSE ISP
01 12

Sumar las CI deducibles de las facturas recibidas identificadas como rectificativas (R1 a R5) en caso de AIB que se han registrado con periodo de liquidación consignado en la factura igual al periodo de autoliquidación, sin tener en cuenta las facturas rectificativas por sustitución con base rectificada distinto de cero o nulo
LRFR
R1 R2 R3 R4 R5
SIN DESGLOSE ISP
09

Facturas rectificativas por sustitución con base rectificada distinto de cero/nulo en LRFR sumar las CI deducibles de las facturas recibidas identificadas como rectificativas (R1 a R5) en caso de AIB que se han registrado con periodo de liquidación consignado en la factura igual al periodo de autoliquidación menos la cuota rectificada contenida en cada una de ellas
LRFR
R1 R2 R3 R4 R5
SIN DESGLOSE ISP
09

Sumar las CI de facturas que sin estar identificadas con R tienen cuota deducible negativa, que se han registrado con periodo consignado en la factura igual al periodo de autoliquidación
LRFR
F1 F3 F4 F6 <0
CON/SIN DESGLOSE ISP
01 12

Sumar las CI de importaciones con cuota deducible negativa, que se han registrado con periodo consignado en la factura igual al periodo de autoliquidación
LRFR
F5 LC<0
SIN DESGLOSE ISP
01 12

Sumar las CI de AIB con cuota deducible negativa que se han registrado con periodo consignado en la factura igual al periodo de autoliquidación
LRFR
F1 F3 F4 F6<0
SIN DESGLOSE ISP
09

Sumar las CI deducible positivas de las facturas recibidas contrastadas / parcialmente contrastadas no identificadas como R que se han emitido como rectificativas que se han registrado con periodo consignado en la factura igual al periodo de autoliquidación
LRFR
F1 F3 F4 F6>0
CON/SIN DESGLOSE ISP
01 12

42
CI COMPENSACIONES REGIMEN ESPECIAL A.G.Y P
CI deducible de facturas registradas con clave 02 con periodo consignado en la factura igual al periodo de autoliquidación
LRFR
F1 F3 F6
SIN DESGLOSE ISP
02

Sumar las CI deducibles de las facturas recibidas identificadas como rectificativas (R1 a R5) en caso de operaciones en las que los empresarios satisfacen compensaciones en las adquisiciones a personas acogidas a  REAGP, que se han registrado con período de liquidación consignado en la factura igual al período de autoliquidación, sin tener en cuenta las facturas rectificativas por sustitución con base rectificada distinto de cero o nulo.

LRFR
R1 R2 R3 R4 R5
SIN DESGLOSE ISP
02

Facturas rectificativas por sustitución con base rectificada distinto de cero/nulo en LRFR sumar las CI deducibles de las facturas recibidas identificadas como rectificativas (R1 a R5) en caso de operaciones en las que los empresarios satisfacen compensaciones en las adquisiciones a personas acogidas a REAGP, que se han registrado con periodo de liquidación consignado en la factura igual al periodo de autoliquidación menos la cuota rectificada contenida en cada una de ellas.
LRFR
R1 R2 R3 R4 R5
SIN DESGLOSE ISP
02

43
NO CONSTA

44
NO CONSTA

45
CUOTA TOTAL A DEDUCIR
CI deducible de facturas registradas en LRFR con periodo consignado en la factura igual al periodo de autoliquidación

Generar PDF
Cerrar
La generación del PDF puede tardar varios minutos dependiendo de la cantidad de información.
Seleccione la información que desee incluir en el PDF:
Sólo la página actual

Todas las páginas del índice

Puede cancelar la generación del PDF en cualquier momento.

CancelarContinuar

Generar PDF
[AnteriorInformación adicional](/Sede/iva/pre-303/2020.html)[SiguienteIVA devengado](/Sede/iva/pre-303/2020/iva-devengado.html)

Página actualizada: 13/octubre/2026
