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

use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Lib\InvoiceOperation;
use FacturaScripts\Dinamic\Lib\Modelo303;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Dinamic\Model\Join\PartidaImpuestoResumen;
use FacturaScripts\Dinamic\Model\RegularizacionImpuesto;

/**
 * Calcula las casillas del modelo 390 (declaración-resumen anual del IVA) de una regularización
 * anual (periodo Y), según el diseño oficial de la AEAT de los ejercicios 2024 a 2026.
 *
 * - Régimen general (apartado 5): a partir del mismo resumen de partidas de IVA que el 303
 *   (Modelo303::taxSummary(), con el filtro Modelo303::taxWhere()) sobre todo el año, repartido
 *   por tipo de IVA en las casillas propias del 390. Todo el IVA deducible se declara como de
 *   bienes y servicios corrientes: FacturaScripts no distingue las compras de bienes de inversión.
 * - Resultado de la liquidación anual (apartado 7) y resultado de las liquidaciones (apartado 9):
 *   a partir de las regularizaciones trimestrales o mensuales guardadas del mismo ejercicio.
 * - Volumen de operaciones (apartado 10) y operaciones específicas (apartado 11): a partir de las
 *   partidas de IVA y de las facturas de venta por tipo de operación.
 * - Casillas sin origen automático (96, 106, 107 y 112): se toman de la regularización anual, donde
 *   el usuario las introduce a mano.
 *
 * Quedan a 0 la prorrata, los sectores diferenciados, el régimen simplificado, el criterio de caja y
 * el resto de regímenes especiales, que FacturaScripts no contabiliza por separado.
 *
 * Lo usan la pestaña del modelo 390 de EditRegularizacionImpuesto, el fichero DR390 (Modelo390File)
 * y el endpoint /api/3/modelo390 (Modelo390Api), para que todos den las mismas cifras.
 *
 * @author Daniel Fernández Giménez <contacto@danielfg.es>
 */
class Modelo390
{
    /** Adquisiciones intracomunitarias de bienes corrientes (deducible): tipo => [base, cuota]. */
    private const DED_AIB = [
        '2' => ['765', '766'], '4' => ['214', '215'], '5' => ['736', '737'], '7.5' => ['767', '768'],
        '10' => ['627', '628'], '21' => ['629', '630'],
    ];

    /** Adquisiciones intracomunitarias de servicios (deducible): tipo => [base, cuota]. */
    private const DED_AIS = [
        '2' => ['773', '774'], '4' => ['587', '588'], '5' => ['740', '741'], '7.5' => ['775', '776'],
        '10' => ['635', '636'], '21' => ['637', '638'],
    ];

    /** Importaciones de bienes corrientes (deducible): tipo => [base, cuota]. */
    private const DED_IMPORTACION = [
        '2' => ['757', '758'], '4' => ['202', '203'], '5' => ['732', '733'], '7.5' => ['759', '760'],
        '10' => ['619', '620'], '21' => ['621', '622'],
    ];

    /** Operaciones interiores de bienes y servicios corrientes (deducible): tipo => [base, cuota]. */
    private const DED_INTERIOR = [
        '2' => ['695', '696'], '4' => ['190', '191'], '5' => ['724', '725'], '7.5' => ['697', '698'],
        '10' => ['603', '604'], '21' => ['605', '606'],
    ];

    /** Adquisiciones intracomunitarias de bienes (devengado): tipo => [base, cuota]. */
    private const DEV_AIB = [
        '0' => ['716', '717'], '2' => ['683', '684'], '4' => ['21', '22'], '5' => ['718', '719'],
        '7.5' => ['685', '686'], '10' => ['23', '24'], '21' => ['25', '26'],
    ];

    /** Adquisiciones intracomunitarias de servicios (devengado): tipo => [base, cuota]. */
    private const DEV_AIS = [
        '0' => ['720', '721'], '2' => ['687', '688'], '4' => ['545', '546'], '5' => ['722', '723'],
        '7.5' => ['689', '690'], '10' => ['547', '548'], '21' => ['551', '552'],
    ];

    /** Régimen ordinario (devengado): tipo => [base, cuota]. */
    private const DEV_ORDINARIO = [
        '0' => ['700', '701'], '2' => ['667', '668'], '4' => ['01', '02'], '5' => ['702', '703'],
        '7.5' => ['669', '670'], '10' => ['03', '04'], '21' => ['05', '06'],
    ];

