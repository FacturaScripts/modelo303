<?php
/**
 * This file is part of Modelo303 plugin for FacturaScripts
 * Copyright (C) 2017-2026 Carlos Garcia Gomez <carlos@facturascripts.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace FacturaScripts\Plugins\Modelo303\Lib;

use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Dinamic\Model\RegularizacionImpuesto;

/**
 * Genera el fichero de texto del modelo 303 para su presentación telemática en la AEAT.
 *
 * El fichero sigue el "diseño de registro" oficial DR303 (ejercicio 2026, v1.01), que usa un
 * formato de posiciones fijas con bloques delimitados por etiquetas:
 *
 *   <T3030EEEEPP0000>   (cabecera)
 *   <AUX> ... </AUX>    (bloque reservado para la Administración / entidad desarrolladora)
 *   [página 1]          (<T30301000> ... </T30301000>) - Régimen general (1581 posiciones)
 *   [página 3]          (<T30303000> ... </T30303000>) - Información adicional y resultado (1017)
 *   [página DID]        (<T303DID00> ... </T303DID00>) - Domiciliación/Devolución (823, opcional)
 *   </T3030EEEEPP0000>  (cierre)
 *
 * Los valores de las casillas se toman de la clase Modelo303 (que las calcula desde la
 * contabilidad). Los datos sin origen automático (tipo de declaración, IBAN, cuotas a compensar
 * de periodos anteriores, etc.) se toman del modelo RegularizacionImpuesto, donde el usuario los
 * introduce manualmente.
 *
 * @author FacturaScripts
 */
