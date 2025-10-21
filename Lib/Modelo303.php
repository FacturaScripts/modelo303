<?php
/**
 * This file is part of Modelo303 plugin for FacturaScripts
 * Copyright (C) 2017-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
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

use FacturaScripts\Core\DataSrc\Impuestos;
use FacturaScripts\Core\Tools;

/**
 * Class to handle Modelo 303 tax form data.
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class Modelo303
{
    private const MAX_SQUARE = 200;

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
        $this->square['157'] = 1.75;
        $this->square['169'] = 0.5;
        $this->square['20'] = 1.4;
        $this->square['23'] = 5.2;
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
    public function casillaStr(string $square): string
    {
        $value = $this->casilla($square);
        return empty($value) ? '' : Tools::number($value, 2);
    }

    /**
     * Assign movements to the corresponding squares.
     * Calculates the totals squares based on the provided movements.
     *
     * @param array $groupedMovs
     * @return void
     */
    public function setMovements(array $groupedMovs): void
    {
        // obtenemos los códigos de subcuentas agrupados según tipo iva
        // esto lo hacemos por si existen varios impuestos
        // del mismo iva y distintas subcuentas
        $subaccountsVAT = [];
        $subaccountsSurcharge = [];
        foreach (Impuestos::all() as $tax) {
            $subaccountsVAT[$tax->iva]['repercutido'][] = $tax->codsubcuentarep;
            $subaccountsVAT[$tax->iva]['soportado'][] = $tax->codsubcuentasop;
            $subaccountsSurcharge[$tax->recargo]['repercutido'][] = $tax->codsubcuentarepre;
            $subaccountsSurcharge[$tax->recargo]['soportado'][] = $tax->codsubcuentasopre;
        }

        // aplicamos los movimientos a las casillas correspondientes
        $this->applyVATCharged($groupedMovs, $subaccountsVAT, $subaccountsSurcharge);
        $this->applyVATPaid($groupedMovs, $subaccountsVAT, $subaccountsSurcharge);

        // Resultado régimen general
        $this->square['46'] = $this->square['27'] - $this->square['45'];
    }

    private function applyVATCharged(array $groupedMovs, array $subaccountsVAT, array $subaccountsSurcharge): void
    {
        foreach ($groupedMovs as $subaccount => $movements) {
            foreach ($movements as $mov) {
                // IVA 21%
                if (in_array($subaccount, $subaccountsVAT[21]['repercutido'])) {
                    $this->square['07'] += $mov->baseimponible;
                    $this->square['09'] += $mov->haber;
                    continue;
                }

                // IVA 10%
                if (in_array($subaccount, $subaccountsVAT[10]['repercutido'])) {
                    $this->square['04'] += $mov->baseimponible;
                    $this->square['06'] += $mov->haber;
                    continue;
                }

                // IVA 4%
                if (in_array($subaccount, $subaccountsVAT[4]['repercutido'])) {
                    $this->square['01'] += $mov->baseimponible;
                    $this->square['03'] += $mov->haber;
                    continue;
                }

                // IVA 0%
                if (in_array($subaccount, $subaccountsVAT[0]['repercutido'])) {
                    $this->square['150'] += $mov->baseimponible;
                    $this->square['152'] += $mov->haber;
                    continue;
                }

                // RECARGO 1.75%
                if (in_array($subaccount, $subaccountsSurcharge[1.75]['repercutido'])) {
                    $this->square['156'] += $mov->baseimponible;
                    $this->square['158'] += $mov->haber;
                    continue;
                }

                // RECARGO 0.5%
                if (in_array($subaccount, $subaccountsSurcharge[0.5]['repercutido'])) {
                    $this->square['168'] += $mov->baseimponible;
                    $this->square['170'] += $mov->haber;
                    continue;
                }

                // RECARGO 1.4%
                if (in_array($subaccount, $subaccountsSurcharge[1.4]['repercutido'])) {
                    $this->square['19'] += $mov->baseimponible;
                    $this->square['21'] += $mov->haber;
                    continue;
                }

                // RECARGO 5.2%
                if (in_array($subaccount, $subaccountsSurcharge[5.2]['repercutido'])) {
                    $this->square['22'] += $mov->baseimponible;
                    $this->square['24'] += $mov->haber;
                }
            }
        }

        // JOSEA: Existen casillas que no están calculadas
        // Total cuota devengada
        $this->square['27'] = $this->square['152']
            + $this->square['167']
            + $this->square['03']       // Cuota IVA 4%
            + $this->square['155']
            + $this->square['06']       // Cuota IVA 10%
            + $this->square['09']       // Cuota IVA 21%
            + $this->square['11']
            + $this->square['13']
            + $this->square['15']
            + $this->square['158']      // Cuota recargo 1.75%
            + $this->square['170']      // Cuota recargo 0.5%
            + $this->square['18']
            + $this->square['21']       // Cuota recargo 1.4%
            + $this->square['24']       // Cuota recargo 5.2%
            + $this->square['26'];
    }

    private function applyVATPaid(array $groupedMovs, array $subaccountsVAT): void
    {
        // JoseA: Todo se acumula en las casillas 28 y 29
        foreach ($groupedMovs as $subaccount => $movements) {
            foreach ($movements as $mov) {
                // IVA 21%
                if (in_array($subaccount, $subaccountsVAT[21]['soportado'])) {
                    $this->square['28'] += $mov->baseimponible;
                    $this->square['29'] += $mov->debe;
                    continue;
                }

                // IVA 10%
                if (in_array($subaccount, $subaccountsVAT[10]['soportado'])) {
                    $this->square['28'] += $mov->baseimponible;
                    $this->square['29'] += $mov->debe;
                    continue;
                }

                // IVA 4%
                if (in_array($subaccount, $subaccountsVAT[4]['soportado'])) {
                    $this->square['28'] += $mov->baseimponible;
                    $this->square['29'] += $mov->debe;
                }
            }
        }

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
    }
}