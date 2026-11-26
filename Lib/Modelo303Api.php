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
use FacturaScripts\Dinamic\Model\RegularizacionImpuesto;

/**
 * Construye la respuesta del endpoint de solo lectura GET /api/3/modelo303 (autoliquidación de IVA trimestral o
 * mensual). El resumen anual (modelo 390) tiene su propio endpoint, GET /api/3/modelo390 (Modelo390Api).
 *
 * Busca la regularización de IVA guardada de la empresa, ejercicio y periodo pedidos y, si existe, calcula el
 * modelo sobre ella (con sus casillas de introducción manual). Si no existe, calcula sobre una regularización en
 * memoria con las fechas del periodo, que nunca se guarda. El cálculo es el mismo que el de la pantalla y el del
 * fichero: Modelo303::fromRegularizacion().
 *
 * No crea, modifica ni borra ningún registro.
 *
 * @author Daniel Fernández Giménez <contacto@danielfg.es>
 */
class Modelo303Api
{
    use Modelo303ApiTrait;

    /** Casillas que se devuelven siempre, aunque valgan 0. */
    protected const ALWAYS_SQUARES = ['27', '45', '46', '64', '69', '71'];

    /** Casillas de base del IVA deducible. */
    protected const BASE_DEDUCIBLE_SQUARES = ['28', '30', '32', '34', '36', '38', '40'];

    /** Casillas de base del IVA devengado (régimen general, intracomunitarias, ISP y modificaciones). */
    protected const BASE_DEVENGADO_SQUARES = ['150', '165', '01', '153', '162', '04', '07', '10', '12', '14'];

    /** Casillas de base del recargo de equivalencia. */
    protected const BASE_RECARGO_SQUARES = ['156', '168', '16', '19', '22', '25'];

    /** Casillas de cuota del IVA devengado (sin recargo de equivalencia). */
    protected const CUOTA_IVA_DEVENGADO_SQUARES = ['152', '167', '03', '155', '164', '06', '09', '11', '13', '15'];

    /** Casillas de cuota del recargo de equivalencia. */
    protected const CUOTA_RECARGO_SQUARES = ['158', '170', '18', '21', '24', '26'];

    /**
     * Casillas de porcentaje fijo y la casilla de base de la que dependen: solo se devuelven
     * si su base no es 0.
     */
    protected const PERCENT_SQUARES = [
        '02' => '01', '05' => '04', '08' => '07', '17' => '16', '20' => '19', '23' => '22',
        '151' => '150', '154' => '153', '157' => '156', '163' => '162', '166' => '165',
        '169' => '168', '65' => '64',
    ];

    /** Modelo de la AEAT. */
    public const MODEL = '303';

    /** Periodos admitidos, tal y como se devuelven en la respuesta. */
    public const PERIODS = [
        'T1', 'T2', 'T3', 'T4',
        '01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12',
    ];

    /**
     * Construye la respuesta del endpoint a partir de los parámetros de la query string.
     *
     * Parámetros: codejercicio, idempresa, anio, periodo (obligatorio: T1–T4 o 01–12) y detalle.
     *
     * @param array $params parámetros de la petición (valores escalares o null).
     * @return array{code: int, body: array} código HTTP y cuerpo JSON de la respuesta.
     */
    public static function build(array $params): array
    {
        // periodo
        $rawPeriod = trim((string)($params['periodo'] ?? ''));
        if ($rawPeriod === '') {
            return static::error(400, 'missing-period', ['allowed' => static::PERIODS]);
        }
        $period = static::normalizePeriod($rawPeriod);
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

        // regularización guardada del periodo o una en memoria sin guardar
        [$reg, $exists] = static::loadRegularization($exercise, $period);

        // mismo cálculo que la pantalla y el fichero .303
        $resumen = Modelo303::taxSummary($reg);
        $modelo = Modelo303::fromRegularizacion($reg, $resumen);
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
            'resultado' => static::amount($squares['71'] ?? 0.0),
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
     * Datos de la regularización usada en el cálculo y, si está guardada, los de presentación del fichero .303
     * (tipo de declaración, IBAN, sin actividad, rectificativa y casillas de introducción manual).
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

        $data['tipodeclaracion'] = empty($reg->tipodeclaracion) ? null : (string)$reg->tipodeclaracion;
        $data['iban'] = empty($reg->iban) ? null : (string)$reg->iban;
        $data['sinactividad'] = (bool)$reg->sinactividad;
        $data['rectificativa'] = (bool)$reg->rectificativa;
        $data['justificanteant'] = empty($reg->justificanteant) ? null : (string)$reg->justificanteant;

        $manual = [];
        foreach (Modelo303::MANUAL_SQUARES as $box) {
            $value = $reg->{'c' . $box} ?? null;
            $manual[$box] = $value === null ? null : static::amount($value);
        }
        $data['casillas_manuales'] = $manual;

        return $data;
    }

    /**
     * Devuelve las casillas que no valen 0 más las que se devuelven siempre, ordenadas por
     * número de casilla. Las de porcentaje fijo solo se devuelven si su base no es 0.
     *
     * @param array<string, float> $squares
     * @return array<string, float>
     */
    protected static function squares(array $squares): array
    {
        $result = [];
        foreach ($squares as $box => $value) {
            $box = (string)$box;
            if (isset(static::PERCENT_SQUARES[$box])) {
                if (round((float)($squares[static::PERCENT_SQUARES[$box]] ?? 0.0), 2) == 0.0) {
                    continue;
                }
            } elseif (round((float)$value, 2) == 0.0 && false === in_array($box, static::ALWAYS_SQUARES, true)) {
                continue;
            }

            $result[str_pad($box, 2, '0', STR_PAD_LEFT)] = static::amount($value);
        }

        uksort($result, static fn($a, $b) => (int)$a <=> (int)$b);
        return $result;
    }

    /**
     * Resumen de importes con nombre.
     *
     * @param array<string, float> $squares
     * @return array<string, float>
     */
    protected static function summary(array $squares): array
    {
        return [
            'base_imponible_devengado' => static::sumSquares($squares, static::BASE_DEVENGADO_SQUARES),
            'cuota_iva_devengado' => static::sumSquares($squares, static::CUOTA_IVA_DEVENGADO_SQUARES),
            'base_recargo_equivalencia' => static::sumSquares($squares, static::BASE_RECARGO_SQUARES),
            'cuota_recargo_equivalencia' => static::sumSquares($squares, static::CUOTA_RECARGO_SQUARES),
            'iva_devengado' => static::amount($squares['27'] ?? 0.0),
            'base_imponible_deducible' => static::sumSquares($squares, static::BASE_DEDUCIBLE_SQUARES),
            'iva_deducible' => static::amount($squares['45'] ?? 0.0),
            'resultado_regimen_general' => static::amount($squares['46'] ?? 0.0),
            'suma_resultados' => static::amount($squares['64'] ?? 0.0),
            'atribuible_estado' => static::amount($squares['66'] ?? 0.0),
            'cuotas_compensar_periodos_anteriores' => static::amount($squares['110'] ?? 0.0),
            'cuotas_compensadas_periodo' => static::amount($squares['78'] ?? 0.0),
            'a_compensar_periodos_posteriores' => static::amount($squares['87'] ?? 0.0),
            'resultado_autoliquidacion' => static::amount($squares['69'] ?? 0.0),
            'resultado' => static::amount($squares['71'] ?? 0.0),
        ];
    }
}
