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

namespace FacturaScripts\Test\Plugins;

use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Lib\Modelo303;
use FacturaScripts\Dinamic\Model\Ejercicio;
use FacturaScripts\Dinamic\Model\RegularizacionImpuesto;
use FacturaScripts\Plugins\Modelo303\Lib\Modelo303Api;
use FacturaScripts\Test\Traits\DefaultSettingsTrait;
use FacturaScripts\Test\Traits\LogErrorsTrait;
use FacturaScripts\Test\Traits\RandomDataTrait;

/**
 * Pruebas del endpoint de solo lectura /api/3/modelo303 a través de Modelo303Api::build():
 * periodos, fechas, errores, resolución de empresa y año, regularización guardada o en
 * memoria y coincidencia de cifras con la pantalla. El modelo 390 se prueba en Modelo390ApiTest.
 *
 * @author Daniel Fernández Giménez <contacto@danielfg.es>
 */
final class Modelo303ApiTest extends Modelo303TestCase
{
    use DefaultSettingsTrait;
    use LogErrorsTrait;
    use RandomDataTrait;

    /**
     * Prepara el plan contable y elimina las regularizaciones previas para que no haya
     * duplicados del mismo periodo.
     */
    public static function setUpBeforeClass(): void
    {
        self::setDefaultSettings();
        self::installAccountingPlan();
        self::removeTaxRegularization();
    }

    /**
     * Errores de parámetros: periodo ausente o no válido, ejercicio, empresa y año.
     */
    public function testErrors(): void
    {
        $result = Modelo303Api::build([]);
        $this->assertSame(400, $result['code']);
        $this->assertSame('error', $result['body']['status']);
        $this->assertSame('missing-period', $result['body']['message']);

        $result = Modelo303Api::build(['periodo' => 'T5']);
        $this->assertSame(400, $result['code']);
        $this->assertSame('invalid-period', $result['body']['message']);
        $this->assertNotContains('ANUAL', $result['body']['allowed']);
        $this->assertContains('T1', $result['body']['allowed']);
        $this->assertContains('12', $result['body']['allowed']);

        $result = Modelo303Api::build(['periodo' => 'ANUAL']);
        $this->assertSame(400, $result['code']);
        $this->assertSame('invalid-period', $result['body']['message']);

        $result = Modelo303Api::build(['periodo' => 'T1', 'codejercicio' => 'ZZZZ']);
        $this->assertSame(404, $result['code']);
        $this->assertSame('exercise-not-found', $result['body']['message']);

        $result = Modelo303Api::build(['periodo' => 'T1', 'idempresa' => 999999]);
        $this->assertSame(404, $result['code']);
        $this->assertSame('company-not-found', $result['body']['message']);

        $result = Modelo303Api::build(['periodo' => 'T1', 'anio' => '1901']);
        $this->assertSame(404, $result['code']);
        $this->assertSame('exercise-not-found', $result['body']['message']);

        $result = Modelo303Api::build(['periodo' => 'T1', 'anio' => '20x6']);
        $this->assertSame(400, $result['code']);
        $this->assertSame('invalid-year', $result['body']['message']);
    }

    /**
     * Normalización de los periodos admitidos y rechazo de los no válidos.
     */
    public function testNormalizePeriod(): void
    {
        $this->assertSame('T1', Modelo303Api::normalizePeriod('t1'));
        $this->assertSame('T4', Modelo303Api::normalizePeriod(' 4T '));
        $this->assertSame('01', Modelo303Api::normalizePeriod('1'));
        $this->assertSame('09', Modelo303Api::normalizePeriod('09'));
        $this->assertSame('12', Modelo303Api::normalizePeriod('12'));
        foreach (['anual', 'ANNUAL', 'Y', 'a', '0A'] as $annual) {
            $this->assertSame('ANUAL', Modelo303Api::normalizePeriod($annual));
        }

        foreach (['T5', '0', '13', 'X', '5T', ''] as $invalid) {
            $this->assertNull(Modelo303Api::normalizePeriod($invalid), $invalid);
        }
    }

