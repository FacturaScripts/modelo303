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

namespace FacturaScripts\Plugins\Modelo303\Lib;

use FacturaScripts\Dinamic\Lib\Modelo303;
use FacturaScripts\Dinamic\Lib\Modelo390;
use FacturaScripts\Dinamic\Model\RegularizacionImpuesto;

/**
 * Construye la respuesta del endpoint de solo lectura GET /api/3/modelo390 (declaración-resumen anual de IVA).
 * Los trimestres y meses tienen su propio endpoint, GET /api/3/modelo303 (Modelo303Api).
 *
 * Busca la regularización anual (periodo Y) guardada de la empresa y ejercicio pedidos y, si existe, calcula el
 * modelo sobre ella (con sus casillas de introducción manual y sus datos de presentación). Si no existe, calcula
 * sobre una regularización en memoria con las fechas del año, que nunca se guarda. El cálculo es el mismo que el
 * de la pantalla y el del fichero: Modelo390::fromRegularizacion(), que devuelve las casillas oficiales del
 * modelo 390 y los resultados de las regularizaciones periódicas del año. resultado es la casilla 86 (resultado
 * de la liquidación anual): informativa, lo ingresado o devuelto está en los modelos 303 del año.
 *
 * Parámetros: codejercicio, idempresa, anio, periodo (opcional; solo ANUAL, el valor por defecto) y detalle.
 *
 * No crea, modifica ni borra ningún registro.
 *
 * @author Daniel Fernández Giménez <contacto@danielfg.es>
 */
class Modelo390Api
{
    use Modelo303ApiTrait;

    /** Casillas del modelo 390 que se devuelven siempre, aunque valgan 0. */
    protected const ALWAYS_SQUARES = ['33', '34', '47', '64', '65', '84', '86', '95', '97', '98', '662', '108'];

    /** Modelo de la AEAT. */
    public const MODEL = '390';

    /** Periodos admitidos, tal y como se devuelven en la respuesta. */
    public const PERIODS = ['ANUAL'];

    /**
     * Construye la respuesta del endpoint a partir de los parámetros de la query string.
     *
     * Parámetros: codejercicio, idempresa, anio, periodo (opcional; solo ANUAL) y detalle.
     *
     * @param array $params parámetros de la petición (valores escalares o null).
     * @return array{code: int, body: array} código HTTP y cuerpo JSON de la respuesta.
     */
    public static function build(array $params): array
    {
        // periodo: solo anual, y si no se indica se usa el anual
        $rawPeriod = trim((string)($params['periodo'] ?? ''));
        $period = $rawPeriod === '' ? 'ANUAL' : static::normalizePeriod($rawPeriod);
        if (false === in_array($period, static::PERIODS, true)) {
            return static::error(400, 'invalid-period', ['allowed' => static::PERIODS]);
        }

        // ejercicio y empresa
        $resolved = static::resolveExercise($params);
        if (isset($resolved['error'])) {
            return $resolved['error'];
        }
        $exercise = $resolved['exercise'];
        $detail = static::parseBool($params['detalle'] ?? '');

        // regularización anual guardada (periodo Y) o una en memoria sin guardar
        [$reg, $exists] = static::loadRegularization($exercise, 'Y');

        // mismo cálculo que la pantalla y el fichero .390
        $resumen = Modelo303::taxSummary($reg);
        $modelo = Modelo390::fromRegularizacion($reg, $resumen);
        $squares = $modelo->getSquares();
        $invoices = Modelo303::invoicesWithoutAccounting($reg);

        $data = [
            'modelo' => static::MODEL,
            'plugin_version' => static::pluginVersion(),
            'empresa' => static::companyData($resolved['company']),
            'ejercicio' => static::exerciseData($exercise),
            'periodo' => $period,
            'fechainicio' => static::isoDate($reg->fechainicio),
            'fechafin' => static::isoDate($reg->fechafin),
            'parametros' => ['detalle' => $detail],
            'regularizacion' => static::presentationData($reg, $exists),
            'resumen' => static::summary($squares),
            'casillas' => static::squares($squares),
            'resultado' => static::amount($squares['86'] ?? 0.0),
            // regularizaciones periódicas del año usadas en el resultado de las liquidaciones
            'periodos' => static::periodRows($modelo->getPeriodos()),
        ];

        if ($detail) {
            $data['detalle'] = [
                'resumen_impuestos' => static::summaryRows($resumen),
                'facturas_sin_asiento' => static::invoiceRows($invoices),
            ];
        }

        $data['avisos'] = static::warnings($modelo->getAvisos(), $invoices, $exists);
        return ['code' => 200, 'body' => $data];
    }

