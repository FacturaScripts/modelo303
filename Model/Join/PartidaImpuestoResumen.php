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
namespace FacturaScripts\Plugins\Modelo303\Model\Join;

use FacturaScripts\Core\Model\Base\JoinModel;

/**
 * Auxiliary model to load a resume of accounting entries with VAT
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class PartidaImpuestoResumen extends JoinModel
{
    /**
     * Returns an array of fields for the select clausule.
     *
     * @return array
     */
    protected function getFields(): array
    {
        return [
            'codsubcuenta' => 'partidas.codsubcuenta',
            'iva' => 'COALESCE(partidas.iva, 0)',
            'recargo' => 'COALESCE(partidas.recargo, 0)',

            'descripcion' => 'subcuentas.descripcion',

            'codcuentaesp' => 'COALESCE(subcuentas.codcuentaesp, cuentas.codcuentaesp)',
            'tipo_desc' => 'cuentasesp.descripcion',

            'baseimponible' => 'ROUND(SUM(partidas.baseimponible), 2)',
            'debe' => 'ROUND(SUM(partidas.debe), 2)',
            'haber' => 'ROUND(SUM(partidas.haber), 2)',
            'cuota' => 'ROUND(SUM(' . $this->sqlForCuota() . '), 2)',
        ];
    }

    /**
     * Returns a string with the group by fields.
     *
     * @return string
     */
    protected function getGroupFields(): string
    {
        return 'partidas.codsubcuenta'
            . ', partidas.iva'
            . ', partidas.recargo'
            . ', subcuentas.descripcion'
            . ', COALESCE(subcuentas.codcuentaesp, cuentas.codcuentaesp)'
            . ', cuentasesp.descripcion';
    }

    /**
     * Returns a string with the tables related to from clausule.
     *
     * @return string
     */
    protected function getSQLFrom(): string
    {
        return 'partidas'
            . ' INNER JOIN asientos on asientos.idasiento = partidas.idasiento'
            . ' INNER JOIN subcuentas on subcuentas.idsubcuenta = partidas.idsubcuenta'
            . ' INNER JOIN cuentas on cuentas.idcuenta = subcuentas.idcuenta'
            . ' LEFT JOIN cuentasesp on cuentasesp.codcuentaesp = coalesce(subcuentas.codcuentaesp, cuentas.codcuentaesp)'
            . ' LEFT JOIN series on series.codserie = partidas.codserie';
    }

    /**
     * Returns an array of tables required for the select clausule.
     *
     * @return array
     */
    protected function getTables(): array
    {
        return [
            'partidas',
            'asientos',
            'subcuentas',
            'cuentas',
            'cuentasesp',
            'series',
        ];
    }

    /**
     * SQL snippet to calculate the cuota field.
     *
     * @return string
     */
    private function sqlForCuota(): string
    {
        return 'CASE WHEN partidas.baseimponible < 0 AND (partidas.debe + partidas.haber) > 0
                      THEN (partidas.debe + partidas.haber) * -1
                      ELSE partidas.debe + partidas.haber
                  END';
    }
}
