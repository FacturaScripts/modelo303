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
use FacturaScripts\Dinamic\Model\Ejercicio;
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
     * Stores all model squares.
     * Each key is the AEAT square number.
     *   '01' => 0.00, '02' => 0.00, ...
     */
    private array $square;

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
            '7.5' => ['base' => '153', 'cuota' => '155'],
            '10'  => ['base' => '04', 'cuota' => '06'],
            '21'  => ['base' => '07', 'cuota' => '09'],
        ],

        // Adquisiciones intracomunitarias
        'IVARUE' => ['21' => ['base' => '10', 'cuota' => '11']],

        // Operaciones con inversión del sujeto pasivo
        // TODO: 'xxxxx' =>  ['21' => ['base' => '12', 'cuota' => '13']],

        // Recargo de equivalencia
        'IVARRE' => [
            '1.75' => ['base' => '156', 'cuota' => '158'],
            '0.26' => ['base' => '168',  'cuota' => '170'],
            '1'    => ['base' => '16',  'cuota' => '18'],
            '1.4'  => ['base' => '19',  'cuota' => '21'],
            '5.2'  => ['base' => '22',  'cuota' => '24'],
        ],

        // Operaciones exentas
        'IVAREX' => ['0' => ['base' => '150', 'cuota' => null]],

        /*
         * IVA soportado (deducible)
         * Compras nacionales (régimen general)
         */
        'IVASOP' => [
            '21' => ['base' => '28', 'cuota' => '29'],
            '10' => ['base' => '28', 'cuota' => '29'],
            '4'  => ['base' => '28', 'cuota' => '29'],
        ],

        // Compras en importaciones
        'IVASIM' => ['21' => ['base' => '32', 'cuota' => '33']],

        // Compras en adquisiciones intracomunitarias
        'IVASUE' => ['21' => ['base' => '36', 'cuota' => '37']],

        // Operaciones exentas
        'IVASEX' => ['0'  => ['base' => '60', 'cuota' => null]],
    ];

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
        $this->square['154'] = 7.50;
        $this->square['157'] = 1.75;
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
     * Loads summary data from an array of PartidaImpuestoResumen.
     *
     * @param PartidaImpuestoResumen[] $resumen
     */
    public function loadFromResumen(array $resumen): void
    {
        foreach ($resumen as $item) {
            $this->addMovimiento(
                $item->codcuentaesp ?? '',
                (float) $item->iva,
                (float) $item->recargo,
                (float) $item->baseimponible,
                (float) $item->cuota
            );
        }
        $this->calculateTotals();
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

    /**
     * Add a tax movement to the model (base + quota by type and rate)
     * - Determine the correct square based on the type and tax rate.
     * - Update the base and quota squares accordingly.
     *
     * @param string $tipo
     * @param float $iva
     * @param float $recargo
     * @param float $base
     * @param float $cuota
     * @return void
     */
    private function addMovimiento(string $tipo, float $iva, float $recargo, float $base, float $cuota): void
    {
        if (false === isset($this->casillaMap[$tipo])) {
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
            return;
        }

        // Update base and quota squares.
        if (false === empty($grupo['base'])) {
            $this->square[$grupo['base']] += $base;
        }

        if (false === empty($grupo['cuota'])) {
            // For recargo, if cuota is zero, calculate it from base and recargo rate
            if ($tipo === 'IVARRE' && $cuota == 0.0 && $recargo > 0.0) {
                $cuota = $base * ($recargo / 100.0);
            }
            $this->square[$grupo['cuota']] += $cuota;
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
}