class Txt303Export
{
    /**
     * Mapa de campos de la página 1 (régimen general). Cada campo es:
     *   ['l' => longitud, 'k' => tipo, 'v' => valor]
     * tipos:
     *   c  => constante literal (etiquetas, modelo, tipos de IVA fijos)
     *   u  => importe sin signo (Num): 15 enteros + 2 decimales, relleno con ceros a la izquierda
     *   a  => importe con signo (N): 'N' en la primera posición si es negativo
     *   p  => porcentaje (3 enteros + 2 decimales)
     *   id => campo de identificación/manual resuelto por resolveId()
     *   b  => reservado para la AEAT (blancos)
     */
    private const PAGE1 = [
        ['l'=>2,'k'=>'c','v'=>'<T'],
        ['l'=>3,'k'=>'c','v'=>'303'],
        ['l'=>5,'k'=>'c','v'=>'01000'],
        ['l'=>1,'k'=>'c','v'=>'>'],
        ['l'=>1,'k'=>'id','v'=>'pag_complementaria'],
        ['l'=>1,'k'=>'id','v'=>'tipo_declaracion'],
        ['l'=>9,'k'=>'id','v'=>'nif'],
        ['l'=>80,'k'=>'id','v'=>'nombre'],
        ['l'=>4,'k'=>'id','v'=>'ejercicio'],
        ['l'=>2,'k'=>'id','v'=>'periodo'],
        ['l'=>1,'k'=>'id','v'=>'ind_foral'],
        ['l'=>1,'k'=>'id','v'=>'ind_dev_mensual'],
        ['l'=>1,'k'=>'id','v'=>'ind_simplificado'],
        ['l'=>1,'k'=>'id','v'=>'ind_conjunta'],
        ['l'=>1,'k'=>'id','v'=>'ind_caja'],
        ['l'=>1,'k'=>'id','v'=>'ind_dest_caja'],
        ['l'=>1,'k'=>'id','v'=>'ind_prorrata_opc'],
        ['l'=>1,'k'=>'id','v'=>'ind_prorrata_rev'],
        ['l'=>1,'k'=>'id','v'=>'ind_concurso'],
        ['l'=>8,'k'=>'id','v'=>'fecha_concurso'],
        ['l'=>1,'k'=>'id','v'=>'tipo_concurso'],
        ['l'=>1,'k'=>'id','v'=>'ind_sii'],
        ['l'=>1,'k'=>'id','v'=>'ind_exonerado390'],
        ['l'=>1,'k'=>'id','v'=>'ind_volumen'],
        ['l'=>1,'k'=>'id','v'=>'ind_gasolinas'],
        ['l'=>17,'k'=>'u','v'=>'150'],
        ['l'=>5,'k'=>'c','v'=>'00000'],
        ['l'=>17,'k'=>'u','v'=>'152'],
        ['l'=>17,'k'=>'u','v'=>'165'],
        ['l'=>5,'k'=>'c','v'=>'00000'],
        ['l'=>17,'k'=>'u','v'=>'167'],
        ['l'=>17,'k'=>'u','v'=>'01'],
        ['l'=>5,'k'=>'c','v'=>'00400'],
        ['l'=>17,'k'=>'u','v'=>'03'],
        ['l'=>17,'k'=>'u','v'=>'153'],
        ['l'=>5,'k'=>'c','v'=>'00000'],
        ['l'=>17,'k'=>'u','v'=>'155'],
        ['l'=>17,'k'=>'u','v'=>'04'],
        ['l'=>5,'k'=>'c','v'=>'01000'],
        ['l'=>17,'k'=>'u','v'=>'06'],
        ['l'=>17,'k'=>'u','v'=>'07'],
        ['l'=>5,'k'=>'c','v'=>'02100'],
        ['l'=>17,'k'=>'u','v'=>'09'],
        ['l'=>17,'k'=>'u','v'=>'10'],
        ['l'=>17,'k'=>'u','v'=>'11'],
        ['l'=>17,'k'=>'u','v'=>'12'],
        ['l'=>17,'k'=>'u','v'=>'13'],
        ['l'=>17,'k'=>'a','v'=>'14'],
        ['l'=>17,'k'=>'a','v'=>'15'],
        ['l'=>17,'k'=>'u','v'=>'156'],
        ['l'=>5,'k'=>'c','v'=>'00175'],
        ['l'=>17,'k'=>'u','v'=>'158'],
        ['l'=>17,'k'=>'u','v'=>'168'],
        ['l'=>5,'k'=>'c','v'=>'00050'],
        ['l'=>17,'k'=>'u','v'=>'170'],
        ['l'=>17,'k'=>'u','v'=>'16'],
        ['l'=>5,'k'=>'c','v'=>'00000'],
        ['l'=>17,'k'=>'u','v'=>'18'],
        ['l'=>17,'k'=>'u','v'=>'19'],
        ['l'=>5,'k'=>'c','v'=>'00140'],
        ['l'=>17,'k'=>'u','v'=>'21'],
        ['l'=>17,'k'=>'u','v'=>'22'],
        ['l'=>5,'k'=>'c','v'=>'00520'],
        ['l'=>17,'k'=>'u','v'=>'24'],
        ['l'=>17,'k'=>'a','v'=>'25'],
        ['l'=>17,'k'=>'a','v'=>'26'],
        ['l'=>17,'k'=>'a','v'=>'27'],
        ['l'=>17,'k'=>'u','v'=>'28'],
        ['l'=>17,'k'=>'u','v'=>'29'],
        ['l'=>17,'k'=>'u','v'=>'30'],
        ['l'=>17,'k'=>'u','v'=>'31'],
        ['l'=>17,'k'=>'u','v'=>'32'],
        ['l'=>17,'k'=>'u','v'=>'33'],
        ['l'=>17,'k'=>'u','v'=>'34'],
        ['l'=>17,'k'=>'u','v'=>'35'],
        ['l'=>17,'k'=>'u','v'=>'36'],
        ['l'=>17,'k'=>'u','v'=>'37'],
        ['l'=>17,'k'=>'u','v'=>'38'],
        ['l'=>17,'k'=>'u','v'=>'39'],
        ['l'=>17,'k'=>'a','v'=>'40'],
        ['l'=>17,'k'=>'a','v'=>'41'],
        ['l'=>17,'k'=>'a','v'=>'42'],
        ['l'=>17,'k'=>'a','v'=>'43'],
        ['l'=>17,'k'=>'a','v'=>'44'],
        ['l'=>17,'k'=>'a','v'=>'45'],
        ['l'=>17,'k'=>'a','v'=>'46'],
        ['l'=>521,'k'=>'b'],
        ['l'=>13,'k'=>'b'],
        ['l'=>12,'k'=>'c','v'=>'</T30301000>'],
    ];

