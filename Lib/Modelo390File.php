<?php
/**
 * This file is part of Modelo303 plugin for FacturaScripts
 * Copyright (C) 2026 Carlos Garcia Gomez <carlos@facturascripts.com>
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
use InvalidArgumentException;
use RuntimeException;

/**
 * Genera el fichero de texto del modelo 390 (declaración-resumen anual del IVA) para su
 * presentación telemática en la AEAT, según el diseño de registro oficial DR390.
 *
 * Hay dos diseños, elegidos por el año del ejercicio:
 *   - 2024: ejercicios 2024 y 2025 (dr390e2024.xlsx y dr390e2025.xlsx, idénticos en posiciones).
 *   - 2026: ejercicio 2026 y siguientes (dr390e2026.xlsx, v1.00): añade el indicador del pago a cuenta
 *     de carburantes en la página 01000, la casilla 112 en la 06000 y el CNAE de 4 caracteres en la 07000.
 *
 * Estructura del fichero (sin separadores entre páginas):
 *   <T3900EEEE0A0000><AUX>...</AUX>   cabecera (328 posiciones)
 *   <T39001000>...</T39001000>        identificación, devengo y datos estadísticos
 *   <T39002000>...</T39002000>        IVA devengado
 *   <T39002B00>...</T39002B00>        recargo de equivalencia y total devengado
 *   <T39003000>...</T39003000>        IVA deducible (I)
 *   <T39004000>...</T39004000>        IVA deducible (II) y resultado del régimen general
 *   <T39006000>...</T39006000>        resultado anual, resultado de las liquidaciones y volumen de operaciones
 *   <T39007000>...</T39007000>        operaciones específicas y prorratas
 *   </T3900EEEE0A0000>                cierre
 *
 * No se generan las páginas 05000 (régimen simplificado) ni 08000 (sectores diferenciados), que el
 * plugin no calcula. Las casillas se toman de Modelo390::getSquares() y los datos de identificación
 * de la empresa y de la regularización anual.
 *
 * Cada campo de los diseños es [longitud, tipo, valor], con estos tipos:
 *   c  => constante literal
 *   id => dato de identificación resuelto por resolveId()
 *   n  => importe con signo (N): 'N' en la primera posición si es negativo
 *   u  => importe sin signo (Num)
 *   p  => porcentaje (3 enteros y 2 decimales)
 *   z  => numérico que no calcula el plugin (ceros)
 *   b  => alfanumérico que no calcula el plugin o reservado para la AEAT (blancos)
 *
 * @author Daniel Fernández Giménez <contacto@danielfg.es>
 */
class Modelo390File
{
    use Modelo303FileTrait;

    /** Primer ejercicio con diseño de registro disponible. */
    public const FIRST_YEAR = 2024;

