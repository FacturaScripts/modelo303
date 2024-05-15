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
        $data['icon'] = 'fas fa-book';
        return $data;
    }

    /**
     * Load views
     */
    protected function createViews()
    {
        $this->createViewsModel303();
        $this->createViewsModel390();
    }

    protected function createViewsModel303(string $viewName = 'ListRegularizacionImpuesto')
    {
        $this->addView($viewName, 'RegularizacionImpuesto', 'model-303', 'fas fa-book')
            ->addOrderBy(['fechainicio'], 'start-date', 2)
            ->addOrderBy(['codejercicio||periodo'], 'period')
            ->addSearchFields(['codsubcuentaacr', 'codsubcuentadeu']);

        // añadimos filtros
        $this->addFilterSelectWhere($viewName, 'status', [
            ['label' => Tools::lang()->trans('model-303'), 'where' => [new DataBaseWhere('periodo', 'Y', '!=')]]
        ]);

        $this->addFilterSelect($viewName, 'idempresa', 'company', 'idempresa', Empresas::codeModel());

        $exercises = $this->codeModel->all('ejercicios', 'codejercicio', 'nombre');
        $this->addFilterSelect($viewName, 'codejercicio', 'exercise', 'codejercicio', $exercises);
    }

    protected function createViewsModel390(string $viewName = 'ListRegularizacionImpuesto-390')
    {
        $this->addView($viewName, 'RegularizacionImpuesto', 'model-390', 'fas fa-book')
            ->addOrderBy(['fechainicio'], 'start-date', 2)
            ->addOrderBy(['codejercicio||periodo'], 'period')
            ->addSearchFields(['codsubcuentaacr', 'codsubcuentadeu']);

        // añadimos filtros
        $this->addFilterSelectWhere($viewName, 'status', [
            ['label' => Tools::lang()->trans('model-390'), 'where' => [new DataBaseWhere('periodo', 'Y')]]
        ]);

        $this->addFilterSelect($viewName, 'idempresa', 'company', 'idempresa', Empresas::codeModel());

        $exercises = $this->codeModel->all('ejercicios', 'codejercicio', 'nombre');
        $this->addFilterSelect($viewName, 'codejercicio', 'exercise', 'codejercicio', $exercises);
    }
}