    /**
     * Mapa de campos de la página 3 (información adicional y resultado de la autoliquidación).
     */
    private const PAGE3 = [
        ['l'=>2,'k'=>'c','v'=>'<T'],
        ['l'=>3,'k'=>'c','v'=>'303'],
        ['l'=>5,'k'=>'c','v'=>'03000'],
        ['l'=>1,'k'=>'c','v'=>'>'],
        ['l'=>17,'k'=>'a','v'=>'59'],
        ['l'=>17,'k'=>'a','v'=>'60'],
        ['l'=>17,'k'=>'a','v'=>'120'],
        ['l'=>17,'k'=>'a','v'=>'122'],
        ['l'=>17,'k'=>'a','v'=>'123'],
        ['l'=>17,'k'=>'a','v'=>'124'],
        ['l'=>17,'k'=>'a','v'=>'62'],
        ['l'=>17,'k'=>'a','v'=>'63'],
        ['l'=>17,'k'=>'a','v'=>'74'],
        ['l'=>17,'k'=>'a','v'=>'75'],
        ['l'=>17,'k'=>'a','v'=>'76'],
        ['l'=>17,'k'=>'a','v'=>'64'],
        ['l'=>5,'k'=>'p','v'=>'65'],
        ['l'=>17,'k'=>'a','v'=>'66'],
        ['l'=>17,'k'=>'u','v'=>'77'],
        ['l'=>17,'k'=>'u','v'=>'110'],
        ['l'=>17,'k'=>'u','v'=>'78'],
        ['l'=>17,'k'=>'u','v'=>'87'],
        ['l'=>17,'k'=>'a','v'=>'68'],
        ['l'=>17,'k'=>'a','v'=>'108'],
        ['l'=>17,'k'=>'a','v'=>'69'],
        ['l'=>17,'k'=>'u','v'=>'70'],
        ['l'=>17,'k'=>'u','v'=>'109'],
        ['l'=>17,'k'=>'u','v'=>'112'],
        ['l'=>17,'k'=>'a','v'=>'71'],
        ['l'=>1,'k'=>'id','v'=>'sin_actividad'],
        ['l'=>1,'k'=>'id','v'=>'rectificativa'],
        ['l'=>13,'k'=>'id','v'=>'justificante_ant'],
        ['l'=>1,'k'=>'id','v'=>'baja_domiciliacion'],
        ['l'=>17,'k'=>'u','v'=>'111'],
        ['l'=>1,'k'=>'id','v'=>'motivo_rect_1'],
        ['l'=>1,'k'=>'id','v'=>'motivo_rect_2'],
        ['l'=>546,'k'=>'b'],
        ['l'=>12,'k'=>'c','v'=>'</T30303000>'],
    ];

    /**
     * Mapa de campos de la página DID (datos de domiciliación / devolución).
     */
    private const PAGE_DID = [
        ['l'=>2,'k'=>'c','v'=>'<T'],
        ['l'=>3,'k'=>'c','v'=>'303'],
        ['l'=>5,'k'=>'c','v'=>'DID00'],
        ['l'=>1,'k'=>'c','v'=>'>'],
        ['l'=>11,'k'=>'id','v'=>'swift'],
        ['l'=>34,'k'=>'id','v'=>'iban'],
        ['l'=>70,'k'=>'id','v'=>'banco_nombre'],
        ['l'=>35,'k'=>'id','v'=>'banco_dir'],
        ['l'=>30,'k'=>'id','v'=>'banco_ciudad'],
        ['l'=>2,'k'=>'id','v'=>'banco_pais'],
        ['l'=>1,'k'=>'id','v'=>'marca_sepa'],
        ['l'=>617,'k'=>'b'],
        ['l'=>12,'k'=>'c','v'=>'</T303DID00>'],
    ];

    /** @var Empresa */
    private static Empresa $company;

