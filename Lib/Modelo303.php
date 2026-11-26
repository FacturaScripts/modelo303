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

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Lib\SubAccountTools;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Lib\InvoiceOperation;
use FacturaScripts\Dinamic\Model\Asiento;
use FacturaScripts\Dinamic\Model\Ejercicio;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Dinamic\Model\FacturaProveedor;
use FacturaScripts\Dinamic\Model\Join\PartidaImpuestoResumen;
use FacturaScripts\Dinamic\Model\RegularizacionImpuesto;

/**
 * Class to handle Modelo 303 tax form data.
 *
 * Además del reparto de importes a casillas, ofrece los métodos estáticos que calculan el
 * modelo a partir de una regularización de IVA (fromRegularizacion(), taxWhere(),
 * taxSummary() e invoicesWithoutAccounting()) y el arrastre de cuotas a compensar entre periodos
 * (previousRegularization(), pendingCarryover() y declarationType()), compartidos por la pantalla
 * EditRegularizacionImpuesto, el fichero .303 y el endpoint de la API /api/3/modelo303,
 * para que todos den exactamente las mismas cifras.
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class Modelo303
{
    /**
     * Nombres que recibe el periodo anual según quién lo pida: Y (periodo guardado en
     * RegularizacionImpuesto), ANUAL (formato público de la API), ANNUAL (plugin Informes) y A y 0A
     * (fichero de la AEAT). Modelo303ApiTrait::normalizePeriod() y treasury() los aceptan todos.
     *
     * @var string[]
     */
    public const ANNUAL_PERIODS = ['Y', 'ANUAL', 'ANNUAL', 'A', '0A'];

    /**
     * Casillas de introducción manual, guardadas en la regularización como c65, c68, ...
     * (ver Extension/Table/regularizacionimpuestos.xml).
     */
    public const MANUAL_SQUARES = ['65', '68', '70', '76', '77', '78', '108', '109', '110', '111', '112'];

    /**
     * Número de casilla más alto que se inicializa en el modelo (de la 00 a la 200).
     *
     * @var int
     */
    private const MAX_SQUARE = 200;

    /**
     * Warnings collected while loading data (amounts not assigned to any square).
     *
     * @var string[]
     */
    private array $avisos = [];

    /**
     * Structure for know to assign values to squares.
     *
     * @var array<string, array<string, array<string, ?string>>>
     */
    private array $casillaMap = [
        /*
         * IVA devengado (repercutido).
         * Ventas nacionales (régimen general)
         */
        'IVAREP' => [
            // tipo 0%: en la práctica es una operación exenta, aunque la subcuenta
            // no esté etiquetada como IVAREX; se trata igual, como base informativa (150)
            '0'   => ['base' => '150', 'cuota' => null],
            '2'   => ['base' => '165', 'cuota' => '167'],
            '4'   => ['base' => '01', 'cuota' => '03'],
            '5'   => ['base' => '153', 'cuota' => '155'],
            '7.5' => ['base' => '162', 'cuota' => '164'],
            '10'  => ['base' => '04', 'cuota' => '06'],
            '21'  => ['base' => '07', 'cuota' => '09'],
        ],

        // Adquisiciones intracomunitarias (cualquier tipo va a 10/11)
        'IVARUE' => [
            '*' => ['base' => '10', 'cuota' => '11'],
        ],

        // Recargo de equivalencia, según el diseño de registro DR303 de 2025 y 2026: la fila 168-170
        // es la del 0,5 % (IVA del 4 %) y la 16-18 la del 0 %, que no genera cuota y se deja vacía. Los
        // recargos del 0,26 % y del 1 % de 2024 ya no tienen casilla, así que se avisan como importe
        // sin casilla.
        'IVARRE' => [
            '0.5'  => ['base' => '168', 'cuota' => '170'],
            '1.4'  => ['base' => '19',  'cuota' => '21'],
            '1.75' => ['base' => '156', 'cuota' => '158'],
            '5.2'  => ['base' => '22',  'cuota' => '24'],
        ],

        // Operaciones exentas
        'IVAREX' => ['*' => ['base' => '150', 'cuota' => null]],

        /*
         * IVA soportado (deducible)
         * Compras nacionales (régimen general)
         */
        'IVASOP' => [
            // tipo 0%: compra exenta, igual que IVASEX; no genera cuota deducible
            // y no debe sumarse a la base de la casilla 28
            '0' => ['base' => null, 'cuota' => null],
            '*' => ['base' => '28', 'cuota' => '29'],
        ],

        // Compras en importaciones (cualquier tipo va a 32/33)
        'IVASIM' => [
            '*' => ['base' => '32', 'cuota' => '33'],
        ],

        // Compras en adquisiciones intracomunitarias (cualquier tipo va a 36/37)
        'IVASUE' => [
            '*' => ['base' => '36', 'cuota' => '37'],
        ],

        // Operaciones exentas: las compras exentas no se deducen ni figuran en el
        // resultado del régimen general; se reconocen para no generar avisos.
        'IVASEX' => ['*' => ['base' => null, 'cuota' => null]],
    ];

    /**
     * Stores all model squares.
     * Each key is the AEAT square number.
     *   '01' => 0.00, '02' => 0.00, ...
     *
     * @var array<string, float>
     */
    private array $square;

    /**
     * Initializes the tax rates for each square.
     */
    public function __construct()
    {
        $this->square = array_fill_keys(
            array_map(fn($i) => sprintf('%02d', $i), range(0, self::MAX_SQUARE)),
            0.00
        );

        $this->square['02'] = 4.00;
        $this->square['05'] = 10.00;
        $this->square['08'] = 21.00;
        $this->square['17'] = 0.00;
        $this->square['20'] = 1.40;
        $this->square['23'] = 5.20;
        $this->square['154'] = 5.00;
        $this->square['157'] = 1.75;
        $this->square['163'] = 7.50;
        $this->square['169'] = 0.50;
        $this->square['166'] = 2.00;

        // % atribuible a la Administración del Estado (casilla 65), por defecto 100%
        $this->square['65'] = 100.00;
    }

    /**
     * Get the value of a specific square.
     *
     * @param string $square
     * @return float
     */
    public function casilla(string $square): float
    {
        return $this->square[$square] ?? 0.00;
    }

    /**
     * Get the value of a specific square formatted as a string.
     *
     * @param string $square
     * @param bool $showEmpty si es true, devuelve también los importes a cero formateados.
     * @return string
     */
    public function casillaStr(string $square, bool $showEmpty = false): string
    {
        $value = $this->casilla($square);
        if (empty($value) && false === $showEmpty ) {
            return '';
        }
        return Tools::number($value, 2);
    }

    /**
     * Computes the result chain of the general regime (squares 64, 66, 69, 71, 87) from the
     * totals already calculated and the manually-injected squares. Must be called AFTER
     * loadFromResumen() and after setting the manual squares (110, 78, 70, 109, 112, 68, 108, 77, 76).
     *
     * @return void
     */
    public function computeResultado(): void
    {
        // Suma de resultados: régimen general (46) + simplificado (58) + regularización 80.cinco (76)
        $this->square['64'] = $this->square['46'] + $this->square['58'] + $this->square['76'];

        // Atribuible a la Administración del Estado = 64 * % (65)
        $this->square['66'] = round($this->square['64'] * ($this->square['65'] / 100.0), 2);

        // Cuotas a compensar de periodos previos pendientes para posteriores = 110 - 78
        $this->square['87'] = $this->square['110'] - $this->square['78'];

        // Resultado de la autoliquidación = 66 + 77 - 78 + 68 + 108
        $this->square['69'] = $this->square['66']
            + $this->square['77']
            - $this->square['78']
            + $this->square['68']
            + $this->square['108'];

        // Resultado = 69 - 70 + 109 - 112
        $this->square['71'] = $this->square['69']
            - $this->square['70']
            + $this->square['109']
            - $this->square['112'];
    }

    /**
     * Tipo de declaración de una regularización: el introducido a mano o, si no hay, el mismo que
     * deduce el fichero .303 a partir del resultado (casilla 71) y del IBAN: I o U si sale a ingresar,
     * C o D si sale negativa (a compensar sin IBAN, a devolver con IBAN) y N si sale 0.
     *
     * @param RegularizacionImpuesto $reg
     * @param float $resultado casilla 71 de la regularización.
     * @return string
     */
    public static function declarationType(RegularizacionImpuesto $reg, float $resultado): string
    {
        $manual = strtoupper(trim((string)($reg->tipodeclaracion ?? '')));
        if ($manual !== '') {
            return substr($manual, 0, 1);
        }

        if ($reg->sinactividad) {
            return 'N';
        }

        $tieneIban = false === empty($reg->iban);
        if ($resultado > 0.0) {
            return $tieneIban ? 'U' : 'I';
        }

        if ($resultado < 0.0) {
            return $tieneIban ? 'D' : 'C';
        }

        return 'N';
    }

    /**
     * Construye el modelo 303 completo de una regularización de IVA: casillas del régimen
     * general (resumen de partidas de IVA), bases exentas e informativas de ventas, casillas de
     * introducción manual guardadas en la regularización y cadena de resultado (64 a 87).
     *
     * La regularización no tiene por qué estar guardada: basta con que tenga idempresa,
     * codejercicio, fechainicio y fechafin. Nunca se guarda ni se modifica.
     *
     * @param RegularizacionImpuesto $reg regularización de la que calcular el modelo.
     * @param PartidaImpuestoResumen[]|null $resumen filas del resumen ya cargadas con
     *                                               taxSummary(), para no repetir la consulta.
     * @return static
     */
    public static function fromRegularizacion(RegularizacionImpuesto $reg, ?array $resumen = null): static
    {
        $modelo = new static();

        // casillas del régimen general (devengado, deducible y resultado 46)
        $modelo->loadFromResumen($resumen ?? static::taxSummary($reg));

        // bases exentas/informativas de ventas (casillas 59, 60 y 122)
        $modelo->loadFromSalesInvoices(
            (int)$reg->idempresa,
            (string)$reg->codejercicio,
            (string)$reg->fechainicio,
            (string)$reg->fechafin
        );

        // casillas de introducción manual (sin origen automático en la contabilidad)
        foreach (self::MANUAL_SQUARES as $box) {
            $value = $reg->{'c' . $box} ?? null;
            if ($value !== null) {
                $modelo->setCasilla($box, (float)$value);
            }
        }

        // la casilla 112 (pago a cuenta de gasolinas) solo existe desde el diseño de 2026: en los
        // ejercicios anteriores no forma parte del resultado ni del fichero
        $year = (int)date('Y', strtotime((string)$reg->fechainicio));
        if ($year < Modelo303File::FIRST_YEAR_2026_LAYOUT && abs($modelo->casilla('112')) >= 0.005) {
            $modelo->avisos[] = Tools::trans('model303-box-112-not-in-year', [
                '%amount%' => Tools::number($modelo->casilla('112')),
                '%year%' => $year,
            ]);
            $modelo->setCasilla('112', 0.0);
        }

        // cadena de resultado (casillas 64, 66, 69, 71, 87)
        $modelo->computeResultado();

        return $modelo;
    }

    /**
     * Returns the list of warnings collected while loading data
     * (amounts that could not be assigned to any square).
     *
     * @return string[]
     */
    public function getAvisos(): array
    {
        return $this->avisos;
    }

    /**
     * Returns all the squares (key = AEAT square number, value = amount).
     *
     * @return array<string, float>
     */
    public function getSquares(): array
    {
        return $this->square;
    }

    /**
     * Devuelve las facturas de compra y de venta del ejercicio y periodo de la regularización
     * que no tienen asiento contable (y cuyo total no es cero), por lo que no se tienen en
     * cuenta en el modelo.
     *
     * @param RegularizacionImpuesto $reg
     * @return array{compra: FacturaProveedor[], venta: FacturaCliente[]}
     */
    public static function invoicesWithoutAccounting(RegularizacionImpuesto $reg): array
    {
        $where = [
            Where::isNull('idasiento'),
            Where::eq('codejercicio', $reg->codejercicio),
            Where::gte('fecha', $reg->fechainicio),
            Where::lte('fecha', $reg->fechafin),
            Where::notEq('total', 0),
        ];

        return [
            'compra' => FacturaProveedor::all($where, ['fecha' => 'ASC', 'codigo' => 'ASC'], 0, 0),
            'venta' => FacturaCliente::all($where, ['fecha' => 'ASC', 'codigo' => 'ASC'], 0, 0),
        ];
    }

    /**
     * Loads summary data from an array of PartidaImpuestoResumen.
     *
     * @param PartidaImpuestoResumen[] $resumen
     */
    public function loadFromResumen(array $resumen): void
    {
        foreach ($resumen as $item) {
            $this->addMovimiento(
                $item->codcuentaesp ?? '',
                $item->operacion ?? '',
                $item->tipodoc ?? '',
                (float) $item->iva,
                (float) $item->recargo,
                (float) $item->baseimponible,
                (float) $item->cuota
            );
        }
        $this->calculateTotals();
    }

    /**
     * Loads the exempt/informative tax bases of sales invoices (without VAT entries)
     * into their squares: intracommunity (59), exports (60) and reverse charge (122).
     *
     * @param int $idempresa
     * @param string $codejercicio
     * @param string $dateStart
     * @param string $dateEnd
     */
    public function loadFromSalesInvoices(int $idempresa, string $codejercicio, string $dateStart, string $dateEnd): void
    {
        $where = [
            Where::eq('idempresa', $idempresa),
            Where::eq('codejercicio', $codejercicio),
            Where::gte('fecha', $dateStart),
            Where::lte('fecha', $dateEnd),
            Where::in('operacion', [
                InvoiceOperation::INTRA_COMMUNITY,
                InvoiceOperation::INTRA_COMMUNITY_SERVICES,
                InvoiceOperation::EXPORT,
                InvoiceOperation::REVERSE_CHARGE,
            ]),
        ];

        foreach (FacturaCliente::all($where, [], 0, 0) as $invoice) {
            $this->addBaseExentaVenta((string)$invoice->operacion, (float)$invoice->neto);
        }
    }

    /**
     * Cuotas pendientes de compensar que una regularización deja para el periodo siguiente (casilla
     * 110 del siguiente 303): las pendientes de periodos anteriores que no se han aplicado (casilla 87)
     * más el resultado negativo del propio periodo si se ha pedido compensar (tipo de declaración C).
     * Si el resultado negativo se pide devolver, no queda nada que compensar por él.
     *
     * @param RegularizacionImpuesto $reg
     * @return float
     */
    public static function pendingCarryover(RegularizacionImpuesto $reg): float
    {
        $modelo = static::fromRegularizacion($reg);
        $pendiente = $modelo->casilla('87');

        $resultado = round($modelo->casilla('71'), 2);
        if ($resultado < 0.0 && static::declarationType($reg, $resultado) === 'C') {
            $pendiente += abs($resultado);
        }

        return round($pendiente, 2);
    }

    /**
     * Devuelve la regularización trimestral o mensual inmediatamente anterior de la misma empresa,
     * aunque sea de otro ejercicio. Nunca devuelve una regularización anual (modelo 390), que comparte
     * fecha de fin con el último periodo del año.
     *
     * @param RegularizacionImpuesto $reg
     * @return RegularizacionImpuesto|null
     */
    public static function previousRegularization(RegularizacionImpuesto $reg): ?RegularizacionImpuesto
    {
        $where = [
            Where::eq('idempresa', $reg->idempresa),
            Where::lt('fechafin', $reg->fechainicio),
            Where::notEq('periodo', 'Y'),
        ];
        $previous = RegularizacionImpuesto::all($where, ['fechafin' => 'DESC', 'idregiva' => 'DESC'], 0, 1);
        return empty($previous) ? null : $previous[0];
    }

    /**
     * Set the value of a specific square (used to inject manually-entered amounts
     * that have no automatic source in FacturaScripts, e.g. cuotas a compensar).
     *
     * @param string $square
     * @param float $value
     * @return void
     */
    public function setCasilla(string $square, float $value): void
    {
        $this->square[$square] = $value;
    }

    /**
     * Carga el resumen de partidas de IVA de la regularización (todas las filas, sin paginar),
     * agrupado por subcuenta, tipo de IVA, recargo y operación, que alimenta las casillas del
     * régimen general.
     *
     * @param RegularizacionImpuesto $reg
     * @return PartidaImpuestoResumen[]
     */
    public static function taxSummary(RegularizacionImpuesto $reg): array
    {
        // mismo criterio que las pestañas Compras/Ventas y que el asiento de regularización
        $where = static::taxWhere($reg, SubAccountTools::SPECIAL_GROUP_TAX_ALL);
        return PartidaImpuestoResumen::all($where, [
            'COALESCE(subcuentas.codcuentaesp, cuentas.codcuentaesp)' => 'ASC',
            'partidas.codsubcuenta' => 'ASC',
        ], 0, 0);
    }

    /**
     * Construye el filtro común del resumen, de las pestañas de compras/ventas y del asiento
     * de regularización, para que todos trabajen sobre el MISMO conjunto de partidas: asientos
     * de la empresa y ejercicio dentro de las fechas de la regularización, sin series sin IVA,
     * sin asientos de apertura o cierre y sin los asientos de ninguna regularización de IVA.
     *
     * @param RegularizacionImpuesto $reg
     * @param int $group grupo de cuentas especiales (TAX_ALL, TAX_INPUT, TAX_OUTPUT)
     * @return array
     */
    public static function taxWhere(RegularizacionImpuesto $reg, int $group): array
    {
        $excludedOperations = implode(',', [Asiento::OPERATION_OPENING, Asiento::OPERATION_CLOSING]);

        // ids de los asientos de TODAS las regularizaciones (para excluirlos)
        $regIds = [];
        foreach (RegularizacionImpuesto::all([Where::isNotNull('idasiento')], [], 0, 0) as $item) {
            if ($item->idasiento) {
                $regIds[] = $item->idasiento;
            }
        }

        $subAccountTools = new SubAccountTools();
        $where = [
            Where::eq('asientos.idempresa', $reg->idempresa),
            Where::eq('asientos.codejercicio', $reg->codejercicio),
            Where::gte('asientos.fecha', $reg->fechainicio),
            Where::lte('asientos.fecha', $reg->fechafin),
            Where::eq('COALESCE(series.siniva, false)', false),
            Where::notIn("COALESCE(asientos.operacion, '')", $excludedOperations),
            $subAccountTools->whereForSpecialAccounts('COALESCE(subcuentas.codcuentaesp, cuentas.codcuentaesp)', $group),
        ];

        if (false === empty($regIds)) {
            $where[] = Where::notIn('asientos.idasiento', implode(',', $regIds));
        }

        return $where;
    }

    /**
     * Devuelve los saldos de IVA repercutido (477), soportado (472) y a devolver (4700) del
     * periodo de un ejercicio, para el plugin Informes (9421c52). Solo suma las partidas de los
     * asientos de ese ejercicio, que es de una sola empresa.
     *
     * @param string $codejercicio código del ejercicio.
     * @param string $period T1, T2, T3, T4 o el anual, que admite ANNUAL, ANUAL, Y, A y 0A
     *                       (cualquier otro valor se trata como T1).
     * @return array<string, float> claves iva-repercutido, iva-soportado e iva-devolver; vacío si el ejercicio no existe.
     */
    public static function treasury(string $codejercicio, string $period): array
    {
        // comprobamos que el ejercicio existe
        $exercise = new Ejercicio();
        if (false === $exercise->load($codejercicio)) {
            return [];
        }

        $dataBase = new DataBase();
        [$dateStart, $dateEnd] = static::treasuryDates($exercise, strtoupper(trim($period)));

        return [
            'iva-repercutido' => static::treasurySaldoCuenta('477%', $exercise->codejercicio, $dateStart, $dateEnd, $dataBase),
            'iva-soportado' => static::treasurySaldoCuenta('472%', $exercise->codejercicio, $dateStart, $dateEnd, $dataBase),
            'iva-devolver' => static::treasurySaldoCuenta('4700%', $exercise->codejercicio, $dateStart, $dateEnd, $dataBase),
        ];
    }

    /**
     * Adds the exempt tax base of a sales invoice to its informative square.
     *
     * @param string $operacion
     * @param float $base
     * @return void
     */
    private function addBaseExentaVenta(string $operacion, float $base): void
    {
        switch ($operacion) {
            case InvoiceOperation::EXPORT:
                $this->square['60'] += $base;
                break;

            case InvoiceOperation::INTRA_COMMUNITY:
            case InvoiceOperation::INTRA_COMMUNITY_SERVICES:
                $this->square['59'] += $base;
                break;

            case InvoiceOperation::REVERSE_CHARGE:
                $this->square['122'] += $base;
                break;
        }
    }

    /**
     * Adds a base and/or quota amount to the given squares.
     *
     * @param string|null $baseSquare
     * @param string|null $cuotaSquare
     * @param float $base
     * @param float $cuota
     * @return void
     */
    private function addCasilla(?string $baseSquare, ?string $cuotaSquare, float $base, float $cuota): void
    {
        if (false === empty($baseSquare)) {
            $this->square[$baseSquare] += $base;
        }

        if (false === empty($cuotaSquare)) {
            $this->square[$cuotaSquare] += $cuota;
        }
    }

    /**
     * Add a tax movement to the model (base + quota by type and rate)
     * - Special operations (reverse charge, intracommunity, import) are routed by
     *   the invoice operation type to their specific squares.
     * - The rest follows the casillaMap by special account and tax rate.
     *
     * @param string $tipo cuenta especial de IVA (IVAREP, IVASOP, IVARUE, ...)
     * @param string $operacion tipo de operación de la factura (intracomunitaria, inv-sujeto-pasivo, ...)
     * @param string $tipodoc 'compra' o 'venta'
     * @param float $iva
     * @param float $recargo
     * @param float $base
     * @param float $cuota
     * @return void
     */
    private function addMovimiento(string $tipo, string $operacion, string $tipodoc, float $iva, float $recargo, float $base, float $cuota): void
    {
        // El recargo de equivalencia se reconoce por el tipo de recargo (> 0), no por la cuenta
        // especial: cuando no existe una subcuenta IVARRE configurada, el núcleo contabiliza el
        // recargo en la propia cuenta de IVA repercutido (IVAREP), con iva = 0 y recargo > 0. En
        // ese caso también debe mapearse a las casillas de recargo, no quedar sin casilla.
        if ($recargo > 0.0) {
            $tipo = 'IVARRE';
        }

        // Las operaciones especiales (ISP, intracomunitarias, importación) deciden la casilla
        // por el tipo de operación de la factura, no solo por la cuenta especial.
        if ($this->addMovimientoEspecial($tipo, $operacion, $tipodoc, $iva, $base, $cuota)) {
            return;
        }

        if (false === isset($this->casillaMap[$tipo])) {
            $this->registrarNoMapeado($tipo, $operacion, $base, $cuota);
            return;
        }

        // Determine the correct group based on the tax rate.
        $tax = ($tipo === 'IVARRE') ? $recargo : $iva;
        $key = rtrim(rtrim(number_format($tax, 2, '.', ''), '0'), '.');
        $grupo = $this->casillaMap[$tipo][$key]
            ?? $this->casillaMap[$tipo][(string)(int)$tax]
            ?? $this->casillaMap[$tipo]['*']
            ?? null;

        if ($grupo === null) {
            $this->registrarNoMapeado($tipo, $operacion, $base, $cuota, $tax);
            return;
        }

        // For recargo, if cuota is zero, calculate it from base and recargo rate
        if ($tipo === 'IVARRE' && $cuota == 0.0 && $recargo > 0.0) {
            $cuota = $base * ($recargo / 100.0);
        }

        $this->addCasilla($grupo['base'] ?? null, $grupo['cuota'] ?? null, $base, $cuota);
    }

    /**
     * Routes movements of special invoice operations (reverse charge, intracommunity, import)
     * to their specific squares. Returns true if the movement has been handled (or intentionally
     * skipped) and must not follow the standard mapping.
     *
     * @param string $tipo
     * @param string $operacion
     * @param string $tipodoc
     * @param float $iva tipo de IVA de la partida (%)
     * @param float $base
     * @param float $cuota
     * @return bool
     */
    private function addMovimientoEspecial(string $tipo, string $operacion, string $tipodoc, float $iva, float $base, float $cuota): bool
    {
        $esDevengado = in_array($tipo, ['IVAREP', 'IVARUE', 'IVAREX'], true);
        $esDeducible = in_array($tipo, ['IVASOP', 'IVASUE', 'IVASIM', 'IVASEX'], true);

        // El recargo de equivalencia y cuentas no fiscales siguen el tratamiento estándar.
        if (false === $esDevengado && false === $esDeducible) {
            return false;
        }

        switch ($operacion) {
            case InvoiceOperation::REVERSE_CHARGE:
                // Inversión del sujeto pasivo en COMPRAS: autorrepercusión.
                // Devengado → 12/13, deducible → 28/29 (el IVA se compensa).
                if ($tipodoc === 'compra') {
                    $esDevengado
                        ? $this->addCasilla('12', '13', $base, $cuota)
                        : $this->addCasilla('28', '29', $base, $cuota);
                }
                // En ventas con ISP el vendedor no repercute IVA; la base informativa (122)
                // se carga desde las facturas en loadFromSalesInvoices().
                return true;

            case InvoiceOperation::INTRA_COMMUNITY:
            case InvoiceOperation::INTRA_COMMUNITY_SERVICES:
                // Adquisición intracomunitaria (COMPRA): devengado → 10/11, deducible → 36/37.
                if ($tipodoc === 'compra') {
                    $esDevengado
                        ? $this->addCasilla('10', '11', $base, $cuota)
                        : $this->addCasilla('36', '37', $base, $cuota);
                }
                // Entrega intracomunitaria (VENTA): exenta. Las partidas de autorrepercusión
                // (IVARUE/IVASUE) se compensan y no forman parte del régimen general; la base
                // informativa (casilla 59) se carga desde las facturas.
                return true;

            case InvoiceOperation::EXPORT:
                // Exportación (VENTA): operación exenta. Su base va a la casilla 60 (se carga desde
                // las facturas en loadFromSalesInvoices()), no debe sumarse a la 150 (base al 0%).
                return $tipodoc === 'venta';

            case InvoiceOperation::IMPORT:
                // Importación (COMPRA): el IVA lo liquida la aduana (DUA). Solo el soportado deducible.
                // La factura del proveedor extracomunitario va al 0% y no aporta base ni cuota: la base
                // de las casillas 32/33 es solo la del DUA (art. 83 LIVA). El DUA sí lleva IVA (> 0%),
                // y el núcleo lo contabiliza en la cuenta de IVA soportado del impuesto (IVASOP), no en
                // IVASIM, por lo que se decide por el tipo de IVA y no por la cuenta especial.
                if ($esDeducible && $iva > 0.0) {
                    $this->addCasilla('32', '33', $base, $cuota);
                }
                // El IVA devengado de importación (casilla 77 / IVA diferido) no tiene origen
                // contable automático en FacturaScripts.
                return true;

            default:
                return false;
        }
    }

    /**
     * Calculate total squares based on individual entries.
     *
     * @return void
     */
    private function calculateTotals(): void
    {
        // Total cuota devengada, según el diseño de la AEAT:
        // [152] + [167] + [03] + [155] + [164] + [06] + [09] + [11] + [13] + [15]
        // + [158] + [170] + [18] + [21] + [24] + [26]
        $this->square['27'] = $this->square['152']
            + $this->square['167']
            + $this->square['155']
            + $this->square['164']
            + $this->square['158']
            + $this->square['170']
            + $this->square['03']
            + $this->square['06']
            + $this->square['09']
            + $this->square['11']
            + $this->square['13']
            + $this->square['15']
            + $this->square['18']
            + $this->square['21']
            + $this->square['24']
            + $this->square['26'];

        // Total a deducir
        $this->square['45'] = $this->square['29']
            + $this->square['31']
            + $this->square['33']
            + $this->square['35']
            + $this->square['37']
            + $this->square['39']
            + $this->square['41']
            + $this->square['42']
            + $this->square['43']
            + $this->square['44'];

        // Resultado régimen general
        $this->square['46'] = $this->square['27'] - $this->square['45'];
    }

    /**
     * Records a warning for an amount that could not be assigned to any square,
     * so the user can understand why the summary may not match the purchases/sales tabs.
     *
     * @param string $tipo
     * @param string $operacion
     * @param float $base
     * @param float $cuota
     * @param float|null $tax
     * @return void
     */
    private function registrarNoMapeado(string $tipo, string $operacion, float $base, float $cuota, ?float $tax = null): void
    {
        // No avisamos si no hay importe relevante.
        if (empty($base) && empty($cuota)) {
            return;
        }

        $this->avisos[] = Tools::trans('model303-amount-without-square', [
            '%type%' => $tipo,
            '%rate%' => $tax === null ? '-' : Tools::number($tax, 2),
            '%operation%' => $operacion === '' ? '-' : $operacion,
            '%base%' => Tools::number($base, 2),
            '%quota%' => Tools::number($cuota, 2),
        ]);
    }

    /**
     * Calcula las fechas de inicio y fin del periodo, con el año del inicio del ejercicio.
     *
     * El periodo anual se acepta con todos los nombres que usan el plugin y sus llamadores:
     * ANNUAL (plugin Informes), Y (periodo de RegularizacionImpuesto), ANUAL (la API), A y 0A
     * (fichero de la AEAT).
     *
     * @param Ejercicio $exercise
     * @param string $period T1, T2, T3, T4 o el anual, en mayúsculas.
     * @return array{0: string, 1: string} fechas en formato Y-m-d, el que entienden MySQL y PostgreSQL.
     */
    protected static function treasuryDates(Ejercicio $exercise, string $period): array
    {
        if (in_array($period, self::ANNUAL_PERIODS, true)) {
            $period = 'ANNUAL';
        }

        // si el periodo no es T1, T2, T3, T4 o el anual, se asume que es el primer trimestre
        if (!in_array($period, ['T1', 'T2', 'T3', 'T4', 'ANNUAL'], true)) {
            $period = 'T1';
        }

        $year = date('Y', strtotime($exercise->fechainicio));
        return match ($period) {
            'T1' => [$year . '-01-01', $year . '-03-31'],
            'T2' => [$year . '-04-01', $year . '-06-30'],
            'T3' => [$year . '-07-01', $year . '-09-30'],
            'ANNUAL' => [$year . '-01-01', $year . '-12-31'],
            default => [$year . '-10-01', $year . '-12-31'],
        };
    }

    /**
     * Suma debe menos haber de las partidas de las subcuentas que empiezan por el patrón, en
     * los asientos del ejercicio dentro del rango de fechas.
     *
     * @param string $cuenta patrón LIKE de la subcuenta (por ejemplo 477%).
     * @param string $codejercicio ejercicio de los asientos; limita la suma a su empresa.
     * @param string $desde fecha de inicio en formato Y-m-d.
     * @param string $hasta fecha de fin en formato Y-m-d.
     * @param DataBase $dataBase
     * @return float
     */
    protected static function treasurySaldoCuenta(string $cuenta, string $codejercicio, string $desde, string $hasta, DataBase $dataBase): float
    {
        $saldo = 0.0;

        if ($dataBase->tableExists('partidas')) {
            // calculamos el saldo de los asientos del ejercicio en el periodo
            $sql = "select sum(debe-haber) as total from partidas where codsubcuenta LIKE " . $dataBase->var2str($cuenta)
                . " and idasiento in (select idasiento from asientos where codejercicio = " . $dataBase->var2str($codejercicio)
                . " and fecha >= " . $dataBase->var2str($desde)
                . " and fecha <= " . $dataBase->var2str($hasta) . ");";

            $data = $dataBase->select($sql);
            if ($data && $data[0]['total'] !== null) {
                $saldo = floatval($data[0]['total']);
            }
        }

        return $saldo;
    }
}