    /** Recargo de equivalencia (devengado): tipo de recargo => [base, cuota]. */
    private const DEV_RECARGO = [
        '0' => ['663', '664'], '0.26' => ['691', '692'], '0.5' => ['35', '36'], '0.62' => ['665', '666'],
        '1' => ['693', '694'], '1.4' => ['599', '600'], '1.75' => ['41', '42'], '5.2' => ['601', '602'],
    ];

    /**
     * Casillas de introducción manual del 390 y la columna de la regularización donde se guardan
     * (ver Extension/Table/regularizacionimpuestos.xml).
     */
    public const MANUAL_SQUARES = [
        '96' => 'c390_96',
        '106' => 'c390_106',
        '107' => 'c390_107',
        '112' => 'c390_112',
    ];

    /**
     * Filas de la pantalla y del resumen de la API, agrupadas por apartado. Cada fila es
     * [etiqueta, tipo (o null), casilla de base (o null), casilla de cuota (o null), siempre visible].
     */
    public const SECTIONS = [
        'model390-section-accrued' => [
            ['model390-ordinary-regime', '0', '700', null, false],
            ['model390-ordinary-regime', '4', '01', '02', true],
            ['model390-ordinary-regime', '10', '03', '04', true],
            ['model390-ordinary-regime', '21', '05', '06', true],
            ['model390-ordinary-regime', '2', '667', '668', false],
            ['model390-ordinary-regime', '5', '702', '703', false],
            ['model390-ordinary-regime', '7.5', '669', '670', false],
            ['model390-intra-goods', '0', '716', null, false],
            ['model390-intra-goods', '4', '21', '22', false],
            ['model390-intra-goods', '10', '23', '24', false],
            ['model390-intra-goods', '21', '25', '26', false],
            ['model390-intra-services', '0', '720', null, false],
            ['model390-intra-services', '4', '545', '546', false],
            ['model390-intra-services', '10', '547', '548', false],
            ['model390-intra-services', '21', '551', '552', false],
            ['model390-reverse-charge', null, '27', '28', false],
            ['model390-total-vat', null, '33', '34', true],
            ['model390-surcharge', '0.5', '35', '36', false],
            ['model390-surcharge', '1.4', '599', '600', false],
            ['model390-surcharge', '1.75', '41', '42', false],
            ['model390-surcharge', '5.2', '601', '602', false],
            ['model390-total-accrued', null, null, '47', true],
        ],
        'model390-section-deductible' => [
            ['model390-domestic-current', '4', '190', '191', false],
            ['model390-domestic-current', '10', '603', '604', false],
            ['model390-domestic-current', '21', '605', '606', false],
            ['model390-domestic-current', '2', '695', '696', false],
            ['model390-domestic-current', '5', '724', '725', false],
            ['model390-domestic-current', '7.5', '697', '698', false],
            ['model390-total-domestic-current', null, '48', '49', true],
            ['model390-imports-current', '4', '202', '203', false],
            ['model390-imports-current', '10', '619', '620', false],
            ['model390-imports-current', '21', '621', '622', false],
            ['model390-total-imports-current', null, '52', '53', false],
            ['model390-intra-goods-current', '4', '214', '215', false],
            ['model390-intra-goods-current', '10', '627', '628', false],
            ['model390-intra-goods-current', '21', '629', '630', false],
            ['model390-total-intra-goods-current', null, '56', '57', false],
            ['model390-intra-services-deductible', '4', '587', '588', false],
            ['model390-intra-services-deductible', '10', '635', '636', false],
            ['model390-intra-services-deductible', '21', '637', '638', false],
            ['model390-total-intra-services-deductible', null, '597', '598', false],
            ['model390-total-deductions', null, null, '64', true],
            ['model390-general-regime-result', null, null, '65', true],
        ],
        'model390-section-annual-result' => [
            ['model390-box-658', null, null, '658', false],
            ['model390-box-84', null, null, '84', true],
            ['model390-box-659', null, null, '659', false],
            ['model390-box-85', null, null, '85', true],
            ['model390-box-112', null, null, '112', false],
            ['model390-box-86', null, null, '86', true],
        ],
        'model390-section-settlements' => [
            ['model390-box-95', null, null, '95', true],
            ['model390-box-96', null, null, '96', false],
            ['model390-box-97', null, null, '97', true],
            ['model390-box-98', null, null, '98', true],
            ['model390-box-662', null, null, '662', true],
        ],
        'model390-section-volume' => [
            ['model390-box-99', null, null, '99', true],
            ['model390-box-103', null, null, '103', false],
            ['model390-box-104', null, null, '104', false],
            ['model390-box-105', null, null, '105', false],
            ['model390-box-125', null, null, '125', false],
            ['model390-box-106', null, null, '106', false],
            ['model390-box-107', null, null, '107', false],
            ['model390-box-108', null, null, '108', true],
        ],
        'model390-section-specific' => [
            ['model390-box-230', null, null, '230', false],
            ['model390-box-109', null, null, '109', false],
        ],
    ];