    /** @var string período en formato AEAT (1T..4T o 01..12) */
    private static string $period = '';

    /** @var RegularizacionImpuesto */
    private static RegularizacionImpuesto $reg;

    /** @var array<string, float> casillas calculadas (clave = nº de casilla AEAT) */
    private static array $squares = [];

    /** @var string ejercicio en 4 dígitos (EEEE) */
    private static string $year = '';

    /**
     * Genera el contenido completo del fichero .303 (codificado en ISO-8859-1).
     *
     * @param RegularizacionImpuesto $reg
     * @param array<string, float> $squares casillas calculadas por Modelo303::getSquares()
     * @param Empresa $company
     * @return string
     */
    public static function export(RegularizacionImpuesto $reg, array $squares, Empresa $company): string
    {
        self::$reg = $reg;
        self::$squares = $squares;
        self::$company = $company;
        self::$year = date('Y', strtotime((string)$reg->fechainicio));
        self::$period = self::periodAeat((string)$reg->periodo);

        // cabecera + páginas + cierre
        $content = self::buildCabecera()
            . self::buildPage(self::PAGE1)
            . self::buildPage(self::PAGE3);

        // la página de domiciliación/devolución solo se incluye si hay IBAN
        if (false === empty($reg->iban)) {
            $content .= self::buildPage(self::PAGE_DID);
        }

        $content .= self::closeCabecera();

        return mb_convert_encoding($content, 'ISO-8859-1', 'UTF-8');
    }

    /**
     * Formatea un campo alfanumérico: mayúsculas, sin acentos, alineado a la izquierda y
     * relleno con blancos por la derecha (según Nota 2 del diseño de registro).
     *
     * @param string $value
     * @param int $length
     * @return string
     */
    private static function alpha(string $value, int $length): string
    {
        $value = mb_strtoupper(self::sanitize($value), 'UTF-8');
        $value = mb_substr($value, 0, $length, 'UTF-8');
        $pad = $length - mb_strlen($value, 'UTF-8');
        if ($pad > 0) {
            $value .= str_repeat(' ', $pad);
        }
        return $value;
    }

    /**
     * Formatea un importe con signo (campo "N"): 'N' en la primera posición si es negativo,
     * en otro caso relleno con ceros a la izquierda.
     *
     * @param float $value
     * @param int $length
     * @return string
     */
    private static function amountSigned(float $value, int $length): string
    {
        $cents = (int)round(abs($value) * 100.0);
        if ($value < 0.0) {
            return 'N' . str_pad((string)$cents, $length - 1, '0', STR_PAD_LEFT);
        }
        return str_pad((string)$cents, $length, '0', STR_PAD_LEFT);
    }

    /**
     * Formatea un importe sin signo (campo "Num"): 2 decimales implícitos, relleno con ceros.
     *
     * @param float $value
     * @param int $length
     * @return string
     */
    private static function amountUnsigned(float $value, int $length): string
    {
        $cents = (int)round(abs($value) * 100.0);
        return str_pad((string)$cents, $length, '0', STR_PAD_LEFT);
    }

    /**
     * Construye la etiqueta de apertura y el bloque AUX de la cabecera.
     *
     * @return string
     */
    private static function buildCabecera(): string
    {
        // etiqueta de apertura: <T + 303 + 0 (discriminante) + EEEE + PP + 0000>
        $open = '<T' . '303' . '0' . self::$year . self::$period . '0000>';

        // bloque AUX (posiciones 18-328)
        $aux = '<AUX>'
            . str_repeat(' ', 70)         // reservado AEAT
            . self::alpha('0101', 4)      // versión del programa (informativo)
            . str_repeat(' ', 4)          // reservado AEAT
            . str_repeat(' ', 9)          // NIF empresa de desarrollo (no aplica)
            . str_repeat(' ', 213)        // reservado AEAT
            . '</AUX>';

        return $open . $aux;
    }

