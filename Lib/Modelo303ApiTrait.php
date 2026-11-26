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

use FacturaScripts\Core\Plugins;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Lib\Modelo303;
use FacturaScripts\Dinamic\Lib\Modelo390;
use FacturaScripts\Dinamic\Model\Ejercicio;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Dinamic\Model\RegularizacionImpuesto;

/**
 * Código común de las respuestas de la API del plugin: Modelo303Api (GET /api/3/modelo303) y Modelo390Api
 * (GET /api/3/modelo390). Resuelve la empresa y el ejercicio de la petición, normaliza el periodo, busca la
 * regularización de IVA del periodo (o crea una en memoria sin guardarla), da formato a fechas e importes y
 * construye los bloques que comparten los dos modelos (empresa, ejercicio, regularización, detalle y avisos).
 *
 * Es un trait y no una clase base para que no se despliegue en Dinamic/Lib, que es común a todos los plugins.
 *
 * @author Daniel Fernández Giménez <contacto@danielfg.es>
 */
trait Modelo303ApiTrait
{
    /**
     * Normaliza el periodo recibido al formato público de los endpoints: T1–T4, 01–12 o ANUAL.
     * Acepta también 1T–4T, 1–12 y ANNUAL, Y, A y 0A para el anual.
     *
     * @param string $period
     * @return string|null periodo normalizado o null si no es válido.
     */
    public static function normalizePeriod(string $period): ?string
    {
        $period = strtoupper(preg_replace('/\s+/', '', $period));

        if (in_array($period, ['ANUAL', 'ANNUAL', 'Y', 'A', '0A'], true)) {
            return 'ANUAL';
        }

        if (preg_match('/^T([1-4])$/', $period, $matches) || preg_match('/^([1-4])T$/', $period, $matches)) {
            return 'T' . $matches[1];
        }

        if (preg_match('/^\d{1,2}$/', $period) && (int)$period >= 1 && (int)$period <= 12) {
            return sprintf('%02d', (int)$period);
        }

        return null;
    }

    /**
     * Calcula las fechas (Y-m-d) del periodo del modelo en el año natural indicado,
     * con el mismo criterio que RegularizacionImpuesto::setDates().
     *
     * @param int $year año natural.
     * @param string $period periodo interno de la regularización: T1–T4, 01–12 o Y.
     * @return array{0: string, 1: string} fecha de inicio y fecha de fin.
     */
    public static function periodDates(int $year, string $period): array
    {
        if ($period === 'Y') {
            return [$year . '-01-01', $year . '-12-31'];
        }

        if (preg_match('/^T([1-4])$/', $period, $matches)) {
            $firstMonth = ((int)$matches[1] - 1) * 3 + 1;
            $lastMonth = $firstMonth + 2;
        } else {
            $firstMonth = $lastMonth = (int)$period;
        }

        $start = sprintf('%04d-%02d-01', $year, $firstMonth);
        $lastDay = (int)date('t', strtotime(sprintf('%04d-%02d-01', $year, $lastMonth)));
        $end = sprintf('%04d-%02d-%02d', $year, $lastMonth, $lastDay);
        return [$start, $end];
    }

    /**
     * Redondea un importe a 2 decimales.
     *
     * @param mixed $value
     * @return float
     */
    protected static function amount($value): float
    {
        return round((float)$value, 2);
    }

    /**
     * Devuelve los datos básicos de la empresa para la respuesta.
     *
     * @param Empresa $company empresa declarante
     *
     * @return array
     */
    protected static function companyData(Empresa $company): array
    {
        return [
            'idempresa' => (int)$company->idempresa,
            'nombre' => (string)$company->nombre,
            'cifnif' => (string)$company->cifnif,
        ];
    }

    /**
     * Prepara una respuesta de error en el formato de los endpoints del core.
     *
     * @param int $code código HTTP del error
     * @param string $message clave del error
     * @param array $extra datos adicionales para la respuesta
     *
     * @return array ['code' => código HTTP, 'body' => cuerpo de la respuesta]
     */
    protected static function error(int $code, string $message, array $extra = []): array
    {
        return ['code' => $code, 'body' => array_merge(['status' => 'error', 'message' => $message], $extra)];
    }

    /**
     * Devuelve los datos básicos del ejercicio para la respuesta.
     *
     * @param Ejercicio $exercise ejercicio del modelo
     *
     * @return array
     */
    protected static function exerciseData(Ejercicio $exercise): array
    {
        return [
            'codejercicio' => (string)$exercise->codejercicio,
            'nombre' => (string)$exercise->nombre,
            'fechainicio' => static::isoDate($exercise->fechainicio),
            'fechafin' => static::isoDate($exercise->fechafin),
            'estado' => (string)$exercise->estado,
        ];
    }