    /**
     * Diseños de registro por primer ejercicio de aplicación y página, generados a partir de los
     * Excel oficiales de la AEAT. El comentario de cada página indica su longitud total.
     */
    public const LAYOUTS = [
        '2024' => [
            '01000' => [ // 1187 posiciones
                [2, 'c', '<T'],
                [3, 'c', '390'],
                [5, 'c', '01000'],
                [1, 'c', '>'],
                [1, 'b', ''],
                [1, 'b', ''],
                [9, 'id', 'nif'],
                [60, 'id', 'razon'],
                [20, 'id', 'nombre'],
                [4, 'id', 'ejercicio'],
                [2, 'b', ''],
                [1, 'id', 'cero'],
                [1, 'id', 'cero'],
                [7, 'b', ''],
                [1, 'id', 'cero'],
                [1, 'id', 'cero'],
                [1, 'id', 'cero'],
                [9, 'b', ''],
                [1, 'id', 'no'],
                [1, 'id', 'caja'],
                [1, 'id', 'no'],
                [1, 'id', 'sustitutiva'],
                [1, 'id', 'cero'],
                [13, 'id', 'justificante'],
                [40, 'id', 'actividad'],
                [3, 'id', 'claveactividad'],
                [4, 'id', 'epigrafe'],
                [40, 'b', ''],
                [3, 'b', ''],
                [4, 'b', ''],
                [40, 'b', ''],
                [3, 'b', ''],
                [4, 'b', ''],
                [40, 'b', ''],
                [3, 'b', ''],
                [4, 'b', ''],
                [40, 'b', ''],
                [3, 'b', ''],
                [4, 'b', ''],
                [40, 'b', ''],
                [3, 'b', ''],
                [4, 'b', ''],
                [1, 'id', 'op347'],
                [9, 'b', ''],
                [37, 'b', ''],
                [9, 'b', ''],
                [80, 'b', ''],
                [2, 'b', ''],
                [17, 'b', ''],
                [5, 'b', ''],
                [2, 'b', ''],
                [2, 'b', ''],
                [2, 'b', ''],
                [9, 'b', ''],
                [20, 'b', ''],
                [15, 'b', ''],
                [5, 'b', ''],
                [80, 'b', ''],
                [9, 'b', ''],
                [8, 'z', ''],
                [12, 'b', ''],
                [80, 'b', ''],
                [9, 'b', ''],
                [8, 'z', ''],
                [12, 'b', ''],
                [80, 'b', ''],
                [9, 'b', ''],
                [8, 'z', ''],
                [12, 'b', ''],
                [21, 'b', ''],
                [13, 'b', ''],
                [20, 'b', ''],
                [150, 'b', ''],
                [12, 'c', '</T39001000>'],
            ],
            '02000' => [ // 1806 posiciones
                [2, 'c', '<T'],
                [3, 'c', '390'],
                [5, 'c', '02000'],
                [1, 'c', '>'],
                [1, 'b', ''],
                [17, 'n', '700'],
                [17, 'n', '701'],
                [17, 'n', '667'],
                [17, 'n', '668'],
                [17, 'n', '01'],
                [17, 'n', '02'],
                [17, 'n', '702'],
                [17, 'n', '703'],
                [17, 'n', '669'],
                [17, 'n', '670'],
                [17, 'n', '03'],
                [17, 'n', '04'],
                [17, 'n', '05'],
                [17, 'n', '06'],
                [17, 'n', '704'],
                [17, 'n', '705'],
                [17, 'n', '671'],
                [17, 'n', '672'],
                [17, 'n', '500'],
                [17, 'n', '501'],
                [17, 'n', '706'],
                [17, 'n', '707'],
                [17, 'n', '673'],
                [17, 'n', '674'],
                [17, 'n', '502'],
                [17, 'n', '503'],
                [17, 'n', '504'],
                [17, 'n', '505'],
                [17, 'n', '708'],
                [17, 'n', '709'],
                [17, 'n', '675'],
                [17, 'n', '676'],
                [17, 'n', '643'],
                [17, 'n', '644'],
                [17, 'n', '710'],
                [17, 'n', '711'],
                [17, 'n', '677'],
                [17, 'n', '678'],
                [17, 'n', '645'],
                [17, 'n', '646'],
                [17, 'n', '647'],
                [17, 'n', '648'],
                [17, 'n', '712'],
                [17, 'n', '713'],
                [17, 'n', '679'],
                [17, 'n', '680'],
                [17, 'n', '07'],
                [17, 'n', '08'],
                [17, 'n', '714'],
                [17, 'n', '715'],
                [17, 'n', '681'],
                [17, 'n', '682'],
                [17, 'n', '09'],
                [17, 'n', '10'],
                [17, 'n', '11'],
                [17, 'n', '12'],
                [17, 'n', '13'],
                [17, 'n', '14'],
                [17, 'n', '716'],
                [17, 'n', '717'],
                [17, 'n', '683'],
                [17, 'n', '684'],
                [17, 'n', '21'],
                [17, 'n', '22'],
                [17, 'n', '718'],
                [17, 'n', '719'],
                [17, 'n', '685'],
                [17, 'n', '686'],
                [17, 'n', '23'],
                [17, 'n', '24'],
                [17, 'n', '25'],
                [17, 'n', '26'],
                [17, 'n', '720'],
                [17, 'n', '721'],
                [17, 'n', '687'],
                [17, 'n', '688'],
                [17, 'n', '545'],
                [17, 'n', '546'],
                [17, 'n', '722'],
                [17, 'n', '723'],
                [17, 'n', '689'],
                [17, 'n', '690'],
                [17, 'n', '547'],
                [17, 'n', '548'],
                [17, 'n', '551'],
                [17, 'n', '552'],
                [17, 'n', '27'],
                [17, 'n', '28'],
                [17, 'n', '29'],
                [17, 'n', '30'],
                [17, 'n', '649'],
                [17, 'n', '650'],
                [17, 'n', '31'],
                [17, 'n', '32'],
                [17, 'n', '33'],
                [17, 'n', '34'],
                [150, 'b', ''],
                [12, 'c', '</T39002000>'],
            ],
            '02B00' => [ // 531 posiciones
                [2, 'c', '<T'],
                [3, 'c', '390'],
                [5, 'c', '02B00'],
                [1, 'c', '>'],
                [1, 'b', ''],
                [17, 'n', '663'],
                [17, 'n', '664'],
                [17, 'n', '691'],
                [17, 'n', '692'],
                [17, 'n', '35'],
                [17, 'n', '36'],
                [17, 'n', '665'],
                [17, 'n', '666'],
                [17, 'n', '693'],
                [17, 'n', '694'],
                [17, 'n', '599'],
                [17, 'n', '600'],
                [17, 'n', '601'],
                [17, 'n', '602'],
                [17, 'n', '41'],
                [17, 'n', '42'],
                [17, 'n', '43'],
                [17, 'n', '44'],
                [17, 'n', '45'],
                [17, 'n', '46'],
                [17, 'n', '47'],
                [150, 'b', ''],
                [12, 'c', '</T39002B00>'],
            ],
            '03000' => [ // 1840 posiciones
                [2, 'c', '<T'],
                [3, 'c', '390'],
                [5, 'c', '03000'],
                [1, 'c', '>'],
                [1, 'b', ''],
                [17, 'n', '695'],
                [17, 'n', '696'],
                [17, 'n', '190'],
                [17, 'n', '191'],
                [17, 'n', '724'],
                [17, 'n', '725'],
                [17, 'n', '697'],
                [17, 'n', '698'],
                [17, 'n', '603'],
                [17, 'n', '604'],
                [17, 'n', '605'],
                [17, 'n', '606'],
                [17, 'n', '48'],
                [17, 'n', '49'],
                [17, 'n', '745'],
                [17, 'n', '746'],
                [17, 'n', '506'],
                [17, 'n', '507'],
                [17, 'n', '726'],
                [17, 'n', '727'],
                [17, 'n', '747'],
                [17, 'n', '748'],
                [17, 'n', '607'],
                [17, 'n', '608'],
                [17, 'n', '609'],
                [17, 'n', '610'],
                [17, 'n', '512'],
                [17, 'n', '513'],
                [17, 'n', '749'],
                [17, 'n', '750'],
                [17, 'n', '196'],
                [17, 'n', '197'],
                [17, 'n', '728'],
                [17, 'n', '729'],
                [17, 'n', '751'],
                [17, 'n', '752'],
                [17, 'n', '611'],
                [17, 'n', '612'],
                [17, 'n', '613'],
                [17, 'n', '614'],
                [17, 'n', '50'],
                [17, 'n', '51'],
                [17, 'n', '753'],
                [17, 'n', '754'],
                [17, 'n', '514'],
                [17, 'n', '515'],
                [17, 'n', '730'],
                [17, 'n', '731'],
                [17, 'n', '755'],
                [17, 'n', '756'],
                [17, 'n', '615'],
                [17, 'n', '616'],
                [17, 'n', '617'],
                [17, 'n', '618'],
                [17, 'n', '520'],
                [17, 'n', '521'],
                [17, 'n', '757'],
                [17, 'n', '758'],
                [17, 'n', '202'],
                [17, 'n', '203'],
                [17, 'n', '732'],
                [17, 'n', '733'],
                [17, 'n', '759'],
                [17, 'n', '760'],
                [17, 'n', '619'],
                [17, 'n', '620'],
                [17, 'n', '621'],
                [17, 'n', '622'],
                [17, 'n', '52'],
                [17, 'n', '53'],
                [17, 'n', '761'],
                [17, 'n', '762'],
                [17, 'n', '208'],
                [17, 'n', '209'],
                [17, 'n', '734'],
                [17, 'n', '735'],
                [17, 'n', '763'],
                [17, 'n', '764'],
                [17, 'n', '623'],
                [17, 'n', '624'],
                [17, 'n', '625'],
                [17, 'n', '626'],
                [17, 'n', '54'],
                [17, 'n', '55'],
                [17, 'n', '765'],
                [17, 'n', '766'],
                [17, 'n', '214'],
                [17, 'n', '215'],
                [17, 'n', '736'],
                [17, 'n', '737'],
                [17, 'n', '767'],
                [17, 'n', '768'],
                [17, 'n', '627'],
                [17, 'n', '628'],
                [17, 'n', '629'],
                [17, 'n', '630'],
                [17, 'n', '56'],
                [17, 'n', '57'],
                [150, 'b', ''],
                [12, 'c', '</T39003000>'],
            ],
            '04000' => [ // 854 posiciones
                [2, 'c', '<T'],
                [3, 'c', '390'],
                [5, 'c', '04000'],
                [1, 'c', '>'],
                [1, 'b', ''],
                [17, 'n', '769'],
                [17, 'n', '770'],
                [17, 'n', '220'],
                [17, 'n', '221'],
                [17, 'n', '738'],
                [17, 'n', '739'],
                [17, 'n', '771'],
                [17, 'n', '772'],
                [17, 'n', '631'],
                [17, 'n', '632'],
                [17, 'n', '633'],
                [17, 'n', '634'],
                [17, 'n', '58'],
                [17, 'n', '59'],
                [17, 'n', '773'],
                [17, 'n', '774'],
                [17, 'n', '587'],
                [17, 'n', '588'],
                [17, 'n', '740'],
                [17, 'n', '741'],
                [17, 'n', '775'],
                [17, 'n', '776'],
                [17, 'n', '635'],
                [17, 'n', '636'],
                [17, 'n', '637'],
                [17, 'n', '638'],
                [17, 'n', '597'],
                [17, 'n', '598'],
                [17, 'n', '60'],
                [17, 'n', '61'],
                [17, 'n', '660'],
                [17, 'n', '661'],
                [17, 'n', '639'],
                [17, 'n', '62'],
                [17, 'n', '651'],
                [17, 'n', '652'],
                [17, 'n', '63'],
                [17, 'n', '522'],
                [17, 'n', '64'],
                [17, 'n', '65'],
                [150, 'b', ''],
                [12, 'c', '</T39004000>'],
            ],
            '06000' => [ // 828 posiciones
                [2, 'c', '<T'],
                [3, 'c', '390'],
                [5, 'c', '06000'],
                [1, 'c', '>'],
                [1, 'b', ''],
                [17, 'n', '658'],
                [17, 'n', '84'],
                [17, 'u', '659'],
                [17, 'u', '85'],
                [17, 'n', '86'],
                [5, 'z', ''],
                [5, 'z', ''],
                [5, 'z', ''],
                [5, 'z', ''],
                [5, 'z', ''],
                [17, 'z', ''],
                [17, 'z', ''],
                [17, 'z', ''],
                [17, 'z', ''],
                [17, 'z', ''],
                [17, 'z', ''],
                [17, 'u', '95'],
                [17, 'u', '96'],
                [17, 'u', '524'],
                [17, 'u', '97'],
                [17, 'u', '98'],
                [17, 'u', '662'],
                [17, 'u', '525'],
                [17, 'u', '526'],
                [17, 'n', '99'],
                [17, 'n', '653'],
                [17, 'n', '103'],
                [17, 'n', '104'],
                [17, 'n', '105'],
                [17, 'n', '110'],
                [17, 'n', '125'],
                [17, 'n', '126'],
                [17, 'n', '127'],
                [17, 'n', '128'],
                [17, 'n', '100'],
                [17, 'n', '101'],
                [17, 'n', '102'],
                [17, 'n', '227'],
                [17, 'n', '228'],
                [17, 'n', '106'],
                [17, 'n', '107'],
                [17, 'n', '108'],
                [150, 'b', ''],
                [12, 'c', '</T39006000>'],
            ],
            '07000' => [ // 776 posiciones
                [2, 'c', '<T'],
                [3, 'c', '390'],
                [5, 'c', '07000'],
                [1, 'c', '>'],
                [1, 'b', ''],
                [17, 'n', '230'],
                [17, 'n', '109'],
                [17, 'n', '231'],
                [17, 'n', '232'],
                [17, 'n', '111'],
                [17, 'n', '113'],
                [17, 'n', '523'],
                [17, 'n', '654'],
                [17, 'n', '655'],
                [17, 'n', '656'],
                [17, 'n', '657'],
                [40, 'b', ''],
                [3, 'b', ''],
                [17, 'z', ''],
                [17, 'z', ''],
                [1, 'b', ''],
                [5, 'z', ''],
                [40, 'b', ''],
                [3, 'b', ''],
                [17, 'z', ''],
                [17, 'z', ''],
                [1, 'b', ''],
                [5, 'z', ''],
                [40, 'b', ''],
                [3, 'b', ''],
                [17, 'z', ''],
                [17, 'z', ''],
                [1, 'b', ''],
                [5, 'z', ''],
                [40, 'b', ''],
                [3, 'b', ''],
                [17, 'z', ''],
                [17, 'z', ''],
                [1, 'b', ''],
                [5, 'z', ''],
                [40, 'b', ''],
                [3, 'b', ''],
                [17, 'z', ''],
                [17, 'z', ''],
                [1, 'b', ''],
                [5, 'z', ''],
                [150, 'b', ''],
                [12, 'c', '</T39007000>'],
            ],
        ],
        '2026' => [
            '01000' => [ // 1188 posiciones
                [2, 'c', '<T'],
                [3, 'c', '390'],
                [5, 'c', '01000'],
                [1, 'c', '>'],
                [1, 'b', ''],
                [1, 'b', ''],
                [9, 'id', 'nif'],
                [60, 'id', 'razon'],
                [20, 'id', 'nombre'],
                [4, 'id', 'ejercicio'],
                [2, 'b', ''],
                [1, 'id', 'cero'],
                [1, 'id', 'cero'],
                [7, 'b', ''],
                [1, 'id', 'cero'],
                [1, 'id', 'cero'],
                [1, 'id', 'cero'],
                [9, 'b', ''],
                [1, 'id', 'no'],
                [1, 'id', 'caja'],
                [1, 'id', 'no'],
                [1, 'id', 'carburantes'],
                [1, 'id', 'sustitutiva'],
                [1, 'id', 'cero'],
                [13, 'id', 'justificante'],
                [40, 'id', 'actividad'],
                [3, 'id', 'claveactividad'],
                [4, 'id', 'epigrafe'],
                [40, 'b', ''],
                [3, 'b', ''],
                [4, 'b', ''],
                [40, 'b', ''],
                [3, 'b', ''],
                [4, 'b', ''],
                [40, 'b', ''],
                [3, 'b', ''],
                [4, 'b', ''],
                [40, 'b', ''],
                [3, 'b', ''],
                [4, 'b', ''],
                [40, 'b', ''],
                [3, 'b', ''],
                [4, 'b', ''],
                [1, 'id', 'op347'],
                [9, 'b', ''],
                [37, 'b', ''],
                [9, 'b', ''],
                [80, 'b', ''],
                [2, 'b', ''],
                [17, 'b', ''],
                [5, 'b', ''],
                [2, 'b', ''],
                [2, 'b', ''],
                [2, 'b', ''],
                [9, 'b', ''],
                [20, 'b', ''],
                [15, 'b', ''],
                [5, 'b', ''],
                [80, 'b', ''],
                [9, 'b', ''],
                [8, 'z', ''],
                [12, 'b', ''],
                [80, 'b', ''],
                [9, 'b', ''],
                [8, 'z', ''],
                [12, 'b', ''],
                [80, 'b', ''],
                [9, 'b', ''],
                [8, 'z', ''],
                [12, 'b', ''],
                [21, 'b', ''],
                [13, 'b', ''],
                [20, 'b', ''],
                [150, 'b', ''],
                [12, 'c', '</T39001000>'],
            ],
            '02000' => [ // 1806 posiciones
                [2, 'c', '<T'],
                [3, 'c', '390'],
                [5, 'c', '02000'],
                [1, 'c', '>'],
                [1, 'b', ''],
                [17, 'n', '700'],
                [17, 'n', '701'],
                [17, 'n', '667'],
                [17, 'n', '668'],
                [17, 'n', '01'],
                [17, 'n', '02'],
                [17, 'n', '702'],
                [17, 'n', '703'],
                [17, 'n', '669'],
                [17, 'n', '670'],
                [17, 'n', '03'],
                [17, 'n', '04'],
                [17, 'n', '05'],
                [17, 'n', '06'],
                [17, 'n', '704'],
                [17, 'n', '705'],
                [17, 'n', '671'],
                [17, 'n', '672'],
                [17, 'n', '500'],
                [17, 'n', '501'],
                [17, 'n', '706'],
                [17, 'n', '707'],
                [17, 'n', '673'],
                [17, 'n', '674'],
                [17, 'n', '502'],
                [17, 'n', '503'],
                [17, 'n', '504'],
                [17, 'n', '505'],
                [17, 'n', '708'],
                [17, 'n', '709'],
                [17, 'n', '675'],
                [17, 'n', '676'],
                [17, 'n', '643'],
                [17, 'n', '644'],
                [17, 'n', '710'],
                [17, 'n', '711'],
                [17, 'n', '677'],
                [17, 'n', '678'],
                [17, 'n', '645'],
                [17, 'n', '646'],
                [17, 'n', '647'],
                [17, 'n', '648'],
                [17, 'n', '712'],
                [17, 'n', '713'],
                [17, 'n', '679'],
                [17, 'n', '680'],
                [17, 'n', '07'],
                [17, 'n', '08'],
                [17, 'n', '714'],
                [17, 'n', '715'],
                [17, 'n', '681'],
                [17, 'n', '682'],
                [17, 'n', '09'],
                [17, 'n', '10'],
                [17, 'n', '11'],
                [17, 'n', '12'],
                [17, 'n', '13'],
                [17, 'n', '14'],
                [17, 'n', '716'],
                [17, 'n', '717'],
                [17, 'n', '683'],
                [17, 'n', '684'],
                [17, 'n', '21'],
                [17, 'n', '22'],
                [17, 'n', '718'],
                [17, 'n', '719'],
                [17, 'n', '685'],
                [17, 'n', '686'],
                [17, 'n', '23'],
                [17, 'n', '24'],
                [17, 'n', '25'],
                [17, 'n', '26'],
                [17, 'n', '720'],
                [17, 'n', '721'],
                [17, 'n', '687'],
                [17, 'n', '688'],
                [17, 'n', '545'],
                [17, 'n', '546'],
                [17, 'n', '722'],
                [17, 'n', '723'],
                [17, 'n', '689'],
                [17, 'n', '690'],
                [17, 'n', '547'],
                [17, 'n', '548'],
                [17, 'n', '551'],
                [17, 'n', '552'],
                [17, 'n', '27'],
                [17, 'n', '28'],
                [17, 'n', '29'],
                [17, 'n', '30'],
                [17, 'n', '649'],
                [17, 'n', '650'],
                [17, 'n', '31'],
                [17, 'n', '32'],
                [17, 'n', '33'],
                [17, 'n', '34'],
                [150, 'b', ''],
                [12, 'c', '</T39002000>'],
            ],
            '02B00' => [ // 531 posiciones
                [2, 'c', '<T'],
                [3, 'c', '390'],
                [5, 'c', '02B00'],
                [1, 'c', '>'],
                [1, 'b', ''],
                [17, 'n', '663'],
                [17, 'n', '664'],
                [17, 'n', '691'],
                [17, 'n', '692'],
                [17, 'n', '35'],
                [17, 'n', '36'],
                [17, 'n', '665'],
                [17, 'n', '666'],
                [17, 'n', '693'],
                [17, 'n', '694'],
                [17, 'n', '599'],
                [17, 'n', '600'],
                [17, 'n', '601'],
                [17, 'n', '602'],
                [17, 'n', '41'],
                [17, 'n', '42'],
                [17, 'n', '43'],
                [17, 'n', '44'],
                [17, 'n', '45'],
                [17, 'n', '46'],
                [17, 'n', '47'],
                [150, 'b', ''],
                [12, 'c', '</T39002B00>'],
            ],
            '03000' => [ // 1840 posiciones
                [2, 'c', '<T'],
                [3, 'c', '390'],
                [5, 'c', '03000'],
                [1, 'c', '>'],
                [1, 'b', ''],
                [17, 'n', '695'],
                [17, 'n', '696'],
                [17, 'n', '190'],
                [17, 'n', '191'],
                [17, 'n', '724'],
                [17, 'n', '725'],
                [17, 'n', '697'],
                [17, 'n', '698'],
                [17, 'n', '603'],
                [17, 'n', '604'],
                [17, 'n', '605'],
                [17, 'n', '606'],
                [17, 'n', '48'],
                [17, 'n', '49'],
                [17, 'n', '745'],
                [17, 'n', '746'],
                [17, 'n', '506'],
                [17, 'n', '507'],
                [17, 'n', '726'],
                [17, 'n', '727'],
                [17, 'n', '747'],
                [17, 'n', '748'],
                [17, 'n', '607'],
                [17, 'n', '608'],
                [17, 'n', '609'],
                [17, 'n', '610'],
                [17, 'n', '512'],
                [17, 'n', '513'],
                [17, 'n', '749'],
                [17, 'n', '750'],
                [17, 'n', '196'],
                [17, 'n', '197'],
                [17, 'n', '728'],
                [17, 'n', '729'],
                [17, 'n', '751'],
                [17, 'n', '752'],
                [17, 'n', '611'],
                [17, 'n', '612'],
                [17, 'n', '613'],
                [17, 'n', '614'],
                [17, 'n', '50'],
                [17, 'n', '51'],
                [17, 'n', '753'],
                [17, 'n', '754'],
                [17, 'n', '514'],
                [17, 'n', '515'],
                [17, 'n', '730'],
                [17, 'n', '731'],
                [17, 'n', '755'],
                [17, 'n', '756'],
                [17, 'n', '615'],
                [17, 'n', '616'],
                [17, 'n', '617'],
                [17, 'n', '618'],
                [17, 'n', '520'],
                [17, 'n', '521'],
                [17, 'n', '757'],
                [17, 'n', '758'],
                [17, 'n', '202'],
                [17, 'n', '203'],
                [17, 'n', '732'],
                [17, 'n', '733'],
                [17, 'n', '759'],
                [17, 'n', '760'],
                [17, 'n', '619'],
                [17, 'n', '620'],
                [17, 'n', '621'],
                [17, 'n', '622'],
                [17, 'n', '52'],
                [17, 'n', '53'],
                [17, 'n', '761'],
                [17, 'n', '762'],
                [17, 'n', '208'],
                [17, 'n', '209'],
                [17, 'n', '734'],
                [17, 'n', '735'],
                [17, 'n', '763'],
                [17, 'n', '764'],
                [17, 'n', '623'],
                [17, 'n', '624'],
                [17, 'n', '625'],
                [17, 'n', '626'],
                [17, 'n', '54'],
                [17, 'n', '55'],
                [17, 'n', '765'],
                [17, 'n', '766'],
                [17, 'n', '214'],
                [17, 'n', '215'],
                [17, 'n', '736'],
                [17, 'n', '737'],
                [17, 'n', '767'],
                [17, 'n', '768'],
                [17, 'n', '627'],
                [17, 'n', '628'],
                [17, 'n', '629'],
                [17, 'n', '630'],
                [17, 'n', '56'],
                [17, 'n', '57'],
                [150, 'b', ''],
                [12, 'c', '</T39003000>'],
            ],
            '04000' => [ // 854 posiciones
                [2, 'c', '<T'],
                [3, 'c', '390'],
                [5, 'c', '04000'],
                [1, 'c', '>'],
                [1, 'b', ''],
                [17, 'n', '769'],
                [17, 'n', '770'],
                [17, 'n', '220'],
                [17, 'n', '221'],
                [17, 'n', '738'],
                [17, 'n', '739'],
                [17, 'n', '771'],
                [17, 'n', '772'],
                [17, 'n', '631'],
                [17, 'n', '632'],
                [17, 'n', '633'],
                [17, 'n', '634'],
                [17, 'n', '58'],
                [17, 'n', '59'],
                [17, 'n', '773'],
                [17, 'n', '774'],
                [17, 'n', '587'],
                [17, 'n', '588'],
                [17, 'n', '740'],
                [17, 'n', '741'],
                [17, 'n', '775'],
                [17, 'n', '776'],
                [17, 'n', '635'],
                [17, 'n', '636'],
                [17, 'n', '637'],
                [17, 'n', '638'],
                [17, 'n', '597'],
                [17, 'n', '598'],
                [17, 'n', '60'],
                [17, 'n', '61'],
                [17, 'n', '660'],
                [17, 'n', '661'],
                [17, 'n', '639'],
                [17, 'n', '62'],
                [17, 'n', '651'],
                [17, 'n', '652'],
                [17, 'n', '63'],
                [17, 'n', '522'],
                [17, 'n', '64'],
                [17, 'n', '65'],
                [150, 'b', ''],
                [12, 'c', '</T39004000>'],
            ],
            '06000' => [ // 862 posiciones
                [2, 'c', '<T'],
                [3, 'c', '390'],
                [5, 'c', '06000'],
                [1, 'c', '>'],
                [1, 'b', ''],
                [17, 'n', '658'],
                [17, 'n', '84'],
                [17, 'u', '659'],
                [17, 'u', '85'],
                [17, 'u', '112'],
                [17, 'n', '86'],
                [5, 'z', ''],
                [5, 'z', ''],
                [5, 'z', ''],
                [5, 'z', ''],
                [5, 'z', ''],
                [17, 'z', ''],
                [17, 'z', ''],
                [17, 'z', ''],
                [17, 'z', ''],
                [17, 'z', ''],
                [17, 'z', ''],
                [17, 'z', ''],
                [17, 'u', '95'],
                [17, 'u', '96'],
                [17, 'u', '524'],
                [17, 'u', '97'],
                [17, 'u', '98'],
                [17, 'u', '662'],
                [17, 'u', '525'],
                [17, 'u', '526'],
                [17, 'n', '99'],
                [17, 'n', '653'],
                [17, 'n', '103'],
                [17, 'n', '104'],
                [17, 'n', '105'],
                [17, 'n', '110'],
                [17, 'n', '125'],
                [17, 'n', '126'],
                [17, 'n', '127'],
                [17, 'n', '128'],
                [17, 'n', '100'],
                [17, 'n', '101'],
                [17, 'n', '102'],
                [17, 'n', '227'],
                [17, 'n', '228'],
                [17, 'n', '106'],
                [17, 'n', '107'],
                [17, 'n', '108'],
                [150, 'b', ''],
                [12, 'c', '</T39006000>'],
            ],
            '07000' => [ // 781 posiciones
                [2, 'c', '<T'],
                [3, 'c', '390'],
                [5, 'c', '07000'],
                [1, 'c', '>'],
                [1, 'b', ''],
                [17, 'n', '230'],
                [17, 'n', '109'],
                [17, 'n', '231'],
                [17, 'n', '232'],
                [17, 'n', '111'],
                [17, 'n', '113'],
                [17, 'n', '523'],
                [17, 'n', '654'],
                [17, 'n', '655'],
                [17, 'n', '656'],
                [17, 'n', '657'],
                [40, 'b', ''],
                [4, 'b', ''],
                [17, 'z', ''],
                [17, 'z', ''],
                [1, 'b', ''],
                [5, 'z', ''],
                [40, 'b', ''],
                [4, 'b', ''],
                [17, 'z', ''],
                [17, 'z', ''],
                [1, 'b', ''],
                [5, 'z', ''],
                [40, 'b', ''],
                [4, 'b', ''],
                [17, 'z', ''],
                [17, 'z', ''],
                [1, 'b', ''],
                [5, 'z', ''],
                [40, 'b', ''],
                [4, 'b', ''],
                [17, 'z', ''],
                [17, 'z', ''],
                [1, 'b', ''],
                [5, 'z', ''],
                [40, 'b', ''],
                [4, 'b', ''],
                [17, 'z', ''],
                [17, 'z', ''],
                [1, 'b', ''],
                [5, 'z', ''],
                [150, 'b', ''],
                [12, 'c', '</T39007000>'],
            ],
        ],
    ];

