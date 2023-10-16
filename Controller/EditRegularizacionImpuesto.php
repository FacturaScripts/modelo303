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

namespace FacturaScripts\Plugins\Modelo303\Controller;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Lib\ExtendedController\BaseView;
use FacturaScripts\Core\Lib\ExtendedController\EditController;
use FacturaScripts\Dinamic\Lib\Accounting\VatRegularizationToAccounting;
use FacturaScripts\Dinamic\Lib\SubAccountTools;
use FacturaScripts\Dinamic\Model\Join\PartidaImpuestoResumen;
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

    public function getModelClassName(): string
    {
        return 'RegularizacionImpuesto';
    }

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'reports';
        $data['title'] = 'model-303';
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
            $this->toolBox()->i18nLog()->warning('record-not-found');
            return;
        }

        if ($reg->idasiento) {
            $this->toolBox()->i18nLog()->warning('accounting-entry-already-created');
            return;
        }

        $accounting = new VatRegularizationToAccounting();
        if (false === $accounting->generate($reg)) {
            $this->toolBox()->i18nLog()->warning('accounting-entry-not-created');
            return;
        }

        // lock accounting and save
        $reg->bloquear = true;
        if (false === $reg->save()) {
            $this->toolBox()->i18nLog()->warning('record-save-error');
            return;
        }

        $this->toolBox()->i18nLog()->notice('record-updated-correctly');
    }

    /**
     * Add the view set.
     */
    protected function createViews()
    {
        parent::createViews();
        $this->setTabsPosition('bottom');

        $this->createViewsTaxSummary();
        $this->createViewsTaxLine('ListPartidaImpuesto-1', 'purchases', 'fas fa-sign-in-alt');
        $this->createViewsTaxLine('ListPartidaImpuesto-2', 'sales', 'fas fa-sign-out-alt');
        $this->createViewsEntryLine();
    }

    protected function createViewsEntryLine(string $viewName = 'ListPartida')
    {
        $this->addListView($viewName, 'Partida', 'accounting-entry', 'fas fa-balance-scale');
        $this->disableButtons($viewName, true);
    }

    protected function createViewsTaxLine(string $viewName, string $caption, string $icon)
    {
        $this->addListView($viewName, 'Join\PartidaImpuesto', $caption, $icon);
        $this->disableButtons($viewName);
    }

    protected function createViewsTaxSummary(string $viewName = 'ListPartidaImpuestoResumen')
    {
        $this->addListView($viewName, 'Join\PartidaImpuestoResumen', 'summary', 'fas fa-list-alt');
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
        $id = $this->getViewModelValue($this->getMainViewName(), 'idregiva');
        if (!empty($id)) {
            $where = $this->getPartidaImpuestoWhere(SubAccountTools::SPECIAL_GROUP_TAX_ALL);
            $orderBy = [
                'cuentasesp.descripcion' => 'ASC',
                'partidas.iva' => 'ASC',
                'partidas.recargo' => 'ASC'
            ];
            $view->loadData(false, $where, $orderBy);
            $this->calculateAmounts($view->cursor);
        }
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
        // obtenemos todos los ids de los asientos de las regularizaciones
        $ids = [];
        $reg = new RegularizacionImpuesto();
        foreach ($reg->all([], [], 0, 0) as $reg) {
            if ($reg->idasiento) {
                $ids[] = $reg->idasiento;
            }
        }

        $subAccountTools = new SubAccountTools();
        return [
            new DataBaseWhere('asientos.idasiento', implode(',', $ids), 'NOT IN'),
            new DataBaseWhere('asientos.codejercicio', $this->getModel()->codejercicio),
            new DataBaseWhere('asientos.fecha', $this->getModel()->fechainicio, '>='),
            new DataBaseWhere('asientos.fecha', $this->getModel()->fechafin, '<='),
            new DataBaseWhere('COALESCE(series.siniva, 0)', 0),
            new DataBaseWhere('partidas.baseimponible', 0, '!='),
            new DataBaseWhere('COALESCE(partidas.iva, 0)', 0, '>', 'OR'),
            $subAccountTools->whereForSpecialAccounts('COALESCE(subcuentas.codcuentaesp, cuentas.codcuentaesp)', $group)
        ];
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
