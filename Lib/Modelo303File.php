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

use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Dinamic\Model\RegularizacionImpuesto;

/**
 * Genera el fichero de texto del modelo 303 para su presentación telemática en la AEAT.
 *
 * El fichero sigue el "diseño de registro" oficial DR303 del ejercicio de la regularización: el de
 * 2026 (v1.01) para 2026 y siguientes y el de 2025 (v1.02) para los ejercicios anteriores. Usa un
 * formato de posiciones fijas con bloques delimitados por etiquetas:
 *
 *   <T3030EEEEPP0000>   (cabecera)
 *   <AUX> ... </AUX>    (bloque reservado para la Administración / entidad desarrolladora)
 *   [página 1]          (<T30301000> ... </T30301000>) - Régimen general (1581 posiciones)
 *                       En 2025 no existe el indicador de gasolinas (posición 130) y las casillas
 *                       [165]-[167] y [168]-[170] van al final, tras la [46].
 *   [página 3]          (<T30303000> ... </T30303000>) - Información adicional y resultado (1017)
 *                       En 2025 no existe la casilla [112] y la [108] va tras el justificante anterior.
 *   [página DID]        (<T303DID00> ... </T303DID00>) - Domiciliación/Devolución (823, opcional)
 *   </T3030EEEEPP0000>  (cierre)
 *
 * Los valores de las casillas se toman de la clase Modelo303 (que las calcula desde la
 * contabilidad). Los datos sin origen automático (tipo de declaración, IBAN, cuotas a compensar
 * de periodos anteriores, etc.) se toman del modelo RegularizacionImpuesto, donde el usuario los
 * introduce manualmente. Los formateadores de campos (alfanuméricos, importes y porcentajes)
 * están en Modelo303FileTrait, compartido con el fichero del modelo 390.
 *
 * @author FacturaScripts
 */
