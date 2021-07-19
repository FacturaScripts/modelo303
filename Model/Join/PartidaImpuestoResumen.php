<?php
/**
 * This file is part of Modelo303 plugin for FacturaScripts
 * Copyright (C) 2017-2021 Carlos Garcia Gomez <carlos@facturascripts.com>
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
 * @author Carlos García Gómez           <carlos@facturascripts.com>
 *
 * @property float  $baseimponible
 * @property string $codcuentaesp
 * @property string $codejercicio
 * @property string $codsubcuenta
 * @property float  $cuotaiva
 * @property float  $cuotarecargo
 * @property string $descripcion
 * @property int    $idsubcuenta
 * @property float  $iva
 * @property float  $recargo
 * @property float  $total
 */
class PartidaImpuestoResumen extends JoinModel
{

    /**
     * Reset the values of all model view properties.
     */
    public function clear()
    {
        parent::clear();
        $this->baseimponible = 0.0;
        $this->iva = 0.0;
        $this->recargo = 0.0;
        $this->cuotaiva = 0.0;
        $this->cuotarecargo = 0.0;
        $this->total = 0.0;
    }

    /**
     * Returns an array of fields for the select clausule.
     * 
     * @return array
     */
    protected function getFields(): array
    {
        return [
            'baseimponible' => 'SUM(partidas.baseimponible)',
            'codcuentaesp' => 'COALESCE(subcuentas.codcuentaesp, cuentas.codcuentaesp)',
            'codejercicio' => 'asientos.codejercicio',
            'codsubcuenta' => 'partidas.codsubcuenta',
            'descripcion' => 'cuentasesp.descripcion',
            'idsubcuenta' => 'partidas.idsubcuenta',
            'iva' => 'partidas.iva',
            'recargo' => 'partidas.recargo'
        ];
    }

    /**
     * Returns a string with the group by fields.
     *
     * @return string
     */
    protected function getGroupFields(): string
    {
        return 'asientos.codejercicio,'
            . 'COALESCE(subcuentas.codcuentaesp, cuentas.codcuentaesp),'
            . 'cuentasesp.descripcion,'
            . 'partidas.idsubcuenta,'
            . 'partidas.codsubcuenta,'
            . 'partidas.iva,'
            . 'partidas.recargo';
    }

    /**
     * Returns a string with the tables related to from clausule.
     * 
     * @return string
     */
    protected function getSQLFrom(): string
    {
        return 'asientos'
            . ' LEFT JOIN partidas ON partidas.idasiento = asientos.idasiento'
            . ' LEFT JOIN subcuentas ON subcuentas.idsubcuenta = partidas.idsubcuenta'
            . ' LEFT JOIN cuentas ON cuentas.idcuenta = subcuentas.idcuenta'
            . ' LEFT JOIN cuentasesp ON cuentasesp.codcuentaesp = COALESCE(subcuentas.codcuentaesp, cuentas.codcuentaesp)';
    }

    /**
     * Returns an array of tables required for the select clausule.
     * 
     * @return array
     */
    protected function getTables(): array
    {
        return [
            'asientos',
            'partidas',
            'subcuentas',
            'cuentas',
            'cuentasesp'
        ];
    }

    /**
     * Assign the values of the $data array to the model properties.
     *
     * @param array $data
     */
    protected function loadFromData($data)
    {
        parent::loadFromData($data);
        $this->cuotaiva = $this->baseimponible * ($this->iva / 100.0);
        $this->cuotarecargo = $this->baseimponible * ($this->recargo / 100.0);
        $this->total = $this->baseimponible + $this->cuotaiva + $this->cuotarecargo;
    }
}
