<?php
/**
 * This file is part of Modelo303 plugin for FacturaScripts
 * Copyright (C) 2019-2026 Carlos Garcia Gomez <carlos@facturascripts.com>
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

use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Lib\SubAccountTools;
use FacturaScripts\Dinamic\Model\Asiento;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Dinamic\Model\FacturaProveedor;
use FacturaScripts\Dinamic\Model\RegularizacionImpuesto;
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
    /**
     * Suma del haber de las líneas de IVA ya guardadas en el asiento.
     *
     * @var float
     */
    private float $credit = 0.0;

    /**
     * Suma del debe de las líneas de IVA ya guardadas en el asiento.
     *
     * @var float
     */
    private float $debit = 0.0;

    /**
     * Genera el asiento de regularización de IVA: salda las subcuentas de IVA repercutido y
     * soportado del periodo y lleva la diferencia a la subcuenta de acreedores o deudores. Si
     * hay facturas sin asiento o el asiento no cuadra, no deja nada guardado y devuelve false.
     * Si todo va bien, anota en la regularización el asiento y su fecha (sin guardarla).
     *
     * @param RegularizacionImpuesto $reg
     * @return bool
     */
    public function generate(RegularizacionImpuesto &$reg): bool
    {
        if (false === $this->checkInvoicesWithoutAccEntry($reg)) {
            Tools::log()->warning('invoices-without-acc-entry');
            return false;
        }

        // creamos el asiento contable
        $accEntry = new Asiento();
        $accEntry->codejercicio = $reg->codejercicio;
        $accEntry->concepto = Tools::trans('vat-regularization') . ' ' . $reg->periodo;
        $accEntry->fecha = $reg->fechafin;
        $accEntry->idempresa = $reg->idempresa;
        if (false === $accEntry->save()) {
            // el concepto identifica la regularización con su periodo, p. ej. «Regularización de IVA T1»
            Tools::log()->warning('accounting-entry-error', ['%document%' => $accEntry->concepto]);
            return false;
        }

        if ($this->addAccountingTaxLines($accEntry, $reg) &&
            $this->addAccountingResultLine($accEntry, $reg) &&
            $accEntry->isBalanced()) {
            $accEntry->importe = max([$this->debit, $this->credit]);
            if ($accEntry->save()) {
                $reg->idasiento = $accEntry->id();
                $reg->fechaasiento = $accEntry->fecha;
                return true;
            }
        }

        Tools::log()->warning('accounting-lines-error');
        $accEntry->delete();
        return false;
    }

    /**
     * Añade la línea de resultado: la diferencia entre el IVA devengado y el deducible, a la
     * subcuenta de acreedores (a pagar) o de deudores (a devolver) de la regularización.
     *
     * @param Asiento $accEntry
     * @param RegularizacionImpuesto $reg
     * @return bool
     */
    protected function addAccountingResultLine(Asiento $accEntry, RegularizacionImpuesto $reg): bool
    {
        $subaccount = new Subcuenta();

        // si el debe es mayor que el haber, seleccionamos la cuenta de acreedores
        $id = $this->debit >= $this->credit ? $reg->idsubcuentaacr : $reg->idsubcuentadeu;
        if (false === $subaccount->load($id)) {
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

    /**
     * Añade una línea por cada subcuenta de IVA con el total de sus cuotas (IVA más recargo),
     * en el debe las de repercutido y en el haber las de soportado.
     *
     * @param Asiento $accEntry
     * @param RegularizacionImpuesto $reg
     * @return bool
     */
    protected function addAccountingTaxLines(Asiento $accEntry, RegularizacionImpuesto $reg): bool
    {
        foreach ($this->getSubtotals($reg) as $idsubcuenta => $total) {
            $subaccount = new Subcuenta();
            if (false === $subaccount->load($idsubcuenta)) {
                return false;
            }

            $newLine = $accEntry->getNewLine();
            $newLine->setAccount($subaccount);
            $newLine->debe = round($total['debe'], Tools::settings('default', 'decimals'));
            $newLine->haber = round($total['haber'], Tools::settings('default', 'decimals'));
            if ($newLine->save()) {
                $this->debit += $newLine->debe;
                $this->credit += $newLine->haber;
                continue;
            }

            return false;
        }

        return true;
    }

    /**
     * Comprueba si hay facturas sin asiento contable
     * y cuyo importe sea distinto de cero.
     *
     * @param $reg
     *
     * @return bool
     */
    private function checkInvoicesWithoutAccEntry($reg): bool
    {
        $where = [
            Where::eq('codejercicio', $reg->codejercicio),
            Where::eq('idempresa', $reg->idempresa),
            Where::gte('fecha', $reg->fechainicio),
            Where::lte('fecha', $reg->fechafin),
            Where::isNull('idasiento'),
            Where::notEq('total', 0),
        ];

        $facturasSinAsiento = FacturaCliente::all($where, [], 0, 1);
        if (false === empty($facturasSinAsiento)) {
            return false;
        }

        $facturasSinAsiento = FacturaProveedor::all($where, [], 0, 1);
        return empty($facturasSinAsiento);
    }

    /**
     * Totales de cuota por subcuenta de IVA del periodo, con el mismo filtro de partidas que
     * Modelo303::taxWhere() para que el asiento cuadre con el resumen.
     *
     * @param RegularizacionImpuesto $reg
     * @return array<int, array{debe: float, haber: float}> indexado por idsubcuenta.
     */
    protected function getSubtotals(RegularizacionImpuesto $reg): array
    {
        $accTools = new SubAccountTools();
        $field = 'COALESCE(subcuentas.codcuentaesp, cuentas.codcuentaesp)';
        $excludedOperations = implode(',', [Asiento::OPERATION_OPENING, Asiento::OPERATION_CLOSING]);

        // mismo criterio que las pestañas Resumen/Compras/Ventas para que el asiento cuadre con ellas
        $regIds = [];
        foreach (RegularizacionImpuesto::all() as $other) {
            if ($other->idasiento) {
                $regIds[] = $other->idasiento;
            }
        }

        $where = [
            Where::eq('asientos.idempresa', $reg->idempresa),
            Where::eq('asientos.codejercicio', $reg->codejercicio),
            Where::gte('asientos.fecha', $reg->fechainicio),
            Where::lte('asientos.fecha', $reg->fechafin),
            Where::eq('COALESCE(series.siniva, false)', false),
            Where::notIn("COALESCE(asientos.operacion, '')", $excludedOperations),
            $accTools->whereForSpecialAccounts($field, SubAccountTools::SPECIAL_GROUP_TAX_ALL)
        ];

        if (false === empty($regIds)) {
            $where[] = Where::notIn('asientos.idasiento', implode(',', $regIds));
        }
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
