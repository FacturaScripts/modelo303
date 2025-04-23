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

namespace FacturaScripts\Plugins\Modelo303\Controller;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\DataSrc\Impuestos;
use FacturaScripts\Core\DataSrc\Series;
use FacturaScripts\Core\Lib\ExtendedController\BaseView;
use FacturaScripts\Core\Lib\ExtendedController\EditController;
use FacturaScripts\Core\Model\Asiento;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\Accounting\VatRegularizationToAccounting;
use FacturaScripts\Dinamic\Lib\SubAccountTools;
use FacturaScripts\Dinamic\Model\Join\PartidaImpuestoResumen;
use FacturaScripts\Dinamic\Model\Partida;
use FacturaScripts\Dinamic\Model\RegularizacionImpuesto;

/**
 * Controller to list the items in the RegularizacionImpuesto model
 *
 * @author Carlos García Gómez              <carlos@facturascripts.com>
 * @author Jose Antonio Cuello Principal    <yopli2000@gmail.com>
 * @author Cristo M. Estévez Hernández      <cristom.estevez@gmail.com>
 */
class EditRegularizacionImpuesto extends EditController
{
    /** @var float */
    public $purchases;

    /** @var float */
    public $sales;

    /** @var float */
    public $total;

    /** @var array */
    public array $modelo303 = [];

