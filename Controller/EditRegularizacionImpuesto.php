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

namespace FacturaScripts\Plugins\Modelo303\Controller;

use Exception;
use FacturaScripts\Core\DataSrc\Impuestos;
use FacturaScripts\Core\DataSrc\Series;
use FacturaScripts\Core\Lib\ExtendedController\BaseView;
use FacturaScripts\Core\Lib\ExtendedController\EditController;
use FacturaScripts\Core\Lib\SubAccountTools;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Lib\Accounting\VatRegularizationToAccounting;
use FacturaScripts\Dinamic\Lib\Modelo303;
use FacturaScripts\Dinamic\Lib\Txt303Export;
use FacturaScripts\Dinamic\Model\Asiento;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Dinamic\Model\FacturaProveedor;
use FacturaScripts\Dinamic\Model\Join\PartidaImpuestoResumen;
use FacturaScripts\Dinamic\Model\RegularizacionImpuesto;

/**
 * @author Carlos García Gómez           <carlos@facturascripts.com>
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

    /**
     * Builds and configures a Modelo303 instance for the given tax settlement record,
     * computing the general regime squares plus the manually-entered ones and the result chain.
     *
     * @param RegularizacionImpuesto $reg
     * @return Modelo303
     */
    private function buildModelo303(RegularizacionImpuesto $reg): Modelo303
    {
        $modelo = new Modelo303();

        // mismo criterio que las pestañas Compras/Ventas y que el asiento de regularización
        $where = $this->commonTaxWhere(SubAccountTools::SPECIAL_GROUP_TAX_ALL, $reg);
        $resumen = PartidaImpuestoResumen::all($where, [
            'COALESCE(subcuentas.codcuentaesp, cuentas.codcuentaesp)' => 'ASC',
            'partidas.codsubcuenta' => 'ASC',
        ], 0, 0);

        // casillas del régimen general (devengado, deducible y resultado 46)
        $modelo->loadFromResumen($resumen);

        // bases exentas/informativas de ventas (casillas 59, 60 y 122)
        $modelo->loadFromSalesInvoices(
            (int)$reg->idempresa,
            (string)$reg->codejercicio,
            (string)$reg->fechainicio,
            (string)$reg->fechafin
        );

        // casillas de introducción manual (sin origen automático en la contabilidad)
        foreach (['65', '68', '70', '76', '77', '78', '108', '109', '110', '111', '112'] as $box) {
            $value = $reg->{'c' . $box} ?? null;
            if ($value !== null) {
                $modelo->setCasilla($box, (float)$value);
            }
        }

        // cadena de resultado (casillas 64, 66, 69, 71, 87)
        $modelo->computeResultado();

        return $modelo;
    }

    protected function checkInvoicesWithoutAccounting($model): void
    {
        // si el modelo no existe, no hacemos nada
        if (false === $model->exists()) {
            return;
        }

        // construimos la consulta para buscar facturas sin asiento
        $where = [
            Where::isNull('idasiento'),
            Where::eq('codejercicio', $model->codejercicio),
            Where::gte('fecha', $model->fechainicio),
            Where::lte('fecha', $model->fechafin),
            Where::notEq('total', 0),
        ];

        // buscamos si hay facturas de compra sin asiento para la fecha de la regularización
        foreach (FacturaProveedor::all($where) as $invoice) {
            Tools::log()->warning('supplier-invoice-without-accounting-entry', ['%code%' => $invoice->codigo]);
        }

        // buscamos si hay facturas de venta sin asiento para la fecha de la regularización
        foreach (FacturaCliente::all($where) as $invoice) {
            Tools::log()->warning('sale-invoice-without-accounting-entry', ['%code%' => $invoice->codigo]);
        }
    }

    /**
     * Builds the common filter used by the summary, purchases/sales and accounting-entry
     * queries, so the three of them always work over the SAME set of accounting entries.
     *
     * @param int $group grupo de cuentas especiales (TAX_ALL, TAX_INPUT, TAX_OUTPUT)
     * @return array
     */
    private function commonTaxWhere(int $group, ?RegularizacionImpuesto $model = null): array
    {
        $model = $model ?? $this->getModel();
        $excludedOperations = implode(',', [Asiento::OPERATION_OPENING, Asiento::OPERATION_CLOSING]);

        // ids de los asientos de TODAS las regularizaciones (para excluirlos)
        $regIds = [];
        foreach (RegularizacionImpuesto::all() as $reg) {
            if ($reg->idasiento) {
                $regIds[] = $reg->idasiento;
            }
        }

        $subAccountTools = new SubAccountTools();
        $where = [
            Where::eq('asientos.idempresa', $model->idempresa),
            Where::eq('asientos.codejercicio', $model->codejercicio),
            Where::gte('asientos.fecha', $model->fechainicio),
            Where::lte('asientos.fecha', $model->fechafin),
            Where::eq('COALESCE(series.siniva, false)', false),
            Where::notIn("COALESCE(asientos.operacion, '')", $excludedOperations),
            $subAccountTools->whereForSpecialAccounts('COALESCE(subcuentas.codcuentaesp, cuentas.codcuentaesp)', $group),
        ];

        if (false === empty($regIds)) {
            $where[] = Where::notIn('asientos.idasiento', implode(',', $regIds));
        }

        return $where;
    }

    /**
     * Create accounting entry action procedure.
     *
     * @return void
     */
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
        $this->createViewsTaxLine('ListPartidaImpuesto-1', 'purchases', 'fas fa-sign-in-alt');
        $this->createViewsTaxLine('ListPartidaImpuesto-2', 'sales', 'fas fa-sign-out-alt');
        $this->createViewsEntryLine();
        $this->createViewsPresentacion();
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
     * Add view for the AEAT .303 file presentation data (manual boxes + download button).
     *
     * @param string $viewName
     * @return void
     */
    protected function createViewsPresentacion(string $viewName = 'EditPresentacion303'): void
    {
        $this->addEditView($viewName, 'RegularizacionImpuesto', 'aeat-file-303', 'fa-solid fa-file-arrow-down')
            ->setSettings('btnNew', false)
            ->setSettings('btnDelete', false);
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
        $this->disableButtons($viewName, false);
    }

    /**
     * Add invoices view for tax lines.
     *
     * @param string $viewName
     * @param string $caption
     * @param string $icon
     * @return void
     */
    protected function createViewsTaxLine(string $viewName, string $caption, string $icon): void
    {
        $this->addListView($viewName, 'Join\PartidaImpuesto', $caption, $icon)
            ->addSearchFields(['partidas.concepto'])
            ->addFilterPeriod('date', 'date', 'fecha')
            ->addFilterSelect('iva', 'vat', 'partidas.iva', Impuestos::codeModel())
            ->addFilterSelect('codserie', 'serie', 'partidas.codserie', Series::codeModel());

        $this->disableButtons($viewName);
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
            ->setSettings('clickable', $clickable)
            ->setSettings('btnPrint', true);
    }

    /**
     * Generates and sends the AEAT .303 text file for download.
     *
     * @return bool
     */
    protected function downloadTxtAction(): bool
    {
        $reg = new RegularizacionImpuesto();
        $code = $this->request->input('code');
        if (false === $reg->load($code)) {
            Tools::log()->warning('record-not-found');
            return true;
        }

        $empresa = new Empresa();
        if (false === $empresa->load($reg->idempresa)) {
            Tools::log()->warning('company-not-found');
            return true;
        }

        $modelo = $this->buildModelo303($reg);
        $content = Txt303Export::export($reg, $modelo->getSquares(), $empresa);

        $fileName = $empresa->cifnif . '_' . date('Y', strtotime((string)$reg->fechainicio))
            . '_' . $reg->periodo . '.303';

        $this->setTemplate(false);
        $this->response
            ->header('Content-Type', 'text/plain; charset=ISO-8859-1')
            ->header('Content-Disposition', 'attachment; filename="' . $fileName . '"')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0')
            ->setContent($content);

        return false;
    }

    /**
     * Looks up the immediately previous tax settlement of the same company and copies its
     * pending-for-later-periods result (box 87) into box 110 (cuotas a compensar pendientes
     * de periodos anteriores) of the current settlement.
     *
     * @return void
     */
    private function fillPreviousCarryoverAction(): void
    {
        $reg = new RegularizacionImpuesto();
        $code = $this->request->input('code');
        if (false === $reg->load($code)) {
            Tools::log()->warning('record-not-found');
            return;
        }

        $where = [
            Where::eq('idempresa', $reg->idempresa),
            Where::lt('fechafin', $reg->fechainicio),
        ];
        $previous = RegularizacionImpuesto::all($where, ['fechafin' => 'DESC'], 0, 1);
        if (empty($previous)) {
            Tools::log()->warning('previous-tax-settlement-not-found');
            return;
        }

        $previousModelo = $this->buildModelo303($previous[0]);
        $reg->c110 = $previousModelo->casilla('87');
        if (false === $reg->save()) {
            Tools::log()->warning('record-save-error');
            return;
        }

        Tools::log()->notice('record-updated-correctly');
    }

    /**
     * Run the actions that alter data before reading it.
     *
     * @param string $action
     * @return bool
     */
    protected function execPreviousAction($action): bool
    {
        switch ($action) {
            case 'create-accounting-entry':
                $this->createAccountingEntryAction();
                return true;

            case 'download-303':
                // guardamos primero los cambios de la pestaña para que el fichero
                // descargado coincida siempre con lo que se ve en pantalla
                $this->editAction();
                return $this->downloadTxtAction();

            case 'fill-previous-carryover':
                $this->editAction();
                $this->fillPreviousCarryoverAction();
                return true;

            default:
                return parent::execPreviousAction($action);
        }
    }

    /**
     * Export action procedure.
     *
     * @return void
     */
    protected function exportAction(): void
    {
        if (
            false === $this->views[$this->active]->settings['btnPrint'] ||
            false === $this->permissions->allowExport
        ) {
            Tools::log()->warning('no-print-permission');
            return;
        }

        $this->setTemplate(false);
        $this->exportManager->setOrientation('landscape');
        $this->exportManager->newDoc(
            $this->request->queryOrInput('option', ''),
            $this->title,
            (int)$this->request->input('idformat', ''),
            $this->request->input('langcode', '')
        );

        foreach ($this->views as $name => $selectedView) {
            if (false === $selectedView->settings['active']) {
                continue;
            }

            $activeTab = $this->request->inputOrQuery('activetab', '');
            if (!empty($activeTab) && $activeTab !== $name) {
                continue;
            }

            $codes = $this->request->request->getArray('codes');
            if (false === $selectedView->export($this->exportManager, $codes)) {
                break;
            }
        }

        // add tax settlement summary table
        if (!empty($this->modelo303)) {
            $concept = Tools::trans('concept');
            $amount = Tools::trans('amount');
            $rows = [
                [$concept => Tools::trans('total-accrued-fee'), $amount => Tools::money($this->modelo303->casilla('27'))],
                [$concept => Tools::trans('total-to-deduct'), $amount => Tools::money($this->modelo303->casilla('45'))],
                [$concept => Tools::trans('total-result-of-the-general-regime'), $amount => Tools::money($this->modelo303->casilla('46'))],
            ];
            $this->exportManager->addTablePage([$concept, $amount], $rows);
        }

        $this->exportManager->show($this->response);
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
            $where = [Where::eq('idasiento', $this->getModel()->idasiento)];
            $view->loadData(false, $where, ['orden' => 'ASC']);
        }
    }

    /**
     * Load data into invoices view for tax lines.
     *
     * @param BaseView $view
     * @param int $group
     * @return void
     */
    private function getListPartidaImpuesto(BaseView $view, int $group): void
    {
        $id = $this->getModel()->idregiva;
        if (empty($id)) {
            return;
        }

        $where = $this->getPartidaImpuestoWhere($group);
        $orderBy = ['asientos.fecha' => 'ASC', 'partidas.codserie' => 'ASC', 'partidas.factura' => 'ASC'];
        $view->loadData(false, $where, $orderBy);
    }

    /**
     * Get array filter for tax group
     *
     * @param int $group
     * @return array[]
     */
    private function getPartidaImpuestoWhere(int $group): array
    {
        return $this->commonTaxWhere($group);
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

                // mismo criterio que las pestañas Compras/Ventas y que el asiento de regularización
                $where = $this->commonTaxWhere(SubAccountTools::SPECIAL_GROUP_TAX_ALL);
                $view->loadData(false, $where, [
                    'COALESCE(subcuentas.codcuentaesp, cuentas.codcuentaesp)' => 'ASC',
                    'partidas.codsubcuenta' => 'ASC',
                ]);

                // cargamos el resumen de IVA (casillas del régimen general)
                $this->modelo303->loadFromResumen($view->cursor);

                // cargamos las bases exentas/informativas de ventas (casillas 59, 60 y 122)
                $this->modelo303->loadFromSalesInvoices(
                    (int)$mainModel->idempresa,
                    (string)$mainModel->codejercicio,
                    (string)$mainModel->fechainicio,
                    (string)$mainModel->fechafin
                );

                // mostramos los avisos de importes que no encajan en ninguna casilla
                foreach ($this->modelo303->getAvisos() as $aviso) {
                    Tools::log()->warning($aviso);
                }

                $this->checkInvoicesWithoutAccounting($mainModel);
                break;

            case 'ListPartida':
                $this->getListPartida($view);

                // botón para crear el asiento contable cuando aún no existe
                if ($this->getModel()->exists() && empty($this->getModel()->idasiento)) {
                    $view->addButton([
                        'action' => 'create-accounting-entry',
                        'label' => 'create-accounting-entry',
                        'icon' => 'fa-solid fa-balance-scale',
                        'color' => 'success',
                        'confirm' => true,
                    ]);
                }
                break;

            case 'ListPartidaImpuesto-1':
                $this->getListPartidaImpuesto($view, SubAccountTools::SPECIAL_GROUP_TAX_INPUT);
                break;

            case 'ListPartidaImpuesto-2':
                $this->getListPartidaImpuesto($view, SubAccountTools::SPECIAL_GROUP_TAX_OUTPUT);
                break;

            case 'EditPresentacion303':
                // solo mostramos la pestaña cuando el registro ya existe
                $id = $this->getViewModelValue($this->getMainViewName(), 'idregiva');
                if (empty($id)) {
                    $view->setSettings('active', false);
                    break;
                }

                $view->loadData($id);

                // botón para rellenar la casilla 110 con el pendiente de la liquidación anterior
                $view->addButton([
                    'action' => 'fill-previous-carryover',
                    'color' => 'info',
                    'confirm' => true,
                    'icon' => 'fa-solid fa-wand-magic-sparkles',
                    'label' => 'fill-previous-carryover',
                    'type' => 'action',
                ]);

                // botón para descargar el fichero .303
                $view->addButton([
                    'action' => 'download-303',
                    'color' => 'success',
                    'icon' => 'fa-solid fa-file-arrow-down',
                    'label' => 'download-file-303',
                    'type' => 'action',
                ]);
                break;
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

        $this->tab($viewName)
            ->disableColumn('tax-credit-account', $exists, 'true')
            ->disableColumn('tax-debit-account', $exists, 'true');
    }
}