    /**
     * Construye una página completa a partir de su mapa de campos, validando la longitud final.
     *
     * @param array $map
     * @return string
     */
    private static function buildPage(array $map): string
    {
        $out = '';
        foreach ($map as $field) {
            $len = (int)$field['l'];
            switch ($field['k']) {
                case 'c':
                    $out .= $field['v'];
                    break;

                case 'u':
                    $out .= self::amountUnsigned(self::square($field['v']), $len);
                    break;

                case 'a':
                    $out .= self::amountSigned(self::square($field['v']), $len);
                    break;

                case 'p':
                    $out .= self::percent(self::square($field['v']), $len);
                    break;

                case 'id':
                    $out .= self::resolveId((string)$field['v'], $len);
                    break;

                case 'b':
                default:
                    $out .= str_repeat(' ', $len);
                    break;
            }
        }
        return $out;
    }

    /**
     * Construye la etiqueta de cierre de la cabecera.
     *
     * @return string
     */
    private static function closeCabecera(): string
    {
        return '</T' . '303' . '0' . self::$year . self::$period . '0000>';
    }

    /**
     * Indica si la empresa tributa en el régimen de IVA dado.
     *
     * @param string $regimen
     * @return bool
     */
    private static function isRegimen(string $regimen): bool
    {
        return strtolower((string)self::$company->regimeniva) === strtolower($regimen);
    }

    /**
     * Formatea un porcentaje (3 enteros + 2 decimales), relleno con ceros a la izquierda.
     *
     * @param float $value
     * @param int $length
     * @return string
     */
    private static function percent(float $value, int $length): string
    {
        $hundredths = (int)round(abs($value) * 100.0);
        return str_pad((string)$hundredths, $length, '0', STR_PAD_LEFT);
    }

    /**
     * Convierte el período de RegularizacionImpuesto (T1..T4, 01..12) al formato AEAT (1T..4T, 01..12).
     *
     * @param string $periodo
     * @return string
     */
    private static function periodAeat(string $periodo): string
    {
        $map = ['T1' => '1T', 'T2' => '2T', 'T3' => '3T', 'T4' => '4T'];
        if (isset($map[$periodo])) {
            return $map[$periodo];
        }

        // meses 01..12
        if (preg_match('/^\d{1,2}$/', $periodo)) {
            return str_pad($periodo, 2, '0', STR_PAD_LEFT);
        }

        // por defecto, primer trimestre
        return '1T';
    }

    /**
     * Resuelve el valor de un campo de identificación o manual.
     *
     * @param string $key
     * @param int $len
     * @return string
     */
    private static function resolveId(string $key, int $len): string
    {
        switch ($key) {
            case 'nif':
                return self::alpha((string)self::$company->cifnif, $len);

            case 'nombre':
                return self::alpha((string)self::$company->nombre, $len);

            case 'ejercicio':
                return self::$year;

            case 'periodo':
                return self::$period;

            case 'tipo_declaracion':
                return self::tipoDeclaracion();

            // Indicadores de identificación. Por defecto "2" (NO) salvo casos especiales.
            case 'ind_simplificado':
                // "1" sólo Régimen Simplificado, "2" RG+RS, "3" sólo Régimen General.
                return self::isRegimen('Simplificado') ? '1' : '3';

            case 'ind_caja':
                return self::isRegimen('Caja') ? '1' : '2';

            case 'ind_foral':
            case 'ind_dev_mensual':
            case 'ind_conjunta':
            case 'ind_dest_caja':
            case 'ind_prorrata_opc':
            case 'ind_prorrata_rev':
            case 'ind_concurso':
            case 'ind_sii':
                return '2';

            // Indicadores que para periodos no anuales valen "0".
            case 'ind_exonerado390':
            case 'ind_volumen':
            case 'ind_gasolinas':
                return '0';

            case 'sin_actividad':
                return self::$reg->sinactividad ? 'X' : ' ';

            case 'rectificativa':
            case 'motivo_rect_1':
                return self::$reg->rectificativa ? 'X' : ' ';

            case 'justificante_ant':
                return self::alpha((string)(self::$reg->justificanteant ?? ''), $len);

            case 'iban':
                return self::alpha(preg_replace('/\s+/', '', (string)self::$reg->iban), $len);

            case 'marca_sepa':
                // "1" cuenta España, "2" UE-SEPA, "3" resto países, "0" vacía.
                $iban = strtoupper(trim((string)self::$reg->iban));
                if (empty($iban)) {
                    return '0';
                }
                return str_starts_with($iban, 'ES') ? '1' : '2';

            // resto de campos de identificación: blancos
            case 'pag_complementaria':
            case 'fecha_concurso':
            case 'tipo_concurso':
            case 'baja_domiciliacion':
            case 'motivo_rect_2':
            case 'swift':
            case 'banco_nombre':
            case 'banco_dir':
            case 'banco_ciudad':
            case 'banco_pais':
            default:
                return str_repeat(' ', $len);
        }
    }

