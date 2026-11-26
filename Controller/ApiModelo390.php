<?php
/**
 * This file is part of Modelo303 plugin for FacturaScripts
 * Copyright (C) 2026 Carlos Garcia Gomez <carlos@facturascripts.com>
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

use FacturaScripts\Core\Response;
use FacturaScripts\Core\Template\ApiController;
use FacturaScripts\Dinamic\Lib\Modelo390Api;

/**
 * Endpoint de la API de solo lectura con los datos del modelo 390 (declaración-resumen anual de IVA) de una
 * empresa y un ejercicio: GET /api/3/modelo390. Los trimestres y meses tienen su propio endpoint,
 * GET /api/3/modelo303.
 *
 * Calcula el modelo igual que la pantalla de la regularización anual de IVA. Nunca crea, modifica ni
 * borra registros: si la regularización anual no existe, calcula sobre una en memoria.
 *
 * Parámetros (query string):
 *   - periodo: opcional; solo ANUAL (también ANNUAL, Y, A y 0A), que es el valor por defecto.
 *   - codejercicio: ejercicio contable. Si se indica, manda sobre idempresa y anio.
 *   - idempresa: empresa. Por defecto, la empresa por defecto.
 *   - anio: año natural del ejercicio de la empresa. Por defecto, el actual.
 *   - detalle: 1/true para incluir el resumen de partidas de IVA y las facturas sin asiento.
 *
 * Errores ({"status": "error", "message": ...}): 405 method-not-allowed, 400 invalid-period o invalid-year,
 * 404 exercise-not-found o company-not-found.
 *
 * Una clave API sin acceso completo necesita el permiso de lectura del recurso «modelo390».
 *
 * @author Daniel Fernández Giménez <contacto@danielfg.es>
 */
class ApiModelo390 extends ApiController
{
    /** Parámetros de la query string que acepta el endpoint. */
    private const PARAMS = ['codejercicio', 'idempresa', 'anio', 'periodo', 'detalle'];

    /**
     * Valida el método, delega el cálculo en Modelo390Api y devuelve el resultado en JSON.
     */
    protected function runResource(): void
    {
        if ($this->request->method() !== 'GET') {
            $this->response
                ->setHttpCode(Response::HTTP_METHOD_NOT_ALLOWED)
                ->json(['status' => 'error', 'message' => 'method-not-allowed']);
            return;
        }

        $params = [];
        foreach (self::PARAMS as $key) {
            $params[$key] = $this->request->query($key);
        }

        $result = Modelo390Api::build($params);
        $this->response->setHttpCode($result['code'])->json($result['body']);
    }
}
