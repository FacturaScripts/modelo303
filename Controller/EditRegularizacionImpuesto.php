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
use FacturaScripts\Dinamic\Lib\Modelo390;
use FacturaScripts\Dinamic\Lib\Modelo303File;
use FacturaScripts\Dinamic\Lib\Modelo390File;
use FacturaScripts\Dinamic\Model\Asiento;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Dinamic\Model\RegularizacionImpuesto;

/**
 * Ficha de una regularización de IVA. En las trimestrales y mensuales muestra el modelo 303 y su
 * fichero .303; en la anual (periodo Y) muestra el modelo 390 (resumen anual) y su fichero DR390,
 * y no permite crear ni vincular el asiento de regularización, que es de los periodos.
 *
 * @author Carlos García Gómez           <carlos@facturascripts.com>
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class EditRegularizacionImpuesto extends EditController
{
    /**
     * Modelo 303 de la regularización que alimenta la vista del resumen y la exportación. Se crea vacío
     * en createViewsTaxSummary() y se recalcula en loadData() con Modelo303::fromRegularizacion().
     *
     * @var ?Modelo303
     */
    public ?Modelo303 $modelo303 = null;

    /** @var ?Modelo390 modelo 390 de la regularización anual (null en el resto de periodos) */
    public ?Modelo390 $modelo390 = null;

    /**
     * Indica si la regularización cargada es la anual (modelo 390), para que el título de la
     * página, de la pestaña principal y de la exportación digan «Modelo 390» y no «Modelo 303».
     * Se calcula al cargar la vista principal.
     *
     * @var bool
     */
    protected bool $annualTitle = false;

    /**
     * Returns the class name of the model to use in the editView.
     */
    public function getModelClassName(): string
    {
        return 'RegularizacionImpuesto';
    }

    /**
     * Devuelve los datos básicos de la página. El título es «Modelo 303», salvo cuando ya se ha
     * cargado una regularización anual, que se muestra como «Modelo 390». La página se registra
     * en el menú siempre con el título del 303, porque al desplegar los plugins no hay registro cargado.
     *
     * @return array
     */
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'reports';
        $data['submenu'] = 'taxation-spain';
        $data['title'] = $this->annualTitle ? 'model-390' : 'model-303';
        $data['icon'] = 'fa-solid fa-balance-scale-right';
        return $data;
    }

    /**
     * El widget autocomplete del modal "Vincular asiento" necesita filtrar los asientos por
     * empresa, fecha y operación de la regularización, datos que el formulario del modal no
     * envía (por eso no se puede usar el atributo fieldfilter del widget). Los tomamos de la
     * regularización que se está editando, cargada a partir del 'code' de la url.
     *
     * @return array
     */
    protected function autocompleteAction(): array
    {
        $data = $this->requestGet(['source', 'field', 'term']);
        if ($data['source'] !== 'asientos' || $data['field'] !== 'linkidasiento') {
            return parent::autocompleteAction();
        }

        $reg = new RegularizacionImpuesto();
        if (false === $reg->load($this->request->inputOrQuery('code'))) {
            return [];
        }

        $results = [];
        $where = $this->availableAccEntriesWhere($reg);
        foreach ($this->codeModel->search('asientos', 'idasiento', 'concepto', $data['term'], $where) as $value) {
            $results[] = ['key' => Tools::fixHtml($value->code), 'value' => Tools::fixHtml($value->description)];
        }

        return empty($results) ?
            [['key' => null, 'value' => Tools::trans('no-data')]] :
            $results;
    }

    /**
     * Filtro de los asientos que se pueden vincular a una regularización: los de la misma
     * empresa, con fecha igual o posterior al fin del periodo liquidado, sin operación
     * especial (apertura, cierre o regularización) y que no estén ya vinculados a otra
     * regularización. El ejercicio es indiferente.
     *
     * @param RegularizacionImpuesto $reg
     * @return array
     */
    protected function availableAccEntriesWhere(RegularizacionImpuesto $reg): array
    {
        $where = [
            Where::eq('idempresa', $reg->idempresa),
            Where::gte('fecha', $reg->fechafin),
            // COALESCE porque operacion es null en los asientos sin operación especial,
            // y las comparaciones sobre null en sql nunca se cumplen
            Where::eq("COALESCE(operacion, '')", ''),
        ];

        // excluimos los asientos ya vinculados a otra regularización
        $linked = [];
        foreach (RegularizacionImpuesto::all() as $item) {
            if ($item->idasiento && $item->idregiva != $reg->idregiva) {
                $linked[] = $item->idasiento;
            }
        }
        if (false === empty($linked)) {
            $where[] = Where::notIn('idasiento', implode(',', $linked));
        }

        return $where;
    }

    /**
     * Avisa en el log de las facturas de compra y de venta del periodo de la regularización
     * que no tienen asiento contable, y que por tanto no se incluyen en el modelo.
     *
     * @param RegularizacionImpuesto $model
     * @return void
     */
    protected function checkInvoicesWithoutAccounting($model): void
    {
        // si el modelo no existe, no hacemos nada
        if (false === $model->exists()) {
            return;
        }

        $invoices = Modelo303::invoicesWithoutAccounting($model);

        // facturas de compra sin asiento para la fecha de la regularización
        foreach ($invoices['compra'] as $invoice) {
            Tools::log()->warning('supplier-invoice-without-accounting-entry', ['%code%' => $invoice->codigo]);
        }

        // facturas de venta sin asiento para la fecha de la regularización
        foreach ($invoices['venta'] as $invoice) {
            Tools::log()->warning('sale-invoice-without-accounting-entry', ['%code%' => $invoice->codigo]);
        }
    }

    /**
     * Create accounting entry action procedure.
     *
     * @return void
     */
    protected function createAccountingEntryAction(): void
    {
        $reg = new RegularizacionImpuesto();
        // la pestaña Asiento es una vista de lista y su formulario no envía 'code'
        // en el cuerpo del POST, así que lo recuperamos también de la query string
        $code = $this->request->inputOrQuery('code');
        if (false === $reg->load($code)) {
            Tools::log()->warning('record-not-found');
            return;
        }

        if ($reg->idasiento) {
            Tools::log()->warning('accounting-entry-already-created');
            return;
        }

        // la regularización anual solo resume el año: el asiento duplicaría el de los periodos
        if ($reg->periodo === 'Y') {
            Tools::log()->warning('model390-no-accounting-entry');
            return;
        }

        $accounting = new VatRegularizationToAccounting();
        if (false === $accounting->generate($reg)) {
            Tools::log()->warning('accounting-entry-not-created');
            return;
        }

        // bloqueamos la contabilidad y guardamos; el asiento lo ha creado el plugin, así que
        // se elimina con la regularización
        $reg->bloquear = true;
        $reg->asientomanual = false;
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
        $this->createViewsModelo390();
        $this->createViewsTaxDetail();
        $this->createViewsTaxLine('ListPartidaImpuesto-1', 'deductible-vat', 'fas fa-sign-in-alt');
        $this->createViewsTaxLine('ListPartidaImpuesto-2', 'vat-accrued', 'fas fa-sign-out-alt');
        $this->createViewsEntryLine();
        $this->createViewsPresentacion();
        $this->createViewsPresentacion390();
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
     * Añade la pestaña del modelo 390 (resumen anual), que solo se muestra en la regularización anual.
     *
     * @param string $viewName
     * @return void
     */
    protected function createViewsModelo390(string $viewName = 'Modelo390'): void
    {
        $this->addHtmlView($viewName, $viewName, 'RegularizacionImpuesto', 'model-390', 'fa-solid fa-list-alt');
        $this->disableButtons($viewName);
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
     * Añade la pestaña con los datos de presentación del modelo 390 (identificación y casillas
     * manuales) y el botón de descarga del fichero DR390. Solo se muestra en la regularización anual.
     *
     * @param string $viewName
     * @return void
     */
    protected function createViewsPresentacion390(string $viewName = 'EditPresentacion390'): void
    {
        $this->addEditView($viewName, 'RegularizacionImpuesto', 'aeat-file-390', 'fa-solid fa-file-arrow-down')
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
     * Genera y envía para descargar el fichero DR390 del modelo 390 de la regularización anual.
     *
     * @return bool
     */
    protected function downloadTxt390Action(): bool
    {
        $reg = new RegularizacionImpuesto();
        if (false === $reg->load($this->request->input('code'))) {
            Tools::log()->warning('record-not-found');
            return true;
        }

        if ($reg->periodo !== 'Y') {
            Tools::log()->warning('file-303-not-for-model-390');
            return true;
        }

        $empresa = new Empresa();
        if (false === $empresa->load($reg->idempresa)) {
            Tools::log()->warning('company-not-found');
            return true;
        }

        $year = (int)date('Y', strtotime((string)$reg->fechainicio));
        if ($year < Modelo390File::FIRST_YEAR) {
            Tools::log()->warning('model390-file-not-available', ['%year%' => $year]);
            return true;
        }

        $modelo = Modelo390::fromRegularizacion($reg);
        $content = Modelo390File::generate($reg, $modelo->getSquares(), $empresa);
        $fileName = $empresa->cifnif . '_' . $year . '_0A.390';

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

        // el resumen anual (modelo 390) no se presenta con el fichero del 303, que solo admite
        // trimestres y meses: generarlo daría un .303 con un periodo que no corresponde
        if ($reg->periodo === 'Y') {
            Tools::log()->warning('file-303-not-for-model-390');
            return true;
        }

        $empresa = new Empresa();
        if (false === $empresa->load($reg->idempresa)) {
            Tools::log()->warning('company-not-found');
            return true;
        }

        $modelo = Modelo303::fromRegularizacion($reg);
        $content = Modelo303File::generate($reg, $modelo->getSquares(), $empresa);

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

            case 'download-390':
                // guardamos primero los cambios de la pestaña para que el fichero
                // descargado coincida siempre con lo que se ve en pantalla
                $this->editAction();
                return $this->downloadTxt390Action();

            case 'fill-previous-carryover':
                $this->editAction();
                $this->fillPreviousCarryoverAction();
                return true;

            case 'link-accounting-entry':
                $this->linkAccountingEntryAction();
                return true;

            case 'unlink-accounting-entry':
                $this->unlinkAccountingEntryAction();
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

        // la pestaña del modelo 390 y la del resumen del 303 son vistas html que no se exportan:
        // añadimos sus casillas como tablas
        if (null !== $this->modelo390) {
            $this->exportModelo390();
        } elseif (null !== $this->modelo303) {
            $concept = Tools::trans('concept');
            $amount = Tools::trans('amount');
            $rows = [
                [$concept => Tools::trans('total-accrued-fee'), $amount => Tools::money($this->modelo303->casilla('27'))],
                [$concept => Tools::trans('total-to-deduct'), $amount => Tools::money($this->modelo303->casilla('45'))],
                [$concept => Tools::trans('total-result-of-the-general-regime'), $amount => Tools::money($this->modelo303->casilla('46'))],
            ];
            $this->exportManager->addTablePage([$concept, $amount], $rows, [], Tools::trans('summary'));
        }

        $this->exportManager->show($this->response);
    }

    /**
     * Añade a la exportación las casillas del modelo 390, una tabla por cada apartado de la pestaña
     * Modelo 390 (con las mismas filas visibles que la pantalla), y la lista de regularizaciones
     * periódicas usadas en el resultado de las liquidaciones del año.
     *
     * @return void
     */
    protected function exportModelo390(): void
    {
        $concept = Tools::trans('concept');
        $boxBase = Tools::trans('model390-box-base');
        $base = Tools::trans('tax-base');
        $boxQuota = Tools::trans('model390-box-quota');
        $quota = Tools::trans('quota');
        $box = Tools::trans('model390-box');
        $amount = Tools::trans('amount');

        foreach ($this->modelo390->getSections() as $title => $sectionRows) {
            // los apartados de IVA devengado y deducible tienen base y cuota; el resto, un solo importe
            $withBase = in_array($title, ['model390-section-accrued', 'model390-section-deductible'], true);

            $rows = [];
            foreach ($sectionRows as $row) {
                $label = Tools::trans($row['label']);
                if ($row['rate'] !== null) {
                    $label .= ' ' . str_replace('.', ',', $row['rate']) . ' %';
                }

                if ($withBase) {
                    $rows[] = [
                        $concept => $label,
                        $boxBase => (string)$row['base'],
                        $base => $row['base'] === null ? '' : Tools::money($this->modelo390->casilla($row['base'])),
                        $boxQuota => (string)$row['cuota'],
                        $quota => $row['cuota'] === null ? '' : Tools::money($this->modelo390->casilla($row['cuota'])),
                    ];
                    continue;
                }

                $rows[] = [
                    $concept => $label,
                    $box => (string)$row['cuota'],
                    $amount => $row['cuota'] === null ? '' : Tools::money($this->modelo390->casilla($row['cuota'])),
                ];
            }

            // el prefijo «390 - » distingue estas hojas de las de las pestañas IVA devengado e IVA deducible
            $headers = $withBase ? [$concept, $boxBase, $base, $boxQuota, $quota] : [$concept, $box, $amount];
            $this->exportManager->addTablePage($headers, $rows, [], '390 - ' . Tools::trans($title));
        }

        // regularizaciones periódicas del año usadas en el resultado de las liquidaciones
        $period = Tools::trans('period');
        $type = Tools::trans('model390-declaration-type');
        $result = Tools::trans('result') . ' (71)';
        $pending = Tools::trans('model390-pending-compensation') . ' (87)';
        $rows = [];
        foreach ($this->modelo390->getPeriodos() as $periodo) {
            $rows[] = [
                $period => $periodo['periodo'],
                $type => $periodo['tipo'],
                $result => Tools::money($periodo['resultado']),
                $pending => Tools::money($periodo['pendiente']),
            ];
        }
        $this->exportManager->addTablePage([$period, $type, $result, $pending], $rows, [], '390 - ' . Tools::trans('model390-periodic-settlements'));
    }

    /**
     * Rellena la casilla 110 (cuotas a compensar pendientes de periodos anteriores) con lo que dejó
     * pendiente la regularización trimestral o mensual inmediatamente anterior de la misma empresa:
     * su casilla 87 más su resultado negativo si se pidió compensar (Modelo303::pendingCarryover()).
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

        $previous = Modelo303::previousRegularization($reg);
        if (null === $previous) {
            Tools::log()->warning('previous-tax-settlement-not-found');
            return;
        }

        $reg->c110 = Modelo303::pendingCarryover($previous);
        if (false === $reg->save()) {
            Tools::log()->warning('record-save-error');
            return;
        }

        Tools::log()->notice('record-updated-correctly');
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
        return Modelo303::taxWhere($this->getModel(), $group);
    }

    /**
     * Indica si la regularización que se está editando es la anual (modelo 390).
     *
     * @return bool
     */
    protected function isAnnual(): bool
    {
        return $this->getModel()->periodo === 'Y';
    }

    /**
     * Vincula un asiento contable ya existente (normalmente creado a mano, sin usar el
     * asistente) a una regularización, en lugar de generar uno nuevo. Así la lógica que
     * excluye los asientos de regularización al calcular los periodos siguientes (ver
     * Modelo303::taxWhere() y getSubtotals(), ambos basados en RegularizacionImpuesto::idasiento)
     * también reconoce el asiento manual. El asiento no se modifica.
     *
     * Valida las mismas condiciones que filtra el modal, para que no se pueda colar un
     * asiento inválido manipulando la petición.
     *
     * @param RegularizacionImpuesto $reg
     * @param int $idasiento
     * @return bool
     */
    protected function linkAccountingEntry(RegularizacionImpuesto &$reg, int $idasiento): bool
    {
        if ($reg->idasiento) {
            Tools::log()->warning('accounting-entry-already-created');
            return false;
        }

        // la regularización anual (modelo 390) no tiene asiento propio
        if ($reg->periodo === 'Y') {
            Tools::log()->warning('model390-no-accounting-entry');
            return false;
        }

        $asiento = new Asiento();
        if (false === $asiento->load($idasiento) || $asiento->idempresa != $reg->idempresa) {
            Tools::log()->warning('accounting-entry-invalid');
            return false;
        }

        // el asiento no puede tener una operación especial (apertura, cierre o regularización)
        if (false === empty($asiento->operacion)) {
            Tools::log()->warning('accounting-entry-with-operation');
            return false;
        }

        // la fecha del asiento debe ser igual o posterior al fin del periodo liquidado.
        // ojo: las fechas de los modelos vienen en formato d-m-Y, así que hay que
        // normalizarlas antes de compararlas
        if (strtotime($asiento->fecha) < strtotime($reg->fechafin)) {
            Tools::log()->warning('accounting-entry-before-end-date');
            return false;
        }

        // el asiento no puede estar ya vinculado a otra regularización
        $where = [
            Where::eq('idasiento', $asiento->idasiento),
            Where::notEq('idregiva', $reg->idregiva),
        ];
        if (false === empty(RegularizacionImpuesto::all($where, [], 0, 1))) {
            Tools::log()->warning('accounting-entry-already-linked');
            return false;
        }

        $reg->idasiento = $asiento->idasiento;
        $reg->fechaasiento = $asiento->fecha;
        $reg->bloquear = true;
        // el asiento es del usuario: no se elimina al eliminar la regularización
        $reg->asientomanual = true;
        return $reg->save();
    }

    /**
     * Link accounting entry action procedure.
     *
     * @return void
     */
    protected function linkAccountingEntryAction(): void
    {
        $reg = new RegularizacionImpuesto();
        // la pestaña Asiento es una vista de lista y el formulario del modal no envía 'code'
        // en el cuerpo del POST, así que lo recuperamos también de la query string
        $code = $this->request->inputOrQuery('code');
        if (false === $reg->load($code)) {
            Tools::log()->warning('record-not-found');
            return;
        }

        $idasiento = (int)$this->request->input('linkidasiento');
        if (empty($idasiento)) {
            Tools::log()->warning('accounting-entry-not-selected');
            return;
        }

        // si no se puede vincular, linkAccountingEntry() ya ha registrado el motivo
        if ($this->linkAccountingEntry($reg, $idasiento)) {
            Tools::log()->notice('record-updated-correctly');
        }
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

                // la regularización anual es el modelo 390: lo indicamos en el título de la página,
                // de la pestaña principal y de los documentos exportados
                if ($this->isAnnual()) {
                    $this->annualTitle = true;
                    $view->title = Tools::trans('model-390');
                    $this->title = Tools::trans('model-390');
                    if ($view->model->exists()) {
                        $this->title .= ' ' . $view->model->primaryDescription();
                    }
                }
                break;

            case 'ListPartidaImpuestoResumen':
                $mainModel = $this->getModel();

                // mismo criterio que las pestañas Compras/Ventas y que el asiento de regularización
                $where = Modelo303::taxWhere($mainModel, SubAccountTools::SPECIAL_GROUP_TAX_ALL);
                $view->loadData(false, $where, [
                    'COALESCE(subcuentas.codcuentaesp, cuentas.codcuentaesp)' => 'ASC',
                    'partidas.codsubcuenta' => 'ASC',
                ]);

                // calculamos el resumen con TODAS las filas, no solo con la página visible de la
                // pestaña, para que coincida con el fichero .303 y con la API
                $this->modelo303 = Modelo303::fromRegularizacion($mainModel);

                // mostramos los avisos de importes que no encajan en ninguna casilla (en la anual
                // ya los muestra el modelo 390, con sus propias casillas)
                if (false === $this->isAnnual()) {
                    foreach ($this->modelo303->getAvisos() as $aviso) {
                        Tools::log()->warning($aviso);
                    }
                }

                $this->checkInvoicesWithoutAccounting($mainModel);
                break;

            case 'Modelo303':
                // en la regularización anual se muestra el modelo 390 en su lugar
                if ($this->isAnnual()) {
                    $view->setSettings('active', false);
                }
                break;

            case 'Modelo390':
                $mainModel = $this->getModel();
                if (false === $this->isAnnual() || false === $mainModel->exists()) {
                    $view->setSettings('active', false);
                    break;
                }

                $this->modelo390 = Modelo390::fromRegularizacion($mainModel);
                foreach ($this->modelo390->getAvisos() as $aviso) {
                    Tools::log()->warning($aviso);
                }

                $empresa = new Empresa();
                if ($empresa->load($mainModel->idempresa) && $empresa->personafisica) {
                    Tools::log()->info('model390-natural-person-name');
                }
                break;

            case 'ListPartida':
                $this->getListPartida($view);

                if (false === $this->getModel()->exists()) {
                    break;
                }

                // la regularización anual no tiene asiento propio: no se puede crear ni vincular
                if (empty($this->getModel()->idasiento) && $this->isAnnual()) {
                    break;
                }

                if (empty($this->getModel()->idasiento)) {
                    // botón para crear el asiento contable cuando aún no existe
                    $view->addButton([
                        'action' => 'create-accounting-entry',
                        'label' => 'create-accounting-entry',
                        'icon' => 'fa-solid fa-balance-scale',
                        'color' => 'success',
                        'confirm' => true,
                    ]);

                    // botón para vincular un asiento existente, abre el modal definido
                    // en Extension/XMLView/ListPartida.xml
                    $view->addButton([
                        'action' => 'link-accounting-entry',
                        'label' => 'link-accounting-entry',
                        'icon' => 'fa-solid fa-link',
                        'color' => 'info',
                        'type' => 'modal',
                    ]);
                    break;
                }

                // botón para desvincular el asiento, sin eliminarlo
                $view->addButton([
                    'action' => 'unlink-accounting-entry',
                    'label' => 'unlink-accounting-entry',
                    'icon' => 'fa-solid fa-link-slash',
                    'color' => 'warning',
                    'type' => 'action',
                    'confirm' => true,
                ]);
                break;

            case 'ListPartidaImpuesto-1':
                $this->getListPartidaImpuesto($view, SubAccountTools::SPECIAL_GROUP_TAX_INPUT);
                break;

            case 'ListPartidaImpuesto-2':
                $this->getListPartidaImpuesto($view, SubAccountTools::SPECIAL_GROUP_TAX_OUTPUT);
                break;

            case 'EditPresentacion303':
                // solo mostramos la pestaña cuando el registro ya existe y no es la anual (modelo 390)
                $id = $this->getViewModelValue($this->getMainViewName(), 'idregiva');
                if (empty($id) || $this->isAnnual()) {
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

            case 'EditPresentacion390':
                // solo en la regularización anual ya guardada
                $id = $this->getViewModelValue($this->getMainViewName(), 'idregiva');
                if (empty($id) || false === $this->isAnnual()) {
                    $view->setSettings('active', false);
                    break;
                }

                $view->loadData($id);
                $view->addButton([
                    'action' => 'download-390',
                    'color' => 'success',
                    'icon' => 'fa-solid fa-file-arrow-down',
                    'label' => 'download-file-390',
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

    /**
     * Desvincula el asiento de la regularización, sin eliminarlo de la contabilidad y sin
     * modificarlo. Limpia la fecha del asiento y desbloquea la liquidación, para que el
     * asiento vuelva a contar en los cálculos del periodo (ver Modelo303::taxWhere()).
     *
     * @param RegularizacionImpuesto $reg
     * @return bool
     */
    protected function unlinkAccountingEntry(RegularizacionImpuesto &$reg): bool
    {
        if (empty($reg->idasiento)) {
            Tools::log()->warning('accounting-entry-not-linked');
            return false;
        }

        $reg->idasiento = null;
        $reg->fechaasiento = null;
        $reg->bloquear = false;
        $reg->asientomanual = false;
        return $reg->save();
    }

    /**
     * Unlink accounting entry action procedure.
     *
     * @return void
     */
    protected function unlinkAccountingEntryAction(): void
    {
        $reg = new RegularizacionImpuesto();
        // la pestaña Asiento es una vista de lista y su formulario no envía 'code'
        // en el cuerpo del POST, así que lo recuperamos también de la query string
        $code = $this->request->inputOrQuery('code');
        if (false === $reg->load($code)) {
            Tools::log()->warning('record-not-found');
            return;
        }

        // si no se puede desvincular, unlinkAccountingEntry() ya ha registrado el motivo
        if ($this->unlinkAccountingEntry($reg)) {
            Tools::log()->notice('record-updated-correctly');
        }
    }
}