    /**
     * Fechas de los periodos, incluidos los febreros bisiestos.
     */
    public function testPeriodDates(): void
    {
        $this->assertSame(['2026-01-01', '2026-03-31'], Modelo303Api::periodDates(2026, 'T1'));
        $this->assertSame(['2026-04-01', '2026-06-30'], Modelo303Api::periodDates(2026, 'T2'));
        $this->assertSame(['2026-07-01', '2026-09-30'], Modelo303Api::periodDates(2026, 'T3'));
        $this->assertSame(['2026-10-01', '2026-12-31'], Modelo303Api::periodDates(2026, 'T4'));
        $this->assertSame(['2026-02-01', '2026-02-28'], Modelo303Api::periodDates(2026, '02'));
        $this->assertSame(['2028-02-01', '2028-02-29'], Modelo303Api::periodDates(2028, '02'));
        $this->assertSame(['2026-11-01', '2026-11-30'], Modelo303Api::periodDates(2026, '11'));
        $this->assertSame(['2026-01-01', '2026-12-31'], Modelo303Api::periodDates(2026, 'Y'));
    }

    /**
     * Sin codejercicio, el ejercicio se resuelve por empresa y año natural.
     */
    public function testResolveByCompanyAndYear(): void
    {
        $exercise = $this->getRandomExercise();
        $year = date('Y', strtotime($exercise->fechainicio));

        $result = Modelo303Api::build([
            'idempresa' => $exercise->idempresa,
            'anio' => $year,
            'periodo' => '1T',
        ]);
        $this->assertSame(200, $result['code']);

        // el primer ejercicio de la empresa que empieza en ese año
        $expected = Ejercicio::all([
            Where::eq('idempresa', $exercise->idempresa),
            Where::gte('fechainicio', '01-01-' . $year),
            Where::lte('fechainicio', '31-12-' . $year),
        ], ['fechainicio' => 'ASC'], 0, 1)[0];

        $body = $result['body'];
        $this->assertSame((int)$exercise->idempresa, $body['empresa']['idempresa']);
        $this->assertSame($expected->codejercicio, $body['ejercicio']['codejercicio']);
        $this->assertSame('T1', $body['periodo']);
    }

    /**
     * Sin regularización guardada: calcula en memoria, no guarda nada y refleja las facturas
     * nuevas del periodo.
     */
    public function testWithoutSavedRegularization(): void
    {
        $date = date('d-m-Y');
        $exercise = new Ejercicio();
        $exercise->idempresa = (int)Tools::settings('default', 'idempresa');
        $this->assertTrue($exercise->loadFromDate($date));

        // por si el ejercicio se acaba de crear, nos aseguramos de que tiene plan contable
        self::installAccountingPlan();

        $period = 'T' . (int)ceil(date('n') / 3);
        $params = ['codejercicio' => $exercise->codejercicio, 'periodo' => $period, 'detalle' => 'true'];
        $regCount = RegularizacionImpuesto::count();

        $before = Modelo303Api::build($params);
        $this->assertSame(200, $before['code']);

        // creamos una factura de venta en el periodo
        $invoice = $this->getRandomCustomerInvoice($date);
        $this->addCleanup(static function () use ($invoice) {
            if ($invoice->exists()) {
                $invoice->delete();
            }
            $subject = $invoice->getSubject();
            if ($subject->id()) {
                $subject->delete();
            }
        });
        $this->assertNotEmpty($invoice->idasiento);

        $after = Modelo303Api::build($params);
        $this->assertSame(200, $after['code']);

        // no se ha guardado ninguna regularización
        $this->assertSame($regCount, RegularizacionImpuesto::count());

        $body = $after['body'];
        $this->assertFalse($body['regularizacion']['existe']);
        $this->assertNull($body['regularizacion']['idregiva']);
        $this->assertSame('303', $body['modelo']);
        $this->assertArrayNotHasKey('modelo_anual', $body);
        $this->assertSame($exercise->codejercicio, $body['ejercicio']['codejercicio']);
        // el aviso sale de las traducciones, no de un texto fijo
        $this->assertContains(Tools::trans('api-model303-unsaved-calculation'), $body['avisos']);
        $this->assertContains(
            'Cálculo provisional: no hay regularización guardada, las casillas manuales valen 0.',
            $body['avisos']
        );

        // casillas que se devuelven siempre y resultado = casilla 71
        foreach (['27', '45', '46', '64', '69', '71'] as $box) {
            $this->assertArrayHasKey($box, $body['casillas']);
        }
        $this->assertSame($body['casillas']['71'], $body['resultado']);
        $this->assertSame($body['casillas']['27'], $body['resumen']['iva_devengado']);

        // la factura nueva suma su IVA al devengado
        $this->assertEqualsWithDelta(
            $before['body']['resumen']['iva_devengado'] + (float)$invoice->totaliva,
            $body['resumen']['iva_devengado'],
            0.01
        );

        // detalle con filas planas
        $this->assertIsArray($body['detalle']['resumen_impuestos']);
        $this->assertNotEmpty($body['detalle']['resumen_impuestos']);
        $this->assertIsArray($body['detalle']['facturas_sin_asiento']);
        foreach ($body['detalle']['resumen_impuestos'] as $row) {
            foreach (['codcuentaesp', 'operacion', 'iva', 'recargo', 'baseimponible', 'cuota'] as $key) {
                $this->assertArrayHasKey($key, $row);
            }
            foreach ($row as $value) {
                $this->assertIsScalar($value);
            }
        }

        // sin detalle, no hay lista de detalle
        $params['detalle'] = '0';
        $this->assertArrayNotHasKey('detalle', Modelo303Api::build($params)['body']);
    }