class Modelo303File
{
    use Modelo303FileTrait;

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
        ['l' => 2, 'k' => 'c', 'v' => '<T'],
        ['l' => 3, 'k' => 'c', 'v' => '303'],
        ['l' => 5, 'k' => 'c', 'v' => '01000'],
        ['l' => 1, 'k' => 'c', 'v' => '>'],
        ['l' => 1, 'k' => 'id', 'v' => 'pag_complementaria'],
        ['l' => 1, 'k' => 'id', 'v' => 'tipo_declaracion'],
        ['l' => 9, 'k' => 'id', 'v' => 'nif'],
        ['l' => 80, 'k' => 'id', 'v' => 'nombre'],
        ['l' => 4, 'k' => 'id', 'v' => 'ejercicio'],
        ['l' => 2, 'k' => 'id', 'v' => 'periodo'],
        ['l' => 1, 'k' => 'id', 'v' => 'ind_foral'],
        ['l' => 1, 'k' => 'id', 'v' => 'ind_dev_mensual'],
        ['l' => 1, 'k' => 'id', 'v' => 'ind_simplificado'],
        ['l' => 1, 'k' => 'id', 'v' => 'ind_conjunta'],
        ['l' => 1, 'k' => 'id', 'v' => 'ind_caja'],
        ['l' => 1, 'k' => 'id', 'v' => 'ind_dest_caja'],
        ['l' => 1, 'k' => 'id', 'v' => 'ind_prorrata_opc'],
        ['l' => 1, 'k' => 'id', 'v' => 'ind_prorrata_rev'],
        ['l' => 1, 'k' => 'id', 'v' => 'ind_concurso'],
        ['l' => 8, 'k' => 'id', 'v' => 'fecha_concurso'],
        ['l' => 1, 'k' => 'id', 'v' => 'tipo_concurso'],
        ['l' => 1, 'k' => 'id', 'v' => 'ind_sii'],
        ['l' => 1, 'k' => 'id', 'v' => 'ind_exonerado390'],
        ['l' => 1, 'k' => 'id', 'v' => 'ind_volumen'],
        ['l' => 1, 'k' => 'id', 'v' => 'ind_gasolinas'],
        ['l' => 17, 'k' => 'u', 'v' => '150'],
        ['l' => 5, 'k' => 'c', 'v' => '00000'],
        ['l' => 17, 'k' => 'u', 'v' => '152'],
        ['l' => 17, 'k' => 'u', 'v' => '165'],
        ['l' => 5, 'k' => 'c', 'v' => '00000'],
        ['l' => 17, 'k' => 'u', 'v' => '167'],
        ['l' => 17, 'k' => 'u', 'v' => '01'],
        ['l' => 5, 'k' => 'c', 'v' => '00400'],
        ['l' => 17, 'k' => 'u', 'v' => '03'],
        ['l' => 17, 'k' => 'u', 'v' => '153'],
        ['l' => 5, 'k' => 'c', 'v' => '00000'],
        ['l' => 17, 'k' => 'u', 'v' => '155'],
        ['l' => 17, 'k' => 'u', 'v' => '04'],
        ['l' => 5, 'k' => 'c', 'v' => '01000'],
        ['l' => 17, 'k' => 'u', 'v' => '06'],
        ['l' => 17, 'k' => 'u', 'v' => '07'],
        ['l' => 5, 'k' => 'c', 'v' => '02100'],
        ['l' => 17, 'k' => 'u', 'v' => '09'],
        ['l' => 17, 'k' => 'u', 'v' => '10'],
        ['l' => 17, 'k' => 'u', 'v' => '11'],
        ['l' => 17, 'k' => 'u', 'v' => '12'],
        ['l' => 17, 'k' => 'u', 'v' => '13'],
        ['l' => 17, 'k' => 'a', 'v' => '14'],
        ['l' => 17, 'k' => 'a', 'v' => '15'],
        ['l' => 17, 'k' => 'u', 'v' => '156'],
        ['l' => 5, 'k' => 'c', 'v' => '00175'],
        ['l' => 17, 'k' => 'u', 'v' => '158'],
        ['l' => 17, 'k' => 'u', 'v' => '168'],
        ['l' => 5, 'k' => 'c', 'v' => '00050'],
        ['l' => 17, 'k' => 'u', 'v' => '170'],
        ['l' => 17, 'k' => 'u', 'v' => '16'],
        ['l' => 5, 'k' => 'c', 'v' => '00000'],
        ['l' => 17, 'k' => 'u', 'v' => '18'],
        ['l' => 17, 'k' => 'u', 'v' => '19'],
        ['l' => 5, 'k' => 'c', 'v' => '00140'],
        ['l' => 17, 'k' => 'u', 'v' => '21'],
        ['l' => 17, 'k' => 'u', 'v' => '22'],
        ['l' => 5, 'k' => 'c', 'v' => '00520'],
        ['l' => 17, 'k' => 'u', 'v' => '24'],
        ['l' => 17, 'k' => 'a', 'v' => '25'],
        ['l' => 17, 'k' => 'a', 'v' => '26'],
        ['l' => 17, 'k' => 'a', 'v' => '27'],
        ['l' => 17, 'k' => 'u', 'v' => '28'],
        ['l' => 17, 'k' => 'u', 'v' => '29'],
        ['l' => 17, 'k' => 'u', 'v' => '30'],
        ['l' => 17, 'k' => 'u', 'v' => '31'],
        ['l' => 17, 'k' => 'u', 'v' => '32'],
        ['l' => 17, 'k' => 'u', 'v' => '33'],
        ['l' => 17, 'k' => 'u', 'v' => '34'],
        ['l' => 17, 'k' => 'u', 'v' => '35'],
        ['l' => 17, 'k' => 'u', 'v' => '36'],
        ['l' => 17, 'k' => 'u', 'v' => '37'],
        ['l' => 17, 'k' => 'u', 'v' => '38'],
        ['l' => 17, 'k' => 'u', 'v' => '39'],
        ['l' => 17, 'k' => 'a', 'v' => '40'],
        ['l' => 17, 'k' => 'a', 'v' => '41'],
        ['l' => 17, 'k' => 'a', 'v' => '42'],
        ['l' => 17, 'k' => 'a', 'v' => '43'],
        ['l' => 17, 'k' => 'a', 'v' => '44'],
        ['l' => 17, 'k' => 'a', 'v' => '45'],
        ['l' => 17, 'k' => 'a', 'v' => '46'],
        ['l' => 521, 'k' => 'b'],
        ['l' => 13, 'k' => 'b'],
        ['l' => 12, 'k' => 'c', 'v' => '</T30301000>'],
    ];

    /**
     * Mapa de campos de la página 3 (información adicional y resultado de la autoliquidación).
     */
    private const PAGE3 = [
        ['l' => 2, 'k' => 'c', 'v' => '<T'],
        ['l' => 3, 'k' => 'c', 'v' => '303'],
        ['l' => 5, 'k' => 'c', 'v' => '03000'],
        ['l' => 1, 'k' => 'c', 'v' => '>'],
        ['l' => 17, 'k' => 'a', 'v' => '59'],
        ['l' => 17, 'k' => 'a', 'v' => '60'],
        ['l' => 17, 'k' => 'a', 'v' => '120'],
        ['l' => 17, 'k' => 'a', 'v' => '122'],
        ['l' => 17, 'k' => 'a', 'v' => '123'],
        ['l' => 17, 'k' => 'a', 'v' => '124'],
        ['l' => 17, 'k' => 'a', 'v' => '62'],
        ['l' => 17, 'k' => 'a', 'v' => '63'],
        ['l' => 17, 'k' => 'a', 'v' => '74'],
        ['l' => 17, 'k' => 'a', 'v' => '75'],
        ['l' => 17, 'k' => 'a', 'v' => '76'],
        ['l' => 17, 'k' => 'a', 'v' => '64'],
        ['l' => 5, 'k' => 'p', 'v' => '65'],
        ['l' => 17, 'k' => 'a', 'v' => '66'],
        ['l' => 17, 'k' => 'u', 'v' => '77'],
        ['l' => 17, 'k' => 'u', 'v' => '110'],
        ['l' => 17, 'k' => 'u', 'v' => '78'],
        ['l' => 17, 'k' => 'u', 'v' => '87'],
        ['l' => 17, 'k' => 'a', 'v' => '68'],
        ['l' => 17, 'k' => 'a', 'v' => '108'],
        ['l' => 17, 'k' => 'a', 'v' => '69'],
        ['l' => 17, 'k' => 'u', 'v' => '70'],
        ['l' => 17, 'k' => 'u', 'v' => '109'],
        ['l' => 17, 'k' => 'u', 'v' => '112'],
        ['l' => 17, 'k' => 'a', 'v' => '71'],
        ['l' => 1, 'k' => 'id', 'v' => 'sin_actividad'],
        ['l' => 1, 'k' => 'id', 'v' => 'rectificativa'],
        ['l' => 13, 'k' => 'id', 'v' => 'justificante_ant'],
        ['l' => 1, 'k' => 'id', 'v' => 'baja_domiciliacion'],
        ['l' => 17, 'k' => 'u', 'v' => '111'],
        ['l' => 1, 'k' => 'id', 'v' => 'motivo_rect_1'],
        ['l' => 1, 'k' => 'id', 'v' => 'motivo_rect_2'],
        ['l' => 546, 'k' => 'b'],
        ['l' => 12, 'k' => 'c', 'v' => '</T30303000>'],
    ];

    /**
     * Mapa de campos de la página 3 del diseño de registro de 2025 (DR303e25, v1.02): no tiene la casilla
     * [112] (pago a cuenta de gasolinas), la [71] es [69] - [70] + [109] y la [108] va tras el número de
     * justificante de la autoliquidación anterior. Mide lo mismo que la de 2026 (1017 posiciones).
     */
    private const PAGE3_2025 = [
        ['l' => 2, 'k' => 'c', 'v' => '<T'],
        ['l' => 3, 'k' => 'c', 'v' => '303'],
        ['l' => 5, 'k' => 'c', 'v' => '03000'],
        ['l' => 1, 'k' => 'c', 'v' => '>'],
        ['l' => 17, 'k' => 'a', 'v' => '59'],
        ['l' => 17, 'k' => 'a', 'v' => '60'],
        ['l' => 17, 'k' => 'a', 'v' => '120'],
        ['l' => 17, 'k' => 'a', 'v' => '122'],
        ['l' => 17, 'k' => 'a', 'v' => '123'],
        ['l' => 17, 'k' => 'a', 'v' => '124'],
        ['l' => 17, 'k' => 'a', 'v' => '62'],
        ['l' => 17, 'k' => 'a', 'v' => '63'],
        ['l' => 17, 'k' => 'a', 'v' => '74'],
        ['l' => 17, 'k' => 'a', 'v' => '75'],
        ['l' => 17, 'k' => 'a', 'v' => '76'],
        ['l' => 17, 'k' => 'a', 'v' => '64'],
        ['l' => 5, 'k' => 'p', 'v' => '65'],
        ['l' => 17, 'k' => 'a', 'v' => '66'],
        ['l' => 17, 'k' => 'u', 'v' => '77'],
        ['l' => 17, 'k' => 'u', 'v' => '110'],
        ['l' => 17, 'k' => 'u', 'v' => '78'],
        ['l' => 17, 'k' => 'u', 'v' => '87'],
        ['l' => 17, 'k' => 'a', 'v' => '68'],
        ['l' => 17, 'k' => 'a', 'v' => '69'],
        ['l' => 17, 'k' => 'u', 'v' => '70'],
        ['l' => 17, 'k' => 'u', 'v' => '109'],
        ['l' => 17, 'k' => 'a', 'v' => '71'],
        ['l' => 1, 'k' => 'id', 'v' => 'sin_actividad'],
        ['l' => 1, 'k' => 'id', 'v' => 'rectificativa'],
        ['l' => 13, 'k' => 'id', 'v' => 'justificante_ant'],
        ['l' => 1, 'k' => 'id', 'v' => 'baja_domiciliacion'],
        ['l' => 17, 'k' => 'a', 'v' => '108'],
        ['l' => 17, 'k' => 'u', 'v' => '111'],
        ['l' => 120, 'k' => 'b'],
        ['l' => 1, 'k' => 'id', 'v' => 'motivo_rect_1'],
        ['l' => 1, 'k' => 'id', 'v' => 'motivo_rect_2'],
        ['l' => 443, 'k' => 'b'],
        ['l' => 12, 'k' => 'c', 'v' => '</T30303000>'],
    ];

    /** Primer ejercicio que usa el diseño de registro de 2026 (PAGE1 y PAGE3). */
    public const FIRST_YEAR_2026_LAYOUT = 2026;

    /**
     * Mapa de campos de la página DID (datos de domiciliación / devolución).
     */
    private const PAGE_DID = [
        ['l' => 2, 'k' => 'c', 'v' => '<T'],
        ['l' => 3, 'k' => 'c', 'v' => '303'],
        ['l' => 5, 'k' => 'c', 'v' => 'DID00'],
        ['l' => 1, 'k' => 'c', 'v' => '>'],
        ['l' => 11, 'k' => 'id', 'v' => 'swift'],
        ['l' => 34, 'k' => 'id', 'v' => 'iban'],
        ['l' => 70, 'k' => 'id', 'v' => 'banco_nombre'],
        ['l' => 35, 'k' => 'id', 'v' => 'banco_dir'],
        ['l' => 30, 'k' => 'id', 'v' => 'banco_ciudad'],
        ['l' => 2, 'k' => 'id', 'v' => 'banco_pais'],
        ['l' => 1, 'k' => 'id', 'v' => 'marca_sepa'],
        ['l' => 617, 'k' => 'b'],
        ['l' => 12, 'k' => 'c', 'v' => '</T303DID00>'],
    ];

    /**
     * Tipos de declaración que admiten datos bancarios en la página DID:
     * U (domiciliación del ingreso), D (devolución) y X (devolución por transferencia al extranjero).
     */
    private const TIPOS_CON_CUENTA = ['U', 'D', 'X'];

    /**
     * Tipos de declaración a devolver. Solo en estos la AEAT admite contenido en los campos
     * "Devolución - ..." de la página DID (p. ej. la marca SEPA).
     */
    private const TIPOS_DEVOLUCION = ['D', 'X'];

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
    public static function generate(RegularizacionImpuesto $reg, array $squares, Empresa $company): string
    {
        self::$reg = $reg;
        self::$squares = $squares;
        self::$company = $company;
        self::$year = date('Y', strtotime((string)$reg->fechainicio));
        self::$period = self::periodAeat((string)$reg->periodo);

        // cabecera + páginas + cierre
        $content = self::buildCabecera()
            . self::buildPage(self::page1((int)self::$year))
            . self::buildPage(self::page3((int)self::$year));

        // la página de domiciliación/devolución solo se incluye si hay IBAN y la declaración
        // es de domiciliación o devolución (en el resto de tipos la AEAT no admite datos bancarios)
        if (false === empty($reg->iban) && in_array(self::tipoDeclaracion(), self::TIPOS_CON_CUENTA, true)) {
            $content .= self::buildPage(self::PAGE_DID);
        }

        $content .= self::closeCabecera();

        return mb_convert_encoding($content, 'ISO-8859-1', 'UTF-8');
    }

    /**
     * Devuelve el mapa de campos de la página 1 del diseño de registro del ejercicio. El de 2025 se
     * obtiene del de 2026 con sus tres diferencias: no tiene el indicador de gasolinas (posición 130),
     * las casillas [165]-[167] y [168]-[170] van al final, tras la [46], y el bloque reservado para la
     * AEAT mide 522 posiciones en lugar de 521. Así ambos miden 1581 posiciones.
     *
     * @param int $year ejercicio de la regularización.
     * @return array
     */
    public static function page1(int $year): array
    {
        if ($year >= self::FIRST_YEAR_2026_LAYOUT) {
            return self::PAGE1;
        }

        $fields = self::PAGE1;
        $page = [];
        $tail = [];
        $count = count($fields);
        for ($i = 0; $i < $count; $i++) {
            $field = $fields[$i];
            if (($field['v'] ?? '') === 'ind_gasolinas') {
                continue;
            }

            // las casillas [165]-[167] y [168]-[170] (base, tipo constante y cuota) van al final
            if ($field['k'] === 'u' && in_array($field['v'], ['165', '168'], true)) {
                array_push($tail, ...array_slice($fields, $i, 3));
                $i += 2;
                continue;
            }

            if ($field['k'] === 'b' && $field['l'] === 521) {
                $field['l'] = 522;
            }

            $page[] = $field;
            if ($field['k'] === 'a' && $field['v'] === '46') {
                array_push($page, ...$tail);
            }
        }

        return $page;
    }

    /**
     * Devuelve el mapa de campos de la página 3 del diseño de registro del ejercicio.
     *
     * @param int $year ejercicio de la regularización.
     * @return array
     */
    public static function page3(int $year): array
    {
        return $year >= self::FIRST_YEAR_2026_LAYOUT ? self::PAGE3 : self::PAGE3_2025;
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
     * Convierte el período de RegularizacionImpuesto (T1..T4, 01..12) al formato AEAT (1T..4T, 01..12).
     *
     * @param string $periodo
     * @return string
     * @throws \InvalidArgumentException si el periodo no es un trimestre ni un mes (por ejemplo, el anual Y)
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

        // el 303 no tiene periodo anual (el resumen anual es el modelo 390)
        throw new \InvalidArgumentException('El modelo 303 no admite el periodo ' . $periodo . ': solo trimestres (T1-T4) y meses (01-12).');
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
                // La AEAT solo admite contenido si la declaración es a devolver (error EDID113).
                $iban = strtoupper(trim((string)self::$reg->iban));
                if (empty($iban) || false === in_array(self::tipoDeclaracion(), self::TIPOS_DEVOLUCION, true)) {
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
