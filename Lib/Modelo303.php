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
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Lib\InvoiceOperation;
use FacturaScripts\Dinamic\Model\Ejercicio;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Dinamic\Model\Join\PartidaImpuestoResumen;

/**
 * Class to handle Modelo 303 tax form data.
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class Modelo303
{
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

        // Recargo de equivalencia
        'IVARRE' => [
            '1.75' => ['base' => '156', 'cuota' => '158'],
            '0.26' => ['base' => '168',  'cuota' => '170'],
            '1'    => ['base' => '16',  'cuota' => '18'],
            '1.4'  => ['base' => '19',  'cuota' => '21'],
            '5.2'  => ['base' => '22',  'cuota' => '24'],
        ],

        // Operaciones exentas
        'IVAREX' => ['*' => ['base' => '150', 'cuota' => null]],

        /*
         * IVA soportado (deducible)
         * Compras nacionales (régimen general)
         */
        'IVASOP' => [
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
        $this->square['17'] = 1.00;
        $this->square['20'] = 1.40;
        $this->square['23'] = 5.20;
        $this->square['154'] = 5.00;
        $this->square['157'] = 1.75;
        $this->square['163'] = 7.50;
        $this->square['169'] = 0.26;
        $this->square['166'] = 2.00;
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

    public static function treasury(string $codejercicio, string $period): array
    {
        // comprobamos que el ejercicio existe
        $exercise = new Ejercicio();
        if (false === $exercise->load($codejercicio)) {
            return [];
        }

        $dataBase = new DataBase();
        $period = strtoupper($period);
        list($dateStart, $dateEnd) = static::treasuryDates($exercise, $period);

        return [
            'iva-repercutido' => static::treasurySaldoCuenta('477%', $dateStart, $dateEnd, $dataBase),
            'iva-soportado' => static::treasurySaldoCuenta('472%', $dateStart, $dateEnd, $dataBase),
            'iva-devolver' => static::treasurySaldoCuenta('4700%', $dateStart, $dateEnd, $dataBase),
        ];
    }

    protected static function treasuryDates(Ejercicio $exercise, string $period): array
    {
        // si el periodo no es T1, T2, T3, T4 o Annual, se asume que es el primer trimestre
        if (!in_array($period, ['T1', 'T2', 'T3', 'T4', 'ANNUAL'])) {
            $period = 'T1';
        }

        return match ($period) {
            'T1' => [
                date('01-01-Y', strtotime($exercise->fechainicio)),
                date('31-03-Y', strtotime($exercise->fechainicio))
            ],
            'T2' => [
                date('01-04-Y', strtotime($exercise->fechainicio)),
                date('30-06-Y', strtotime($exercise->fechainicio))
            ],
            'T3' => [
                date('01-07-Y', strtotime($exercise->fechainicio)),
                date('30-09-Y', strtotime($exercise->fechainicio))
            ],
            'ANNUAL' => [
                date('01-01-Y', strtotime($exercise->fechainicio)),
                date('31-12-Y', strtotime($exercise->fechainicio))
            ],
            default => [
                date('01-10-Y', strtotime($exercise->fechainicio)),
                date('31-12-Y', strtotime($exercise->fechainicio))
            ],
        };
    }

    protected static function treasurySaldoCuenta(string $cuenta, string $desde, string $hasta, DataBase $dataBase): float
    {
        $saldo = 0.0;

        if ($dataBase->tableExists('partidas')) {
            // calculamos el saldo de todos aquellos asientos que afecten a caja
            $sql = "select sum(debe-haber) as total from partidas where codsubcuenta LIKE " . $dataBase->var2str($cuenta)
                . " and idasiento in (select idasiento from asientos where fecha >= " . $dataBase->var2str($desde)
                . " and fecha <= " . $dataBase->var2str($hasta) . ");";

            $data = $dataBase->select($sql);
            if ($data && $data[0]['total'] !== null) {
                $saldo = floatval($data[0]['total']);
            }
        }

        return $saldo;
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
        // Las operaciones especiales (ISP, intracomunitarias, importación) deciden la casilla
        // por el tipo de operación de la factura, no solo por la cuenta especial.
        if ($this->addMovimientoEspecial($tipo, $operacion, $tipodoc, $base, $cuota)) {
            return;
        }

        if (false === isset($this->casillaMap[$tipo])) {
            $this->registrarNoMapeado($tipo, $operacion, $base, $cuota);
            return;
        }

        // Determine the correct group based on the tax rate.
        $tax = ($tipo === 'IVARRE') ? $recargo : $iva;
        $key = rtrim(rtrim(number_format($tax, 1, '.', ''), '0'), '.');
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
     * @param float $base
     * @param float $cuota
     * @return bool
     */
    private function addMovimientoEspecial(string $tipo, string $operacion, string $tipodoc, float $base, float $cuota): bool
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

            case InvoiceOperation::IMPORT:
                // Importación (COMPRA): el IVA lo liquida la aduana (DUA). Solo el soportado deducible.
                if ($esDeducible) {
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
        // Total cuota devengada
        $this->square['27'] = $this->square['03']
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

        $this->avisos[] = Tools::lang()->trans('model303-amount-without-square', [
            '%type%' => $tipo,
            '%rate%' => $tax === null ? '-' : Tools::number($tax, 2),
            '%operation%' => $operacion === '' ? '-' : $operacion,
            '%base%' => Tools::number($base, 2),
            '%quota%' => Tools::number($cuota, 2),
        ]);
    }
}