    /**
     * Casillas que, desde el ejercicio 2025, el diseño de registro obliga a dejar a 0 porque
     * corresponden a tipos de IVA o de recargo que ya no existen (nota 2 del diseño).
     */
    public const ZERO_SQUARES_SINCE_2025 = [
        '701', '667', '668', '702', '703', '669', '670', '705', '671', '672', '706', '707', '673', '674',
        '709', '675', '676', '710', '711', '677', '678', '713', '679', '680', '714', '715', '681', '682',
        '717', '683', '684', '718', '719', '685', '686', '721', '687', '688', '722', '723', '689', '690',
        '663', '664', '691', '692', '665', '666', '693', '694',
        '749', '750', '751', '752', '753', '754', '755', '756', '728', '729', '730', '731',
        '761', '762', '763', '764', '734', '735',
        '769', '770', '771', '772', '773', '774', '775', '776', '738', '739', '740', '741',
    ];

    /** @var string[] avisos acumulados durante el cálculo */
    private array $avisos = [];

    /**
     * Resultados de las regularizaciones periódicas usadas en los apartados 7 y 9, en orden de fecha.
     *
     * @var array<int, array<string, mixed>>
     */
    private array $periodos = [];

    /** @var array<string, float> casillas calculadas (clave = nº de casilla AEAT, como en el diseño) */
    private array $square = [];

    /** @var int ejercicio (año natural) de la declaración */
    private int $year;

    /**
     * @param int $year año del ejercicio declarado, que decide las fórmulas y los avisos.
     */
    public function __construct(int $year)
    {
        $this->year = $year;
    }

    /**
     * Devuelve el importe de una casilla (0 si no tiene).
     *
     * @param string $square número de casilla tal y como figura en el diseño ('01', '700', ...).
     * @return float
     */
    public function casilla(string $square): float
    {
        return round($this->square[$square] ?? 0.0, 2);
    }

    /**
     * Devuelve el importe de una casilla formateado para la pantalla, vacío si vale 0.
     *
     * @param string $square
     * @param bool $showEmpty mostrar el 0 en lugar de una cadena vacía.
     * @return string
     */
    public function casillaStr(string $square, bool $showEmpty = false): string
    {
        $value = $this->casilla($square);
        if (empty($value) && false === $showEmpty) {
            return '';
        }

        return Tools::number($value, 2);
    }