    /** @var Empresa */
    private static Empresa $company;

    /** @var RegularizacionImpuesto */
    private static RegularizacionImpuesto $reg;

    /** @var array<string, float> casillas calculadas (clave = nº de casilla AEAT) */
    private static array $squares = [];

    /** @var string ejercicio en 4 dígitos (EEEE) */
    private static string $year = '';

    /**
     * Genera el contenido completo del fichero del modelo 390 (codificado en ISO-8859-1).
     *
     * @param RegularizacionImpuesto $reg regularización anual (periodo Y).
     * @param array<string, float> $squares casillas calculadas por Modelo390::getSquares().
     * @param Empresa $company
     * @return string
     * @throws InvalidArgumentException si la regularización no es anual o el ejercicio no tiene diseño.
     */
    public static function generate(RegularizacionImpuesto $reg, array $squares, Empresa $company): string
    {
        if ($reg->periodo !== 'Y') {
            throw new InvalidArgumentException('El modelo 390 solo se genera para la regularización anual (periodo Y).');
        }

        self::$reg = $reg;
        self::$squares = $squares;
        self::$company = $company;
        self::$year = date('Y', strtotime((string)$reg->fechainicio));

        $content = self::buildCabecera();
        foreach (self::layout((int)self::$year) as $page => $fields) {
            $content .= self::buildPage((string)$page, $fields);
        }
        $content .= '</T3900' . self::$year . '0A0000>';

        return mb_convert_encoding($content, 'ISO-8859-1', 'UTF-8');
    }