    /**
     * Datos de la regularización anual usada en el cálculo y, si está guardada, los de presentación del fichero
     * .390 (actividad, clave, epígrafe del IAE, operaciones del 347, sustitutiva y casillas manuales).
     *
     * @param RegularizacionImpuesto $reg
     * @param bool $exists si la regularización está guardada
     * @return array
     */
    protected static function presentationData(RegularizacionImpuesto $reg, bool $exists): array
    {
        $data = static::regularizationData($reg, $exists);
        if (false === $exists) {
            return $data;
        }

        $data['actividad'] = empty($reg->actividad390) ? null : (string)$reg->actividad390;
        $data['clave_actividad'] = empty($reg->claveactividad390) ? null : (string)$reg->claveactividad390;
        $data['epigrafe_iae'] = empty($reg->epigrafeiae390) ? null : (string)$reg->epigrafeiae390;
        $data['operaciones_347'] = (bool)$reg->operaciones347;
        $data['sustitutiva'] = (bool)$reg->rectificativa;
        $data['justificanteant'] = empty($reg->justificanteant) ? null : (string)$reg->justificanteant;

        $manual = [];
        foreach (Modelo390::MANUAL_SQUARES as $box => $column) {
            $manual[$box] = static::amount($reg->{$column} ?? 0.0);
        }
        $data['casillas_manuales'] = $manual;

        return $data;
    }

    /**
     * Devuelve los resultados de las regularizaciones periódicas del año como filas planas.
     *
     * @param array $periodos resultado de Modelo390::getPeriodos().
     * @return array
     */
    protected static function periodRows(array $periodos): array
    {
        $rows = [];
        foreach ($periodos as $periodo) {
            $rows[] = [
                'idregiva' => (int)$periodo['idregiva'],
                'periodo' => (string)$periodo['periodo'],
                'fechainicio' => (string)$periodo['fechainicio'],
                'fechafin' => (string)$periodo['fechafin'],
                'tipodeclaracion' => (string)$periodo['tipo'],
                'resultado' => static::amount($periodo['resultado']),
                'pendiente_compensar' => static::amount($periodo['pendiente']),
            ];
        }

        return $rows;
    }

    /**
     * Devuelve las casillas del modelo 390 que no valen 0 más las que se devuelven siempre,
     * ordenadas por número de casilla.
     *
     * @param array<string, float> $squares
     * @return array<string, float>
     */
    protected static function squares(array $squares): array
    {
        $result = [];
        foreach ($squares as $box => $value) {
            $box = (string)$box;
            if (round((float)$value, 2) == 0.0 && false === in_array($box, static::ALWAYS_SQUARES, true)) {
                continue;
            }
            $result[$box] = static::amount($value);
        }

        foreach (static::ALWAYS_SQUARES as $box) {
            $result[$box] = $result[$box] ?? 0.0;
        }

        uksort($result, static fn($a, $b) => (int)$a <=> (int)$b ?: strcmp((string)$a, (string)$b));
        return $result;
    }

    /**
     * Resumen con nombre de los importes del modelo 390.
     *
     * @param array<string, float> $squares
     * @return array<string, float>
     */
    protected static function summary(array $squares): array
    {
        return [
            'base_imponible_devengado' => static::amount($squares['33'] ?? 0.0),
            'cuota_iva_devengado' => static::amount($squares['34'] ?? 0.0),
            'cuota_recargo_equivalencia' => static::amount(($squares['47'] ?? 0.0) - ($squares['34'] ?? 0.0)),
            'iva_devengado' => static::amount($squares['47'] ?? 0.0),
            'base_imponible_deducible' => static::sumSquares($squares, ['48', '52', '56', '597']),
            'iva_deducible' => static::amount($squares['64'] ?? 0.0),
            'resultado_regimen_general' => static::amount($squares['65'] ?? 0.0),
            'suma_resultados' => static::amount($squares['84'] ?? 0.0),
            'compensacion_ejercicio_anterior' => static::amount($squares['85'] ?? 0.0),
            'resultado_liquidacion' => static::amount($squares['86'] ?? 0.0),
            'total_a_ingresar_periodos' => static::amount($squares['95'] ?? 0.0),
            'devoluciones_mensuales' => static::amount($squares['96'] ?? 0.0),
            'a_compensar_ultimo_periodo' => static::amount($squares['97'] ?? 0.0),
            'a_devolver_ultimo_periodo' => static::amount($squares['98'] ?? 0.0),
            'pendiente_compensar_generado_ejercicio' => static::amount($squares['662'] ?? 0.0),
            'volumen_operaciones' => static::amount($squares['108'] ?? 0.0),
        ];
    }
}