    /**
     * Calcula los totales y resultados del 390 (casillas 33, 34, 47, 48-59, 597, 598, 64, 65, 84, 86
     * y 108) con las fórmulas oficiales. Debe llamarse después de cargar el resumen de IVA, las
     * facturas, las regularizaciones periódicas y las casillas manuales.
     */
    public function computeTotals(): void
    {
        // total bases y cuotas del IVA devengado (sin recargo de equivalencia)
        $this->square['33'] = $this->sum([
            '700', '667', '01', '702', '669', '03', '05', '704', '671', '500', '706', '673', '502', '504',
            '708', '675', '643', '710', '677', '645', '647', '712', '679', '07', '714', '681', '09', '11',
            '13', '716', '683', '21', '718', '685', '23', '25', '720', '687', '545', '722', '689', '547',
            '551', '27', '29', '649', '31',
        ]);
        // en las instrucciones de 2025 figura «46» en lugar de 646 (errata): es la cuota del 10 % en caja
        $this->square['34'] = $this->sum([
            '701', '668', '02', '703', '670', '04', '06', '705', '672', '501', '707', '674', '503', '505',
            '709', '676', '644', '711', '678', '646', '648', '713', '680', '08', '715', '682', '10', '12',
            '14', '717', '684', '22', '719', '686', '24', '26', '721', '688', '546', '723', '690', '548',
            '552', '28', '30', '650', '32',
        ]);

        // total cuotas de IVA y recargo de equivalencia
        $this->square['47'] = $this->sum(['34', '664', '692', '36', '666', '694', '600', '602', '42', '44', '46']);

        // totales de cada bloque del deducible (bases sin prorratear, cuotas deducibles)
        $this->square['48'] = $this->sum(['695', '190', '724', '697', '603', '605']);
        $this->square['49'] = $this->sum(['696', '191', '725', '698', '604', '606']);
        $this->square['52'] = $this->sum(['757', '202', '732', '759', '619', '621']);
        $this->square['53'] = $this->sum(['758', '203', '733', '760', '620', '622']);
        $this->square['56'] = $this->sum(['765', '214', '736', '767', '627', '629']);
        $this->square['57'] = $this->sum(['766', '215', '737', '768', '628', '630']);
        $this->square['597'] = $this->sum(['773', '587', '740', '775', '635', '637']);
        $this->square['598'] = $this->sum(['774', '588', '741', '776', '636', '638']);

        // suma de deducciones y resultado del régimen general
        $this->square['64'] = $this->sum([
            '49', '513', '51', '521', '53', '55', '57', '59', '598', '61', '661', '62', '652', '63', '522',
        ]);
        $this->square['65'] = $this->square['47'] - $this->square['64'];

        // resultado de la liquidación anual: el pago a cuenta de carburantes (112) existe desde 2026
        $this->square['84'] = $this->sum(['65', '83', '658']);
        $this->square['86'] = $this->square['84'] + $this->casilla('659') - $this->casilla('85');
        if ($this->year >= 2026) {
            $this->square['86'] -= $this->casilla('112');
        } else {
            $this->square['112'] = 0.0;
        }

        // total volumen de operaciones (art. 121 LIVA)
        $this->square['108'] = $this->sum([
            '99', '653', '103', '104', '105', '110', '100', '101', '102', '125', '126', '127', '128', '227', '228',
        ]) - $this->casilla('106') - $this->casilla('107');

        $this->checkResults();
    }

    /**
     * Construye el modelo 390 completo de una regularización anual: régimen general, volumen de
     * operaciones, operaciones específicas, resultados de las regularizaciones periódicas guardadas
     * del ejercicio y casillas manuales. La regularización no tiene por qué estar guardada y nunca
     * se modifica.
     *
     * @param RegularizacionImpuesto $reg regularización anual (periodo Y).
     * @param PartidaImpuestoResumen[]|null $resumen filas del resumen ya cargadas con
     *                                               Modelo303::taxSummary(), para no repetir la consulta.
     * @return static
     */
    public static function fromRegularizacion(RegularizacionImpuesto $reg, ?array $resumen = null): static
    {
        $modelo = new static((int)date('Y', strtotime((string)$reg->fechainicio)));
        $modelo->loadFromResumen($resumen ?? Modelo303::taxSummary($reg));
        $modelo->loadFromSalesInvoices(
            (int)$reg->idempresa,
            (string)$reg->codejercicio,
            (string)$reg->fechainicio,
            (string)$reg->fechafin
        );
        $modelo->loadFromPeriodos(static::periodicResults($reg));

        // casillas de introducción manual
        foreach (self::MANUAL_SQUARES as $box => $column) {
            $modelo->setCasilla($box, (float)($reg->{$column} ?? 0.0));
        }

        $modelo->computeTotals();
        return $modelo;
    }

    /**
     * Devuelve los avisos del cálculo (importes sin casilla, regularizaciones que faltan, descuadres...).
     *
     * @return string[]
     */
    public function getAvisos(): array
    {
        return $this->avisos;
    }

    /**
     * Devuelve los resultados de las regularizaciones periódicas usadas en los apartados 7 y 9.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPeriodos(): array
    {
        return $this->periodos;
    }

    /**
     * Devuelve los apartados de la pantalla con sus filas visibles: las marcadas como siempre
     * visibles y las que tienen importe en su base o en su cuota.
     *
     * @return array<string, array<int, array{label: string, rate: ?string, base: ?string, cuota: ?string}>>
     */
    public function getSections(): array
    {
        $sections = [];
        foreach (self::SECTIONS as $title => $rows) {
            foreach ($rows as [$label, $rate, $base, $cuota, $always]) {
                $hasAmount = ($base !== null && $this->casilla($base) != 0.0)
                    || ($cuota !== null && $this->casilla($cuota) != 0.0);
                if ($always || $hasAmount) {
                    $sections[$title][] = ['label' => $label, 'rate' => $rate, 'base' => $base, 'cuota' => $cuota];
                }
            }
        }

        return $sections;
    }

