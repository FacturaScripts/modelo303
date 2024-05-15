<?php
/**
 * This file is part of Modelo303 plugin for FacturaScripts
 * Copyright (C) 2019-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
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

namespace FacturaScripts\Plugins\Modelo303\Lib\Accounting;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\RegularizacionImpuesto;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\SubAccountTools;
use FacturaScripts\Dinamic\Model\Asiento;
use FacturaScripts\Dinamic\Model\Join\PartidaImpuestoResumen;
use FacturaScripts\Dinamic\Model\Subcuenta;

/**
 * Class for the accounting of tax regularizations
 *
 * @author Carlos García Gómez           <carlos@facturascripts.com>
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class VatRegularizationToAccounting
{
    /** @var float */
    private $credit = 0.0;

    /** @var float */
    private $debit = 0.0;

    public function generate(RegularizacionImpuesto &$reg): bool
    {
        // creamos el asiento contable
        $accEntry = new Asiento();
        $accEntry->codejercicio = $reg->codejercicio;
        $accEntry->concepto = Tools::lang()->trans('vat-regularization') . ' ' . $reg->periodo;
        $accEntry->fecha = $reg->fechafin;
        $accEntry->idempresa = $reg->idempresa;
        if (false === $accEntry->save()) {
            Tools::log()->warning('accounting-entry-error');
            return false;
        }

        if ($this->addAccountingTaxLines($accEntry, $reg) &&
            $this->addAccountingResultLine($accEntry, $reg) &&
            $accEntry->isBalanced()) {

            $accEntry->importe = max([$this->debit, $this->credit]);
            if ($accEntry->save()) {
                $reg->idasiento = $accEntry->primaryColumnValue();
                $reg->fechaasiento = $accEntry->fecha;
                return true;
            }
        }

        Tools::log()->warning('accounting-lines-error');
        $accEntry->delete();
        return false;
    }

    protected function addAccountingResultLine(Asiento $accEntry, RegularizacionImpuesto $reg): bool
    {
        $subaccount = new Subcuenta();

        // si el debe es mayor que el haber, seleccionamos la cuenta de acreedores
        $id = $this->debit >= $this->credit ? $reg->idsubcuentaacr : $reg->idsubcuentadeu;
        if (false === $subaccount->loadFromCode($id)) {
            return false;
        }

        $newLine = $accEntry->getNewLine();
        $newLine->setAccount($subaccount);

        if ($this->debit >= $this->credit) {
            $newLine->haber = $this->debit - $this->credit;
        } else {
            $newLine->debe = $this->credit - $this->debit;
        }

        return $newLine->save();
    }

    protected function addAccountingTaxLines(Asiento $accEntry, RegularizacionImpuesto $reg): bool
    {
        foreach ($this->getSubtotals($reg) as $idsubcuenta => $total) {
            $subaccount = new Subcuenta();
            if (false === $subaccount->loadFromCode($idsubcuenta)) {
                return false;
            }

            $newLine = $accEntry->getNewLine();
            $newLine->setAccount($subaccount);
            $newLine->debe = round($total['debe'], FS_NF0);
            $newLine->haber = round($total['haber'], FS_NF0);
            if ($newLine->save()) {
                $this->debit += $newLine->debe;
                $this->credit += $newLine->haber;
                continue;
            }

            return false;
        }

        return true;
    }

    protected function getSubtotals(RegularizacionImpuesto $reg): array
    {
        $accTools = new SubAccountTools();
        $field = 'COALESCE(subcuentas.codcuentaesp, cuentas.codcuentaesp)';
        $where = [
            new DataBaseWhere('asientos.codejercicio', $reg->codejercicio),
            new DataBaseWhere('asientos.fecha', $reg->fechainicio, '>='),
            new DataBaseWhere('asientos.fecha', $reg->fechafin, '<='),
            $accTools->whereForSpecialAccounts($field, SubAccountTools::SPECIAL_GROUP_TAX_ALL)
        ];
        $orderBy = [
            $field => 'ASC',
            'partidas.iva' => 'ASC',
            'partidas.recargo' => 'ASC'
        ];

        $subtotals = [];
        $inputTaxGroup = $accTools->specialAccountsForGroup(SubAccountTools::SPECIAL_GROUP_TAX_INPUT);
        $outputTaxGroup = $accTools->specialAccountsForGroup(SubAccountTools::SPECIAL_GROUP_TAX_OUTPUT);
        $totals = new PartidaImpuestoResumen();
        foreach ($totals->all($where, $orderBy) as $row) {
            if (!isset($subtotals[$row->idsubcuenta])) {
                $subtotals[$row->idsubcuenta] = ['debe' => 0.0, 'haber' => 0.0];
            }

            if (in_array($row->codcuentaesp, $outputTaxGroup)) {
                $subtotals[$row->idsubcuenta]['debe'] += $row->cuotaiva + $row->cuotarecargo;
            } elseif (in_array($row->codcuentaesp, $inputTaxGroup)) {
                $subtotals[$row->idsubcuenta]['haber'] += $row->cuotaiva + $row->cuotarecargo;
            }
        }

        return $subtotals;
    }
}
