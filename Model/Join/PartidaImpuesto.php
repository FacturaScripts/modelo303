<?php
/**
 * This file is part of Modelo303 plugin for FacturaScripts
 * Copyright (C) 2017-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
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

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\Base\BusinessDocument;
use FacturaScripts\Core\Model\Base\JoinModel;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Dinamic\Model\FacturaProveedor;

/**
 * Auxiliary model to load a list of accounting entries with VAT
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 * @author Carlos García Gómez           <carlos@facturascripts.com>
 *
 * @property float $baseimponible
 * @property float $cuotaiva
 * @property float $cuotarecargo
 * @property float $iva
 * @property float $recargo
 */
class PartidaImpuesto extends JoinModel
{
    /**
     * Reset the values of all model view properties.
     */
    public function clear()
    {
        parent::clear();
        $this->baseimponible = 0.00;
        $this->iva = 0.00;
        $this->cuotaiva = 0.00;
        $this->recargo = 0.00;
        $this->cuotarecargo = 0.00;
    }

    protected function getFactura(): BusinessDocument
    {
        $where = [new DataBaseWhere('idasiento', $this->idasiento ?? 0)];

        $facturaCliente = new FacturaCliente();
        if ($facturaCliente->loadFromCode('', $where)) {
            return $facturaCliente;
        }

        $facturaProveedor = new FacturaProveedor();
        $facturaProveedor->loadFromCode('', $where);
        return $facturaProveedor;
    }

    /**
     * List of fields or columns to select clausule
     */
    protected function getFields(): array
    {
        return [
            'baseimponible' => 'partidas.baseimponible',
            'cifnif' => 'partidas.cifnif',
            'codcontrapartida' => 'partidas.codcontrapartida',
            'codcuentaesp' => 'COALESCE(subcuentas.codcuentaesp, cuentas.codcuentaesp)',
            'codejercicio' => 'asientos.codejercicio',
            'concepto' => 'partidas.concepto',
            'codserie' => 'partidas.codserie',
            'documento' => 'partidas.documento',
            'factura' => 'partidas.factura',
            'fecha' => 'asientos.fecha',
            'idasiento' => 'asientos.idasiento',
            'idcontrapartida' => 'partidas.idcontrapartida',
            'idpartida' => 'partidas.idpartida',
            'iva' => 'partidas.iva',
            'numero' => 'asientos.numero',
            'recargo' => 'partidas.recargo',
            'debe' => 'partidas.debe',
            'haber' => 'partidas.haber',
        ];
    }

    /**
     * List of tables related to from clausule
     */
    protected function getSQLFrom(): string
    {
        return 'asientos'
            . ' INNER JOIN partidas ON partidas.idasiento = asientos.idasiento'
            . ' INNER JOIN subcuentas ON subcuentas.idsubcuenta = partidas.idsubcuenta'
            . ' INNER JOIN cuentas ON cuentas.idcuenta = subcuentas.idcuenta';
    }

    /**
     * List of tables required for the execution of the view.
     */
    protected function getTables(): array
    {
        return [
            'asientos',
            'partidas',
            'subcuentas',
            'cuentas'
        ];
    }

    /**
     * Assign the values of the $data array to the model view properties.
     *
     * @param array $data
     */
    protected function loadFromData(array $data)
    {
        parent::loadFromData($data);

        if ($this->iva > 0 && $this->recargo > 0) {
            $this->cuotaiva = $this->baseimponible * ($this->iva / 100.0);
            $this->cuotarecargo = $this->baseimponible * ($this->recargo / 100.0);
        } elseif ($this->iva > 0) {
            $this->cuotaiva = $data['debe'] > 0 ? $data['debe'] : $data['haber'];
            $this->cuotarecargo = 0.0;
        } else {
            $this->cuotaiva = 0.0;
            $this->cuotarecargo = $data['debe'] > 0 ? $data['debe'] : $data['haber'];
        }

        // si el campo factura está vacío, buscamos la factura con este asiento
        if (empty($this->factura)) {
            $factura = $this->getFactura();
            if ($factura->primaryColumnValue()) {
                $this->factura = $factura->numero;
                $this->documento = $factura->codigo;
                $this->codserie = $factura->codserie;
            }
        }
    }
}