    /**
     * Devuelve todas las casillas con importe, redondeadas a 2 decimales y ordenadas por número.
     *
     * @return array<string, float>
     */
    public function getSquares(): array
    {
        $squares = [];
        foreach ($this->square as $box => $value) {
            $squares[(string)$box] = round($value, 2);
        }

        uksort($squares, static fn($a, $b) => (int)$a <=> (int)$b ?: strcmp((string)$a, (string)$b));
        return $squares;
    }

    /**
     * Devuelve el año del ejercicio declarado.
     *
     * @return int
     */
    public function getYear(): int
    {
        return $this->year;
    }

    /**
     * Carga los apartados 7 (658, 659 y 85) y 9 (95, 97, 98 y 662) a partir de los resultados de las
     * regularizaciones periódicas del ejercicio, ordenadas por fecha. Cada elemento lleva:
     * periodo, fechainicio, fechafin, resultado (casilla 71 del 303), pendiente (casilla 87),
     * c76, c77, c78, c110 y tipo (tipo de declaración: I, U, C, D, N...).
     *
     * - 658 y 659: suma de las casillas 76 y 77 de los 303.
     * - 85: cuotas de ejercicios anteriores aplicadas en el año. Se considera que las primeras cuotas
     *   compensadas (casilla 78) son las más antiguas, es decir, las pendientes al empezar el año
     *   (casilla 110 del primer periodo).
     * - 95: suma de los resultados a ingresar.
     * - 97 o 98: resultado negativo del último periodo, según se pidiera compensar o devolver.
     * - 662: cuotas pendientes de compensar al final del año que se generaron en el propio ejercicio.
     *
     * @param array<int, array<string, mixed>> $periodos
     */
    public function loadFromPeriodos(array $periodos): void
    {
        $this->periodos = array_values($periodos);
        if (empty($this->periodos)) {
            $this->avisos[] = Tools::trans('model390-no-periodic-settlements');
            return;
        }

        $compensado = 0.0;
        foreach ($this->periodos as $periodo) {
            $this->add('658', (float)$periodo['c76']);
            $this->add('659', (float)$periodo['c77']);
            $compensado += (float)$periodo['c78'];

            $resultado = round((float)$periodo['resultado'], 2);
            if ($resultado > 0.0) {
                $this->add('95', $resultado);
            }
        }

        // cuotas de ejercicios anteriores pendientes al empezar el año y aplicadas en él
        $pendienteInicial = max(0.0, (float)$this->periodos[0]['c110']);
        $this->square['85'] = min($pendienteInicial, max(0.0, $compensado));

        // resultado del último periodo: a compensar (97) o a devolver (98)
        $ultimo = end($this->periodos);
        $resultado = round((float)$ultimo['resultado'], 2);
        if ($resultado < 0.0) {
            $box = in_array($ultimo['tipo'] ?? '', ['D', 'X'], true) ? '98' : '97';
            $this->square[$box] = abs($resultado);
        }

        // pendiente al final del año (casilla 87 del último periodo) generado en el propio ejercicio
        $pendienteAnterior = $pendienteInicial - $this->square['85'];
        $this->square['662'] = max(0.0, round((float)$ultimo['pendiente'] - $pendienteAnterior, 2));

        $this->checkPeriodos();
    }

    /**
     * Reparte el resumen de partidas de IVA del año en las casillas del régimen general (devengado y
     * deducible), del volumen de operaciones (99 y 105) y de las operaciones específicas (109 y 230).
     *
     * @param PartidaImpuestoResumen[]|object[] $resumen
     */
    public function loadFromResumen(array $resumen): void
    {
        foreach ($resumen as $item) {
            $this->addMovimiento(
                (string)($item->codcuentaesp ?? ''),
                (string)($item->operacion ?? ''),
                (string)($item->tipodoc ?? ''),
                (float)$item->iva,
                (float)$item->recargo,
                (float)$item->baseimponible,
                (float)$item->cuota
            );
        }
    }

