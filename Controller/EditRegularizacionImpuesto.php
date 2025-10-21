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

use Exception;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Lib\ExtendedController\BaseView;
use FacturaScripts\Core\Lib\ExtendedController\EditController;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\RegularizacionImpuesto;
use FacturaScripts\Dinamic\Lib\Accounting\VatRegularizationToAccounting;
use FacturaScripts\Dinamic\Lib\Modelo303;

/**
 * Controller to list the items in the RegularizacionImpuesto model
 *
 * @author Carlos García Gómez <carlos@facturascripts.com>
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class EditRegularizacionImpuesto extends EditController
{
    /** @var ?Modelo303 */
    public ?Modelo303 $modelo303;

    /**
     * Returns the class name of the model to use in the editView.
     */
    public function getModelClassName(): string
    {
        return 'RegularizacionImpuesto';
    }

    /**
     * Return the basic data for this page.
     *
     * @return array
     */
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'reports';
        $data['title'] = 'model-303';
        $data['icon'] = 'fa-solid fa-balance-scale-right';
        return $data;
    }

    protected function createAccountingEntryAction(): void
    {
        $reg = new RegularizacionImpuesto();
        $code = $this->request->input('code');
        if (false === $reg->load($code)) {
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
    protected function createViews(): void
    {
        parent::createViews();
        $this->setTabsPosition('bottom');

        $this->createViewsTaxSummary();
        $this->createViewsTaxDetail();
        $this->createViewsEntryLine();
    }

    /**
     * Add view for account entry detail.
     *
     * @param string $viewName
     * @return void
     */
    protected function createViewsEntryLine(string $viewName = 'ListPartida'): void
    {
        $this->addListView($viewName, 'Partida', 'accounting-entry', 'fa-solid fa-balance-scale');
        $this->disableButtons($viewName, true);
    }

    /**
     * Add view for tax detail list.
     *
     * @param string $viewName
     * @return void
     */
    protected function createViewsTaxDetail(string $viewName = 'ListPartidaImpuestoResumen'): void
    {
        $this->addListView($viewName, 'Join\PartidaImpuestoResumen', 'tax-detail');
        $this->disableButtons($viewName, true);
    }

    /**
     * Add view for tax summary form.
     *
     * @param string $viewName
     * @return void
     */
    protected function createViewsTaxSummary(string $viewName = 'Modelo303'): void
    {
        $this->addHtmlView($viewName, $viewName, 'RegularizacionImpuesto', 'summary', 'fa-solid fa-list-alt');
        $this->disableButtons($viewName);
        $this->modelo303 = new Modelo303();
    }

    /**
     * Run the actions that alter data before reading it.
     *
     * @param string $action
     * @return bool
     */
    protected function execPreviousAction($action): bool
    {
        if ($action == 'create-accounting-entry') {
            $this->createAccountingEntryAction();
            return true;
        }

        return parent::execPreviousAction($action);
    }

    /**
     * Export action procedure.
     *
     * @return void
     */
    protected function exportAction(): void
    {
        $this->exportManager->setOrientation('landscape');
        parent::exportAction();
    }

    /**
     * Load data view procedure
     *
     * @param string $viewName
     * @param BaseView $view
     * @throws Exception
     */
    protected function loadData($viewName, $view): void
    {
        switch ($viewName) {
            case 'EditRegularizacionImpuesto':
                parent::loadData($viewName, $view);
                $this->settingsMainView();
                break;

            case 'ListPartidaImpuestoResumen':
                $mainModel = $this->getModel();
                $where = [
                    new DataBaseWhere('partidas.codsubcuenta', '477%', 'LIKE'),
                    new DataBaseWhere('partidas.codsubcuenta', '472%', 'LIKE', 'OR'),
                    new DataBaseWhere('asientos.idempresa', $mainModel->idempresa),
                    new DataBaseWhere('asientos.fecha', $mainModel->fechainicio, '>='),
                    new DataBaseWhere('asientos.fecha', $mainModel->fechafin, '<='),
                    new DataBaseWhere('COALESCE(series.siniva, false)', false),
                ];

                if (false === empty($mainModel->idasiento)) {
                    $where[] = new DataBaseWhere('partidas.idasiento', $mainModel->idasiento, '<>');
                }
                $view->loadData(false, $where, [
                    'COALESCE(subcuentas.codcuentaesp, cuentas.codcuentaesp)' => 'ASC',
                    'partidas.codsubcuenta' => 'ASC',
                ]);

                $this->modelo303->loadFromResumen($view->cursor);       // Load data into Modelo303 View
                break;

            case 'ListPartida':
                $this->getListPartida($view);
                break;
        }
    }

    /**
     * Setup actions for view.
     *
     * @param string $viewName
     * @param bool $clickable
     * @return void
     */
    private function disableButtons(string $viewName, bool $clickable = false): void
    {
        $this->setSettings($viewName, 'btnDelete', false)
            ->setSettings('btnNew', false)
            ->setSettings('checkBoxes', false)
            ->setSettings('clickable', $clickable);
    }

    /**
     * Load data for accounting entry.
     *
     * @param BaseView $view
     * @return void
     */
    private function getListPartida(BaseView $view): void
    {
        if (false === empty($this->getModel()->idasiento)) {
            $where = [new DataBaseWhere('idasiento', $this->getModel()->idasiento)];
            $view->loadData(false, $where, ['orden' => 'ASC']);
        }
    }

    /**
     * Settings for main view.
     *
     * @return void
     * @throws Exception
     */
    private function settingsMainView(): void
    {
        $viewName = $this->getMainViewName();
        $exists = $this->getModel()->exists();
        $this->views[$viewName]->disableColumn('tax-credit-account', $exists, 'true')
            ->disableColumn('tax-debit-account', $exists, 'true');

        if (empty($this->getModel()->idasiento)) {
            $this->addButton($viewName, [
                'action' => 'create-accounting-entry',
                'label' => 'create-accounting-entry',
                'icon' => 'fa-solid fa-balance-scale',
                'color' => 'success',
                'confirm' => true,
            ]);
        }
    }
}