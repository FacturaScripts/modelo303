<?php
/**
 * This file is part of Modelo303 plugin for FacturaScripts
 * Copyright (C) 2017-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
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
use FacturaScripts\Core\DataSrc\Empresas;
use FacturaScripts\Core\Lib\ExtendedController\ListController;
use FacturaScripts\Core\Tools;

/**
 * Controller to list the items in the Impuesto model
 *
 * @author Carlos García Gómez      <carlos@facturascripts.com>
 */
class ListRegularizacionImpuesto extends ListController
{
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'reports';
        $data['title'] = 'model-303-390';
        $data['icon'] = 'fa-solid fa-book';
        return $data;
    }

    /**
     * Load views
     */
    protected function createViews(): void
    {
        $this->createViewsModel303();
        $this->createViewsModel390();
    }

    /**
     * Create the list view for Model 303.
     *
     * @param string $viewName
     * @return void
     */
    protected function createViewsModel303(string $viewName = 'ListRegularizacionImpuesto'): void
    {
        $exercises = $this->codeModel->all('ejercicios', 'codejercicio', 'nombre');
        $this->addView($viewName, 'RegularizacionImpuesto', 'model-303', 'fa-solid fa-book')
            // Search and Orderby
            ->addSearchFields(['codsubcuentaacr', 'codsubcuentadeu'])
            ->addOrderBy(['fechainicio'], 'start-date', 2)
            ->addOrderBy(['codejercicio||periodo'], 'period')
            // Filters
            ->addFilterSelect('idempresa', 'company', 'idempresa', Empresas::codeModel())
            ->addFilterSelect('codejercicio', 'exercise', 'codejercicio', $exercises)
            ->addFilterSelectWhere('status', [
                ['label' => Tools::lang()->trans('model-303'), 'where' => [new DataBaseWhere('periodo', 'Y', '!=')]]
            ]);
    }

    /**
     * Create the list view for Model 390.
     *
     * @param string $viewName
     * @return void
     */
    protected function createViewsModel390(string $viewName = 'ListRegularizacionImpuesto-390'): void
    {
        $exercises = $this->codeModel->all('ejercicios', 'codejercicio', 'nombre');
        $this->addView($viewName, 'RegularizacionImpuesto', 'model-390', 'fa-solid fa-book')
            // Search and Orderby
            ->addOrderBy(['fechainicio'], 'start-date', 2)
            ->addOrderBy(['codejercicio||periodo'], 'period')
            ->addSearchFields(['codsubcuentaacr', 'codsubcuentadeu'])
            // Filters
            ->addFilterSelect('idempresa', 'company', 'idempresa', Empresas::codeModel())
            ->addFilterSelect('codejercicio', 'exercise', 'codejercicio', $exercises)
            ->addFilterSelectWhere('status', [
                ['label' => Tools::lang()->trans('model-390'), 'where' => [new DataBaseWhere('periodo', 'Y')]]
            ]);
    }
}