    /**
     * Carga en el volumen de operaciones las bases de las ventas sin IVA español: entregas
     * intracomunitarias de bienes y servicios (103), exportaciones (104) y ventas con inversión del
     * sujeto pasivo (125). Son las mismas facturas que las casillas 59, 60 y 122 del 303.
     *
     * @param int $idempresa
     * @param string $codejercicio
     * @param string $dateStart
     * @param string $dateEnd
     */
    public function loadFromSalesInvoices(int $idempresa, string $codejercicio, string $dateStart, string $dateEnd): void
    {
        $boxes = [
            InvoiceOperation::INTRA_COMMUNITY => '103',
            InvoiceOperation::INTRA_COMMUNITY_SERVICES => '103',
            InvoiceOperation::EXPORT => '104',
            InvoiceOperation::REVERSE_CHARGE => '125',
        ];

        $where = [
            Where::eq('idempresa', $idempresa),
            Where::eq('codejercicio', $codejercicio),
            Where::gte('fecha', $dateStart),
            Where::lte('fecha', $dateEnd),
            Where::in('operacion', array_keys($boxes)),
        ];
        foreach (FacturaCliente::all($where, [], 0, 0) as $invoice) {
            $this->add($boxes[$invoice->operacion], (float)$invoice->neto);
        }
    }

    /**
     * Calcula los resultados de las regularizaciones periódicas (trimestrales o mensuales) guardadas
     * de la misma empresa y ejercicio que la regularización anual, con el mismo cálculo que la
     * pantalla del 303. No modifica ninguna regularización.
     *
     * @param RegularizacionImpuesto $reg regularización anual.
     * @return array<int, array<string, mixed>> ver loadFromPeriodos().
     */
    public static function periodicResults(RegularizacionImpuesto $reg): array
    {
        $where = [
            Where::eq('idempresa', $reg->idempresa),
            Where::eq('codejercicio', $reg->codejercicio),
            Where::notEq('periodo', 'Y'),
        ];

        $periodos = [];
        foreach (RegularizacionImpuesto::all($where, ['fechainicio' => 'ASC', 'idregiva' => 'ASC'], 0, 0) as $item) {
            $modelo = Modelo303::fromRegularizacion($item);
            $periodos[] = [
                'idregiva' => (int)$item->idregiva,
                'periodo' => (string)$item->periodo,
                'fechainicio' => date('Y-m-d', strtotime((string)$item->fechainicio)),
                'fechafin' => date('Y-m-d', strtotime((string)$item->fechafin)),
                'resultado' => round($modelo->casilla('71'), 2),
                'pendiente' => round($modelo->casilla('87'), 2),
                'c76' => (float)$item->c76,
                'c77' => (float)$item->c77,
                'c78' => (float)$item->c78,
                'c110' => (float)$item->c110,
                'tipo' => Modelo303::declarationType($item, $modelo->casilla('71')),
            ];
        }

        return $periodos;
    }

    /**
     * Fija el importe de una casilla (casillas manuales o pruebas).
     *
     * @param string $square
     * @param float $value
     */
    public function setCasilla(string $square, float $value): void
    {
        $this->square[$square] = $value;
    }

    /**
     * Suma un importe a una casilla.
     *
     * @param string|null $square
     * @param float $value
     */
    private function add(?string $square, float $value): void
    {
        if (empty($square)) {
            return;
        }

        $this->square[$square] = ($this->square[$square] ?? 0.0) + $value;
    }

    /**
     * Suma base y cuota en las casillas del tipo indicado de una tabla de casillas por tipo.
     * Si el tipo no existe en la tabla, avisa de que el importe no tiene casilla.
     *
     * @param array<string, string[]> $map tabla tipo => [casilla base, casilla cuota].
     * @param float $rate tipo de IVA o de recargo.
     * @param string $tipo cuenta especial, para el aviso.
     * @param string $operacion operación de la factura, para el aviso.
     * @param float $base
     * @param float $cuota
     * @return bool si se ha encontrado casilla.
     */
    private function addByRate(array $map, float $rate, string $tipo, string $operacion, float $base, float $cuota): bool
    {
        $key = static::rateKey($rate);
        if (false === isset($map[$key])) {
            $this->registrarNoMapeado($tipo, $operacion, $base, $cuota, $rate);
            return false;
        }

        $this->add($map[$key][0], $base);
        $this->add($map[$key][1], $cuota);
        return true;
    }

    /**
     * Suma una base y una cuota en las casillas indicadas.
     *
     * @param string $baseSquare
     * @param string $cuotaSquare
     * @param float $base
     * @param float $cuota
     */
    private function addCasillas(string $baseSquare, string $cuotaSquare, float $base, float $cuota): void
    {
        $this->add($baseSquare, $base);
        $this->add($cuotaSquare, $cuota);
    }