    /**
     * Con regularización guardada: usa sus casillas manuales y da las mismas cifras que la
     * pantalla (Modelo303::fromRegularizacion()).
     */
    public function testWithSavedRegularization(): void
    {
        $exercise = $this->getRandomExercise();

        $reg = new RegularizacionImpuesto();
        $reg->codejercicio = $exercise->codejercicio;
        $reg->periodo = 'T2';
        $reg->c70 = 25.5;
        $reg->c110 = 100.0;
        $reg->c78 = 40.0;
        $reg->iban = 'ES91 2100 0418 4502 0005 1332';
        $this->assertTrue($reg->save());
        $this->addCleanup(static function () use ($reg) {
            if ($reg->exists()) {
                $reg->delete();
            }
        });

        $result = Modelo303Api::build(['codejercicio' => $exercise->codejercicio, 'periodo' => 'T2']);
        $this->assertSame(200, $result['code']);

        $body = $result['body'];
        $this->assertTrue($body['regularizacion']['existe']);
        $this->assertSame((int)$reg->idregiva, $body['regularizacion']['idregiva']);
        $this->assertEqualsWithDelta(25.5, $body['regularizacion']['casillas_manuales']['70'], 0.001);
        $this->assertNotContains(
            'Cálculo provisional: no hay regularización guardada, las casillas manuales valen 0.',
            $body['avisos']
        );

        // el IBAN se devuelve enmascarado: país, control y los 4 últimos caracteres
        $this->assertSame('ES91****************1332', $body['regularizacion']['iban']);

        // mismas cifras que la pantalla y el fichero .303
        $screen = Modelo303::fromRegularizacion($reg);
        foreach (['27', '45', '46', '64', '69', '71', '87'] as $box) {
            $this->assertEqualsWithDelta($screen->casilla($box), $body['resumen'][self::summaryKey($box)], 0.001, $box);
        }

        // las casillas manuales entran en la cadena de resultado
        $this->assertEqualsWithDelta(60.0, $body['resumen']['a_compensar_periodos_posteriores'], 0.001);
        $this->assertEqualsWithDelta(
            $body['resumen']['resultado_autoliquidacion'] - 25.5,
            $body['resultado'],
            0.001
        );
        $this->assertEqualsWithDelta(25.5, $body['casillas']['70'], 0.001);
    }

    /**
     * Clave del resumen correspondiente a una casilla.
     *
     * @param string $box
     * @return string
     */
    private static function summaryKey(string $box): string
    {
        return [
            '27' => 'iva_devengado',
            '45' => 'iva_deducible',
            '46' => 'resultado_regimen_general',
            '64' => 'suma_resultados',
            '69' => 'resultado_autoliquidacion',
            '71' => 'resultado',
            '87' => 'a_compensar_periodos_posteriores',
        ][$box];
    }
}