    /**
     * Elimina acentos y caracteres no admitidos en los campos alfanuméricos.
     *
     * @param string|null $txt
     * @return string
     */
    private static function sanitize(?string $txt): string
    {
        $changes = ['/à/' => 'a', '/á/' => 'a', '/â/' => 'a', '/ã/' => 'a', '/ä/' => 'a',
            '/å/' => 'a', '/æ/' => 'ae', '/ç/' => 'c', '/è/' => 'e', '/é/' => 'e', '/ê/' => 'e',
            '/ë/' => 'e', '/ì/' => 'i', '/í/' => 'i', '/î/' => 'i', '/ï/' => 'i', '/ð/' => 'd',
            '/ñ/' => 'N', '/ò/' => 'o', '/ó/' => 'o', '/ô/' => 'o', '/õ/' => 'o', '/ö/' => 'o',
            '/ő/' => 'o', '/ø/' => 'o', '/ù/' => 'u', '/ú/' => 'u', '/û/' => 'u', '/ü/' => 'u',
            '/ű/' => 'u', '/ý/' => 'y', '/þ/' => 'th', '/ÿ/' => 'y',
            '/&quot;/' => '-', '/€/' => 'EUR', '/º/' => '.',
            '/À/' => 'A', '/Á/' => 'A', '/Â/' => 'A', '/Ä/' => 'A',
            '/Ç/' => 'C', '/È/' => 'E', '/É/' => 'E', '/Ê/' => 'E',
            '/Ë/' => 'E', '/Ì/' => 'I', '/Í/' => 'I', '/Î/' => 'I', '/Ï/' => 'I',
            '/Ñ/' => 'N', '/Ò/' => 'O', '/Ó/' => 'O', '/Ô/' => 'O', '/Ö/' => 'O',
            '/Ù/' => 'U', '/Ú/' => 'U', '/Û/' => 'U', '/Ü/' => 'U',
            '/Ý/' => 'Y', '/Ÿ/' => 'Y'
        ];

        $txtNoHtml = Tools::noHtml($txt) ?? '';
        return preg_replace(array_keys($changes), $changes, $txtNoHtml);
    }

    /**
     * Devuelve el valor de una casilla.
     *
     * @param string $square
     * @return float
     */
    private static function square(string $square): float
    {
        return (float)(self::$squares[$square] ?? 0.0);
    }

    /**
     * Determina el tipo de declaración (campo PI de la página 1).
     * Si el usuario lo ha indicado manualmente se respeta; en caso contrario se deduce del
     * resultado de la liquidación (casilla 71) y de la existencia de IBAN.
     *
     * @return string
     */
    private static function tipoDeclaracion(): string
    {
        // valor manual introducido por el usuario
        $manual = strtoupper(trim((string)(self::$reg->tipodeclaracion ?? '')));
        if (false === empty($manual)) {
            return substr($manual, 0, 1);
        }

        if (self::$reg->sinactividad) {
            return 'N';
        }

        $resultado = self::square('71');
        $tieneIban = false === empty(self::$reg->iban);

        if ($resultado > 0.0) {
            return $tieneIban ? 'U' : 'I';   // domiciliación del ingreso / ingreso
        }
        if ($resultado < 0.0) {
            return $tieneIban ? 'D' : 'C';   // devolución / compensación
        }
        return 'N';                          // sin actividad / resultado cero
    }
}