    /**
     * Suma un IVA deducible por tipo. Las compras interiores al 0 % no tienen cuota deducible: su base
     * va a las adquisiciones interiores exentas o a tipo 0 (casilla 230).
     *
     * @param array<string, string[]> $map
     * @param float $iva
     * @param string $tipo
     * @param string $operacion
     * @param float $base
     * @param float $cuota
     */
    private function addDeducible(array $map, float $iva, string $tipo, string $operacion, float $base, float $cuota): void
    {
        if ($iva == 0.0) {
            if ($map === self::DED_INTERIOR) {
                $this->add('230', $base);
            }
            return;
        }

        $this->addByRate($map, $iva, $tipo, $operacion, $base, $cuota);
    }

    /**
     * Reparte una fila del resumen de IVA (cuenta especial, operación de la factura, tipo de
     * documento, tipos, base y cuota) en las casillas del 390, con el mismo criterio que el 303.
     *
     * @param string $tipo cuenta especial de IVA (IVAREP, IVASOP, IVARUE, ...)
     * @param string $operacion tipo de operación de la factura
     * @param string $tipodoc 'compra', 'venta' o '' (asiento sin factura)
     * @param float $iva
     * @param float $recargo
     * @param float $base
     * @param float $cuota
     */
    private function addMovimiento(string $tipo, string $operacion, string $tipodoc, float $iva, float $recargo, float $base, float $cuota): void
    {
        // recargo de equivalencia: se reconoce por el tipo de recargo, no por la cuenta especial
        if ($recargo > 0.0) {
            if ($cuota == 0.0) {
                $cuota = $base * ($recargo / 100.0);
            }
            $this->addByRate(self::DEV_RECARGO, $recargo, 'IVARRE', $operacion, $base, $cuota);
            return;
        }

        $esDevengado = in_array($tipo, ['IVAREP', 'IVARUE', 'IVAREX'], true);
        $esDeducible = in_array($tipo, ['IVASOP', 'IVASUE', 'IVASIM', 'IVASEX'], true);
        $esCompra = $tipodoc === 'compra';

        if ($esDevengado || $esDeducible) {
            switch ($operacion) {
                case InvoiceOperation::REVERSE_CHARGE:
                    // ISP en compras: autorrepercusión (27/28) y deducción como operación interior.
                    // En ventas el vendedor no repercute: su base va al volumen (125) desde las facturas
                    if ($esCompra) {
                        $esDevengado
                            ? $this->addCasillas('27', '28', $base, $cuota)
                            : $this->addDeducible(self::DED_INTERIOR, $iva, $tipo, $operacion, $base, $cuota);
                    }
                    return;

                case InvoiceOperation::INTRA_COMMUNITY:
                case InvoiceOperation::INTRA_COMMUNITY_SERVICES:
                    // adquisición intracomunitaria de bienes o servicios. Las entregas (ventas) son
                    // exentas: su base va al volumen (103) desde las facturas
                    if ($esCompra) {
                        $bienes = $operacion === InvoiceOperation::INTRA_COMMUNITY;
                        if ($esDevengado) {
                            $this->addByRate($bienes ? self::DEV_AIB : self::DEV_AIS, $iva, $tipo, $operacion, $base, $cuota);
                        } elseif ($bienes && $iva == 0.0) {
                            // adquisición intracomunitaria exenta o a tipo 0 (operaciones específicas)
                            $this->add('109', $base);
                        } else {
                            $this->addDeducible($bienes ? self::DED_AIB : self::DED_AIS, $iva, $tipo, $operacion, $base, $cuota);
                        }
                    }
                    return;

                case InvoiceOperation::EXPORT:
                    // exportación: exenta, su base va al volumen (104) desde las facturas
                    if (false === $esCompra) {
                        return;
                    }
                    break;

                case InvoiceOperation::IMPORT:
                    // importación: solo cuenta el IVA del DUA (> 0 %); la factura del proveedor
                    // extracomunitario va al 0 % y no aporta base ni cuota (como en el 303)
                    if ($esDeducible && $iva > 0.0) {
                        $this->addDeducible(self::DED_IMPORTACION, $iva, $tipo, $operacion, $base, $cuota);
                    }
                    return;
            }
        }

        switch ($tipo) {
            case 'IVAREP':
                $this->addByRate(self::DEV_ORDINARIO, $iva, $tipo, $operacion, $base, $cuota);
                // volumen de operaciones en régimen general (incluye las operaciones a tipo 0)
                if (false === $esCompra) {
                    $this->add('99', $base);
                }
                return;

            case 'IVAREX':
                // ventas exentas sin derecho a deducción
                if (false === $esCompra) {
                    $this->add('105', $base);
                }
                return;

            case 'IVARUE':
                $this->addByRate(self::DEV_AIB, $iva, $tipo, $operacion, $base, $cuota);
                return;

            case 'IVASOP':
                $this->addDeducible(self::DED_INTERIOR, $iva, $tipo, $operacion, $base, $cuota);
                return;

            case 'IVASIM':
                $this->addDeducible(self::DED_IMPORTACION, $iva, $tipo, $operacion, $base, $cuota);
                return;

            case 'IVASUE':
                $this->addDeducible(self::DED_AIB, $iva, $tipo, $operacion, $base, $cuota);
                return;

            case 'IVASEX':
                // adquisiciones interiores exentas (operaciones específicas)
                $this->add('230', $base);
                return;
        }

        $this->registrarNoMapeado($tipo, $operacion, $base, $cuota);
    }