    public function getModelClassName(): string
    {
        return 'RegularizacionImpuesto';
    }

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'reports';
        $data['title'] = 'model-303-390';
        $data['icon'] = 'fas fa-balance-scale-right';
        return $data;
    }

    /**
     * Calculates the amounts for the different sections of the regularization
     *
     * @param PartidaImpuestoResumen[] $data
     */
    protected function calculateAmounts(array $data)
    {
        // Init totals values
        $this->sales = 0.0;
        $this->purchases = 0.0;

        $subAccountTools = new SubAccountTools();
        foreach ($data as $row) {
            if ($subAccountTools->isOutputTax($row->codcuentaesp)) {
                $this->sales += $row->cuotaiva + $row->cuotarecargo;
                continue;
            }

            if ($subAccountTools->isInputTax($row->codcuentaesp)) {
                $this->purchases += $row->cuotaiva + $row->cuotarecargo;
            }
        }

        $this->total = $this->sales - $this->purchases;
    }

    protected function createAccountingEntryAction()
    {
        $reg = new RegularizacionImpuesto();
        $code = $this->request->get('code');
        if (false === $reg->loadFromCode($code)) {
            Tools::log()->warning('record-not-found');
            return;
        }

        if ($reg->idasiento) {
            Tools::log()->warning('accounting-entry-already-created');
            return;
        }

        $accounting = new VatRegularizationToAccounting();
        if (false === $accounting->generate($reg)) {
            Tools::log()->warning('accounting-entry-not-created');
            return;
        }

        // lock accounting and save
        $reg->bloquear = true;
        if (false === $reg->save()) {
            Tools::log()->warning('record-save-error');
            return;
        }

        Tools::log()->notice('record-updated-correctly');
    }

    /**
     * Add the view set.
     */
    protected function createViews()
    {
        parent::createViews();
        $this->setTabsPosition('bottom');

        $this->createViewsTaxSummary();
        $this->createViewsEntryLine();
    }

    protected function createViewsEntryLine(string $viewName = 'ListPartida')
    {
        $this->addListView($viewName, 'Partida', 'accounting-entry', 'fas fa-balance-scale');
        $this->disableButtons($viewName, true);
    }

    protected function createViewsTaxLine(string $viewName, string $caption, string $icon)
    {
        $this->addListView($viewName, 'Join\PartidaImpuesto', $caption, $icon)
            ->addSearchFields(['partidas.concepto'])
            ->addFilterPeriod('date', 'date', 'fecha')
            ->addFilterSelect('iva', 'vat', 'partidas.iva', Impuestos::codeModel())
            ->addFilterSelect('codserie', 'serie', 'partidas.codserie', Series::codeModel());

        $this->disableButtons($viewName);
    }

    protected function createViewsTaxSummary(string $viewName = 'ListPartidaImpuestoResumen')
    {
        $this->addHtmlView($viewName, 'Modelo303', 'Impuesto', 'summary', 'fas fa-list-alt');
        $this->disableButtons($viewName);
    }

    protected function disableButtons(string $viewName, bool $clickable = false)
    {
        $this->setSettings($viewName, 'btnDelete', false);
        $this->setSettings($viewName, 'btnNew', false);
        $this->setSettings($viewName, 'checkBoxes', false);
        $this->setSettings($viewName, 'clickable', $clickable);
    }

    /**
     * Run the actions that alter data before reading it.
     *
     * @param string $action
     *
     * @return bool
     */
    protected function execPreviousAction($action)
    {
        switch ($action) {
            case 'create-accounting-entry':
                $this->createAccountingEntryAction();
                return true;
        }

        return parent::execPreviousAction($action);
    }

    protected function exportAction()
    {
        $this->exportManager->setOrientation('landscape');
        parent::exportAction();
    }

    protected function getListPartida(BaseView $view)
    {
        $idasiento = $this->getViewModelValue('EditRegularizacionImpuesto', 'idasiento');
        if (!empty($idasiento)) {
            $where = [new DataBaseWhere('idasiento', $idasiento)];
            $view->loadData(false, $where, ['orden' => 'ASC']);
        }
    }

    protected function getListPartidaImpuesto(BaseView $view, int $group)
    {
        $id = $this->getViewModelValue($this->getMainViewName(), 'idregiva');
        if (!empty($id)) {
            $where = $this->getPartidaImpuestoWhere($group);
            $orderBy = ['asientos.fecha' => 'ASC', 'partidas.codserie' => 'ASC', 'partidas.factura' => 'ASC'];
            $view->loadData(false, $where, $orderBy);
        }
    }

    protected function getListPartidaImpuestoResumen(BaseView $view)
    {
        $impuestos = Impuestos::all();

        // obtenemos los codigos de subcuentas de los impuestos
        $subcuentas = array_values(array_unique(array_filter(array_merge(
            array_column($impuestos, 'codsubcuentarep'),
            array_column($impuestos, 'codsubcuentasop'),
        ))));

        // obtenemos los asientos para poder filtrar
        // por fecha. asi nos aseguramos que se filtra
        // primero por fecha de devengo y si no existe
        // por fecha de factura
        $asientos = Asiento::all([
            new DataBaseWhere('codejercicio', $this->getModel()->codejercicio),
            new DataBaseWhere('fecha', $this->getModel()->fechainicio, '>='),
            new DataBaseWhere('fecha', $this->getModel()->fechafin, '<'),
        ], [], 0, 0);
        $idsAsientos = array_unique(array_column($asientos, Asiento::primaryColumn()));

        $partidas = Partida::all([
            new DataBaseWhere('idasiento', $idsAsientos, 'IN'),
            new DataBaseWhere('codsubcuenta', $subcuentas, 'IN')
        ], [], 0, 0);

        // agrupamos por subcuenta
        $partidasAgrupadas = [];
        foreach ($partidas as $partida) {
            $partidasAgrupadas[$partida->codsubcuenta][] = $partida;
        }

        // inicializamos el modelo303
        $this->modelo303 = [
            '01' => 0.00,
            '02' => 4.00,
            '03' => 0.00,

            '04' => 0.00,
            '05' => 10.00,
            '06' => 0.00,

            '07' => 0.00,
            '08' => 21.00,
            '09' => 0.00,

            // IVA 0
            '150' => 0.00,
            '151' => 0.00,
            '152' => 0.00,

            // RECARGO 1.75
            '156' => 0.00,
            '157' => 1.75,
            '158' => 0.00,

            // RECARGO 0.5
            '168' => 0.00,
            '169' => 0.5,
            '170' => 0.00,

            // RECARGO 1.4
            '19' => 0.00,
            '20' => 1.4,
            '21' => 0.00,

            // RECARGO 5.2
            '22' => 0.00,
            '23' => 5.2,
            '24' => 0.00,

            // Total cuota devengada
            '27' => 0.00,
        ];

        // obtenemos los codigos de subcuentas agrupados según tipo iva
        // esto lo hacemos por si existen varios impuesto
        // del mismo iva y distintas subcuentas
        $subcuentasSegunIVA = [];
        foreach ($impuestos as $impuesto) {
            $subcuentasSegunIVA[$impuesto->iva]['repercutido'][] = $impuesto->codsubcuentarep;
            $subcuentasSegunIVA[$impuesto->iva]['soportado'][] = $impuesto->codsubcuentasop;
        }

        // obtenemos los codigos de subcuentas agrupados según tipo recargo
        // esto lo hacemos por si existen varios impuesto
        // del mismo recargo y distintas subcuentas
        $subcuentasSegunRecargo = [];
        foreach ($impuestos as $impuesto) {
            $subcuentasSegunRecargo[$impuesto->recargo]['repercutido'][] = $impuesto->codsubcuentarepre;
            $subcuentasSegunRecargo[$impuesto->recargo]['soportado'][] = $impuesto->codsubcuentasopre;
        }

        foreach ($partidasAgrupadas as $subcuenta => $movimientos) {
            foreach ($movimientos as $mov) {
                // IVA 4%
                if (in_array($subcuenta, $subcuentasSegunIVA[4]['repercutido'])) {
                    $this->modelo303['01'] += $mov->baseimponible;
                    $this->modelo303['03'] += $mov->haber;
                }

                // IVA 10%
                if (in_array($subcuenta, $subcuentasSegunIVA[10]['repercutido'])) {
                    $this->modelo303['04'] += $mov->baseimponible;
                    $this->modelo303['06'] += $mov->haber;
                }

                // IVA 21%
                if (in_array($subcuenta, $subcuentasSegunIVA[21]['repercutido'])) {
                    $this->modelo303['07'] += $mov->baseimponible;
                    $this->modelo303['09'] += $mov->haber;
                }

                // IVA 0%
                if (in_array($subcuenta, $subcuentasSegunIVA[0]['repercutido'])) {
                    $this->modelo303['150'] += $mov->baseimponible;
                    $this->modelo303['152'] += $mov->haber;
                }

                // RECARGO 1.75%
                if (in_array($subcuenta, $subcuentasSegunRecargo[1.75]['repercutido'])) {
                    $this->modelo303['156'] += $mov->baseimponible;
                    $this->modelo303['158'] += $mov->haber;
                }

                // RECARGO 0.5%
                if (in_array($subcuenta, $subcuentasSegunRecargo[0.5]['repercutido'])) {
                    $this->modelo303['168'] += $mov->baseimponible;
                    $this->modelo303['170'] += $mov->haber;
                }

                // RECARGO 1.4%
                if (in_array($subcuenta, $subcuentasSegunRecargo[1.4]['repercutido'])) {
                    $this->modelo303['19'] += $mov->baseimponible;
                    $this->modelo303['21'] += $mov->haber;
                }

                // RECARGO 5.2%
                if (in_array($subcuenta, $subcuentasSegunRecargo[5.2]['repercutido'])) {
                    $this->modelo303['22'] += $mov->baseimponible;
                    $this->modelo303['24'] += $mov->haber;
                }
            }
        }

        // Total cuota devengada
        $this->modelo303['27'] = $this->modelo303['152'] + $this->modelo303['167'] + $this->modelo303['03'] + $this->modelo303['155'] + $this->modelo303['06'] + $this->modelo303['09'] + $this->modelo303['11'] + $this->modelo303['13'] + $this->modelo303['15'] + $this->modelo303['158'] + $this->modelo303['170'] + $this->modelo303['18'] + $this->modelo303['21'] + $this->modelo303['24'] + $this->modelo303['26'];
    }

    /**
     * Get DataBaseWhere filter for tax group
     *
     * @param int $group
     *
     * @return DataBaseWhere[]
     */
    protected function getPartidaImpuestoWhere(int $group): array
    {
        $saTools = new SubAccountTools();
        $where = [
            new DataBaseWhere('asientos.codejercicio', $this->getModel()->codejercicio),
            new DataBaseWhere('asientos.fecha', $this->getModel()->fechainicio, '>='),
            new DataBaseWhere('asientos.fecha', $this->getModel()->fechafin, '<='),
            new DataBaseWhere('series.siniva', false),
            new DataBaseWhere('partidas.baseimponible', 0, '!='),
            new DataBaseWhere('COALESCE(partidas.iva, 0)', 0, '>', 'OR'),
            $saTools->whereForSpecialAccounts('COALESCE(subcuentas.codcuentaesp, cuentas.codcuentaesp)', $group)
        ];

        // obtenemos todos los ids de los asientos de las regularizaciones
        $ids = [];
        foreach (RegularizacionImpuesto::all([], [], 0, 0) as $reg) {
            if ($reg->idasiento) {
                $ids[] = $reg->idasiento;
            }
        }
        if (!empty($ids)) {
            array_unshift($where, new DataBaseWhere('asientos.idasiento', implode(',', $ids), 'NOT IN'));
        }

        return $where;
    }

    /**
     * Load data view procedure
     *
     * @param string $viewName
     * @param BaseView $view
     */
    protected function loadData($viewName, $view)
    {
        switch ($viewName) {
            case 'EditRegularizacionImpuesto':
                parent::loadData($viewName, $view);
                if (false === $view->model->exists()) {
                    $view->disableColumn('tax-credit-account', false, 'true');
                    $view->disableColumn('tax-debit-account', false, 'true');
                }
                break;

            case 'ListPartida':
                $this->getListPartida($view);
                break;

            case 'ListPartidaImpuestoResumen':
                $this->getListPartidaImpuestoResumen($view);
                $this->setCreateAcEntryButton($viewName);
                break;

            case 'ListPartidaImpuesto-1':
                $this->getListPartidaImpuesto($view, SubAccountTools::SPECIAL_GROUP_TAX_INPUT);
                break;

            case 'ListPartidaImpuesto-2':
                $this->getListPartidaImpuesto($view, SubAccountTools::SPECIAL_GROUP_TAX_OUTPUT);
                break;
        }
    }

    protected function setCreateAcEntryButton(string $viewName): void
    {
        $idasiento = $this->getViewModelValue($this->getMainViewName(), 'idasiento');
        if (empty($idasiento)) {
            $this->addButton($viewName, [
                'action' => 'create-accounting-entry',
                'color' => 'success',
                'confirm' => true,
                'icon' => 'fas fa-balance-scale',
                'label' => 'create-accounting-entry',
                'row' => 'actions'
            ]);
        }
    }
}