    /**
     * Devuelve las facturas sin asiento como filas planas.
     *
     * @param array $invoices resultado de Modelo303::invoicesWithoutAccounting().
     * @return array
     */
    protected static function invoiceRows(array $invoices): array
    {
        $rows = [];
        foreach (['venta', 'compra'] as $type) {
            foreach ($invoices[$type] ?? [] as $invoice) {
                $rows[] = [
                    'tipo' => $type,
                    'codigo' => (string)$invoice->codigo,
                    'fecha' => static::isoDate($invoice->fecha),
                    'total' => static::amount($invoice->total),
                ];
            }
        }

        return $rows;
    }

    /**
     * Convierte una fecha del modelo (d-m-Y) a formato ISO Y-m-d.
     *
     * @param string|null $date
     * @return string|null
     */
    protected static function isoDate(?string $date): ?string
    {
        return empty($date) ? null : date('Y-m-d', strtotime($date));
    }

    /**
     * Busca la regularización de IVA guardada de la empresa, ejercicio y periodo; si no existe, devuelve una
     * en memoria con las fechas del periodo, que nunca se guarda.
     *
     * @param Ejercicio $exercise ejercicio del modelo
     * @param string $regPeriod periodo interno de la regularización: T1–T4, 01–12 o Y
     *
     * @return array{0: RegularizacionImpuesto, 1: bool} la regularización y si está guardada
     */
    protected static function loadRegularization(Ejercicio $exercise, string $regPeriod): array
    {
        $reg = new RegularizacionImpuesto();
        $exists = $reg->loadWhere([
            Where::eq('idempresa', $exercise->idempresa),
            Where::eq('codejercicio', $exercise->codejercicio),
            Where::eq('periodo', $regPeriod),
        ], ['idregiva' => 'ASC']);

        return $exists ? [$reg, true] : [static::unsavedRegularization($exercise, $regPeriod), false];
    }