    /**
     * Devuelve el diseño de registro (páginas y campos) aplicable al ejercicio indicado.
     *
     * @param int $year
     * @return array<string, array<int, array{0: int, 1: string, 2: string}>>
     * @throws InvalidArgumentException si el ejercicio es anterior al primer diseño disponible.
     */
    public static function layout(int $year): array
    {
        if ($year < self::FIRST_YEAR) {
            throw new InvalidArgumentException('No hay diseño de registro del modelo 390 para el ejercicio ' . $year
                . ': el plugin solo lo genera a partir del ejercicio ' . self::FIRST_YEAR . '.');
        }

        $selected = [];
        foreach (self::LAYOUTS as $from => $layout) {
            if ($year >= (int)$from) {
                $selected = $layout;
            }
        }

        return $selected;
    }

    /**
     * Construye la etiqueta de apertura y el bloque AUX de la cabecera (posiciones 1-328).
     *
     * @return string
     */
    private static function buildCabecera(): string
    {
        return '<T3900' . self::$year . '0A0000>'
            . '<AUX>'
            . str_repeat(' ', 70)         // reservado AEAT
            . self::alpha('0100', 4)      // versión del programa (informativo)
            . str_repeat(' ', 4)          // reservado AEAT
            . str_repeat(' ', 9)          // NIF empresa de desarrollo (no aplica)
            . str_repeat(' ', 213)        // reservado AEAT
            . '</AUX>';
    }