    /**
     * Avisa si las regularizaciones periódicas guardadas no cubren los 12 meses del año.
     */
    private function checkPeriodos(): void
    {
        $meses = [];
        foreach ($this->periodos as $periodo) {
            $inicio = (int)date('n', strtotime((string)$periodo['fechainicio']));
            $fin = (int)date('n', strtotime((string)$periodo['fechafin']));
            for ($mes = $inicio; $mes <= $fin; $mes++) {
                $meses[$mes] = true;
            }
        }

        $faltan = array_diff(range(1, 12), array_keys($meses));
        if (false === empty($faltan)) {
            $this->avisos[] = Tools::trans('model390-missing-periodic-settlements', [
                '%months%' => implode(', ', $faltan),
            ]);
        }
    }

    /**
     * Avisa de los descuadres entre el resultado anual y las regularizaciones periódicas, y de las
     * casillas con importe que el diseño obliga a dejar a 0.
     */
    private function checkResults(): void
    {
        if ($this->year >= 2025) {
            foreach (self::ZERO_SQUARES_SINCE_2025 as $box) {
                if (abs($this->casilla($box)) >= 0.01) {
                    $this->avisos[] = Tools::trans('model390-box-must-be-zero', [
                        '%box%' => $box,
                        '%amount%' => Tools::number($this->casilla($box), 2),
                        '%year%' => $this->year,
                    ]);
                }
            }
        }

        if (empty($this->periodos)) {
            return;
        }

        // el resultado anual debe coincidir con lo liquidado en el año (instrucciones, apartado 9)
        $liquidado = $this->casilla('95') + $this->casilla('96') - $this->casilla('97')
            - $this->casilla('98') - $this->casilla('662');
        $diferencia = round($this->casilla('86') - $liquidado, 2);
        if (abs($diferencia) >= 0.01) {
            $this->avisos[] = Tools::trans('model390-result-mismatch', [
                '%result%' => Tools::number($this->casilla('86'), 2),
                '%settled%' => Tools::number($liquidado, 2),
                '%difference%' => Tools::number($diferencia, 2),
            ]);
        }
    }

    /**
     * Normaliza un tipo (21.00 → '21', 7.50 → '7.5', 0.26 → '0.26') para buscarlo en las tablas.
     *
     * @param float $rate
     * @return string
     */
    private static function rateKey(float $rate): string
    {
        return rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.');
    }

    /**
     * Avisa de un importe que no encaja en ninguna casilla del 390.
     *
     * @param string $tipo
     * @param string $operacion
     * @param float $base
     * @param float $cuota
     * @param float|null $tax
     */
    private function registrarNoMapeado(string $tipo, string $operacion, float $base, float $cuota, ?float $tax = null): void
    {
        if (empty($base) && empty($cuota)) {
            return;
        }

        $this->avisos[] = Tools::trans('model390-amount-without-square', [
            '%type%' => $tipo,
            '%rate%' => $tax === null ? '-' : Tools::number($tax, 2),
            '%operation%' => $operacion === '' ? '-' : $operacion,
            '%base%' => Tools::number($base, 2),
            '%quota%' => Tools::number($cuota, 2),
        ]);
    }

    /**
     * Suma las casillas indicadas.
     *
     * @param string[] $boxes
     * @return float
     */
    private function sum(array $boxes): float
    {
        $total = 0.0;
        foreach ($boxes as $box) {
            $total += $this->square[$box] ?? 0.0;
        }

        return $total;
    }
}