    /**
     * Interpreta un parámetro booleano: 1, true, yes, si, sí y on son verdaderos.
     *
     * @param mixed $value valor recibido
     *
     * @return bool
     */
    protected static function parseBool($value): bool
    {
        return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'si', 'sí', 'on'], true);
    }

    /**
     * Versión instalada del plugin.
     *
     * @return float
     */
    protected static function pluginVersion(): float
    {
        $plugin = Plugins::get('Modelo303');
        if (null !== $plugin && false === empty($plugin->version)) {
            return (float)$plugin->version;
        }

        $ini = parse_ini_file(dirname(__DIR__) . '/facturascripts.ini');
        return (float)($ini['version'] ?? 0);
    }

    /**
     * Datos comunes de la regularización usada en el cálculo (guardada o en memoria). Cada modelo añade sus
     * datos de presentación.
     *
     * @param RegularizacionImpuesto $reg
     * @param bool $exists si la regularización está guardada
     *
     * @return array
     */
    protected static function regularizationData(RegularizacionImpuesto $reg, bool $exists): array
    {
        $data = [
            'existe' => $exists,
            'idregiva' => $exists ? (int)$reg->idregiva : null,
            'idasiento' => empty($reg->idasiento) ? null : (int)$reg->idasiento,
            'periodo' => (string)$reg->periodo,
            'fechainicio' => static::isoDate($reg->fechainicio),
            'fechafin' => static::isoDate($reg->fechafin),
        ];

        if ($exists) {
            $data['fechaasiento'] = static::isoDate($reg->fechaasiento);
            $data['bloquear'] = (bool)$reg->bloquear;
        }

        return $data;
    }

    /**
     * Busca el ejercicio de la petición: por su código si se indica o, si no, por la empresa (la de la
     * petición o la predeterminada) y el año (el de la petición o el actual).
     *
     * @param array $params parámetros de la petición: codejercicio, idempresa y anio
     *
     * @return array ['exercise' => Ejercicio, 'company' => Empresa] o ['error' => respuesta de error]
     */
    protected static function resolveExercise(array $params): array
    {
        $exercise = new Ejercicio();
        $company = new Empresa();

        $codejercicio = trim((string)($params['codejercicio'] ?? ''));
        if ($codejercicio !== '') {
            if (false === $exercise->load($codejercicio)) {
                return ['error' => static::error(404, 'exercise-not-found', ['codejercicio' => $codejercicio])];
            }
            if (false === $company->load($exercise->idempresa)) {
                return ['error' => static::error(404, 'company-not-found', ['idempresa' => (int)$exercise->idempresa])];
            }
            return ['exercise' => $exercise, 'company' => $company];
        }

        // empresa: la indicada o la predeterminada
        $idempresa = trim((string)($params['idempresa'] ?? ''));
        if ($idempresa === '') {
            $idempresa = (string)Tools::settings('default', 'idempresa', '');
        }
        if (false === ctype_digit($idempresa) || false === $company->load((int)$idempresa)) {
            $extra = ['idempresa' => ctype_digit($idempresa) ? (int)$idempresa : $idempresa];
            return ['error' => static::error(404, 'company-not-found', $extra)];
        }

        // año: el indicado o el actual
        $year = trim((string)($params['anio'] ?? ''));
        if ($year === '') {
            $year = date('Y');
        }
        if (false === (bool)preg_match('/^\d{4}$/', $year)) {
            return ['error' => static::error(400, 'invalid-year', ['anio' => $year])];
        }

        // ejercicio de la empresa cuya fecha de inicio cae en ese año
        $where = [
            Where::eq('idempresa', $company->idempresa),
            Where::gte('fechainicio', $year . '-01-01'),
            Where::lte('fechainicio', $year . '-12-31'),
        ];
        if (false === $exercise->loadWhere($where, ['fechainicio' => 'ASC'])) {
            $extra = ['idempresa' => (int)$company->idempresa, 'anio' => (int)$year];
            return ['error' => static::error(404, 'exercise-not-found', $extra)];
        }

        return ['exercise' => $exercise, 'company' => $company];
    }

    /**
     * Devuelve las filas del resumen de partidas de IVA como filas planas.
     *
     * @param array $resumen filas de PartidaImpuestoResumen.
     * @return array
     */
    protected static function summaryRows(array $resumen): array
    {
        $rows = [];
        foreach ($resumen as $item) {
            $rows[] = [
                'codsubcuenta' => (string)$item->codsubcuenta,
                'descripcion' => (string)$item->descripcion,
                'codcuentaesp' => (string)$item->codcuentaesp,
                'operacion' => (string)$item->operacion,
                'tipodoc' => (string)$item->tipodoc,
                'iva' => (float)$item->iva,
                'recargo' => (float)$item->recargo,
                'baseimponible' => static::amount($item->baseimponible),
                'cuota' => static::amount($item->cuota),
            ];
        }

        return $rows;
    }

    /**
     * Suma las casillas indicadas.
     *
     * @param array<string, float> $squares
     * @param string[] $boxes
     * @return float
     */
    protected static function sumSquares(array $squares, array $boxes): float
    {
        $total = 0.0;
        foreach ($boxes as $box) {
            $total += (float)($squares[$box] ?? 0.0);
        }

        return static::amount($total);
    }

    /**
     * Crea una regularización en memoria, SIN guardar, con las fechas del periodo.
     *
     * @param Ejercicio $exercise
     * @param string $regPeriod periodo interno: T1–T4, 01–12 o Y.
     * @return RegularizacionImpuesto
     */
    protected static function unsavedRegularization(Ejercicio $exercise, string $regPeriod): RegularizacionImpuesto
    {
        // mismo año que RegularizacionImpuesto::setDates(): el de inicio del ejercicio
        $year = (int)date('Y', strtotime($exercise->fechainicio));
        [$start, $end] = static::periodDates($year, $regPeriod);

        $reg = new RegularizacionImpuesto();
        $reg->idempresa = $exercise->idempresa;
        $reg->codejercicio = $exercise->codejercicio;
        $reg->periodo = $regPeriod;
        $reg->fechainicio = date('d-m-Y', strtotime($start));
        $reg->fechafin = date('d-m-Y', strtotime($end));

        // casillas manuales a 0 (65: 100 % atribuible a la Administración del Estado)
        foreach (Modelo303::MANUAL_SQUARES as $box) {
            $reg->{'c' . $box} = $box === '65' ? 100.0 : 0.0;
        }
        foreach (Modelo390::MANUAL_SQUARES as $column) {
            $reg->{$column} = 0.0;
        }

        return $reg;
    }

    /**
     * Avisos en español para el usuario.
     *
     * @param string[] $avisos avisos del cálculo del modelo (ya traducidos por Modelo303 o Modelo390).
     * @param array $invoices resultado de Modelo303::invoicesWithoutAccounting().
     * @param bool $exists si la regularización está guardada.
     * @return string[]
     */
    protected static function warnings(array $avisos, array $invoices, bool $exists): array
    {
        $warnings = [];
        if (false === $exists) {
            $warnings[] = 'Cálculo provisional: no hay regularización guardada, las casillas manuales valen 0.';
        }

        // importes sin casilla, regularizaciones que faltan, descuadres... (ya traducidos)
        foreach ($avisos as $aviso) {
            $warnings[] = $aviso;
        }

        $sales = count($invoices['venta'] ?? []);
        if ($sales > 0) {
            $warnings[] = 'Hay ' . $sales . ' facturas de cliente del periodo sin asiento contable: no se tienen en cuenta.';
        }

        $purchases = count($invoices['compra'] ?? []);
        if ($purchases > 0) {
            $warnings[] = 'Hay ' . $purchases . ' facturas de proveedor del periodo sin asiento contable: no se tienen en cuenta.';
        }

        return $warnings;
    }
}