    /**
     * Construye una página a partir de su lista de campos y comprueba que su longitud es la suma de
     * las longitudes del diseño.
     *
     * @param string $page código de página (01000, 02000, ...).
     * @param array<int, array{0: int, 1: string, 2: string}> $fields
     * @return string
     * @throws RuntimeException si algún campo no tiene la longitud del diseño.
     */
    private static function buildPage(string $page, array $fields): string
    {
        $out = '';
        $expected = 0;
        foreach ($fields as [$len, $kind, $value]) {
            $expected += $len;
            switch ($kind) {
                case 'c':
                    $out .= $value;
                    break;

                case 'id':
                    $out .= self::resolveId($value, $len);
                    break;

                case 'n':
                    $out .= self::amountSigned(self::square($value), $len);
                    break;

                case 'u':
                    $out .= self::amountUnsigned(self::square($value), $len);
                    break;

                case 'p':
                    $out .= self::percent(self::square($value), $len);
                    break;

                case 'z':
                    $out .= str_repeat('0', $len);
                    break;

                default:
                    $out .= str_repeat(' ', $len);
                    break;
            }
        }

        if (mb_strlen($out, 'UTF-8') !== $expected) {
            throw new RuntimeException('La página ' . $page . ' del modelo 390 mide ' . mb_strlen($out, 'UTF-8')
                . ' posiciones en lugar de ' . $expected . '.');
        }

        return $out;
    }

    /**
     * Resuelve los datos de identificación de la página 01000.
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

            case 'razon':
                // la empresa no separa apellidos y nombre: en las personas físicas va completo aquí
                return self::alpha((string)self::$company->nombre, $len);

            case 'ejercicio':
                return self::$year;

            case 'caja':
                // "1" sí, "2" no
                return strtolower((string)self::$company->regimeniva) === 'caja' ? '1' : '2';

            case 'carburantes':
            case 'no':
                return '2';

            case 'sustitutiva':
                return self::$reg->rectificativa ? '1' : '0';

            case 'justificante':
                return self::alpha((string)(self::$reg->justificanteant ?? ''), $len);

            case 'actividad':
                return self::alpha((string)(self::$reg->actividad390 ?? ''), $len);

            case 'claveactividad':
                return self::alpha((string)(self::$reg->claveactividad390 ?? ''), $len);

            case 'epigrafe':
                return self::alpha((string)(self::$reg->epigrafeiae390 ?? ''), $len);

            case 'op347':
                return self::$reg->operaciones347 ? '1' : '0';

            case 'cero':
                return '0';

            case 'nombre':
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
}
