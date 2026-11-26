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

use FacturaScripts\Dinamic\Lib\Modelo390;
use FacturaScripts\Dinamic\Model\RegularizacionImpuesto;
use FacturaScripts\Plugins\Modelo303\Lib\Modelo390Api;
use FacturaScripts\Test\Traits\DefaultSettingsTrait;
use FacturaScripts\Test\Traits\LogErrorsTrait;
use FacturaScripts\Test\Traits\RandomDataTrait;

/**
 * Pruebas del endpoint de solo lectura /api/3/modelo390 a través de Modelo390Api::build(): casillas del 390,
 * regularización anual guardada o en memoria, periodos del año, errores y coincidencia de cifras con la
 * pantalla.
 *
 * @author Daniel Fernández Giménez <contacto@danielfg.es>
 */
final class Modelo390ApiTest extends Modelo303TestCase
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
     * Devuelve las casillas del modelo 390 sobre todo el año, en una regularización en memoria, con las
     * mismas cifras que la pantalla (Modelo390::fromRegularizacion()). Sin periodo se usa el anual.
     */
    public function testAnnual(): void
    {
        $exercise = $this->getRandomExercise();
        $year = date('Y', strtotime($exercise->fechainicio));

        $result = Modelo390Api::build(['codejercicio' => $exercise->codejercicio]);
        $this->assertSame(200, $result['code']);
        $this->assertSame($result, Modelo390Api::build(['codejercicio' => $exercise->codejercicio, 'periodo' => 'annual']));

        $body = $result['body'];
        $this->assertSame('ANUAL', $body['periodo']);
        $this->assertSame('390', $body['modelo']);
        $this->assertArrayNotHasKey('modelo_anual', $body);
        $this->assertSame('Y', $body['regularizacion']['periodo']);
        $this->assertSame($year . '-01-01', $body['fechainicio']);
        $this->assertSame($year . '-12-31', $body['fechafin']);

        // casillas del 390 que se devuelven siempre; el resultado es la casilla 86
        foreach (['33', '34', '47', '64', '65', '84', '86', '95', '97', '98', '662', '108'] as $box) {
            $this->assertArrayHasKey($box, $body['casillas']);
        }
        $this->assertArrayNotHasKey('71', $body['casillas']);
        $this->assertSame($body['casillas']['86'], $body['resultado']);
        $this->assertSame($body['casillas']['47'], $body['resumen']['iva_devengado']);
        $this->assertSame($body['casillas']['108'], $body['resumen']['volumen_operaciones']);
        $this->assertIsArray($body['periodos']);

        // ya no se avisa de que son las casillas del 303
        foreach ($body['avisos'] as $aviso) {
            $this->assertStringNotContainsString('casillas del 303', $aviso);
        }
    }

    /**
     * Con regularizaciones trimestrales guardadas: devuelve sus resultados, los
     * datos de presentación del 390 y las mismas casillas que la pantalla.
     */
    public function testWithSavedSettlements(): void
    {
        $exercise = $this->getRandomExercise();

        $t1 = new RegularizacionImpuesto();
        $t1->codejercicio = $exercise->codejercicio;
        $t1->periodo = 'T1';
        $t1->c110 = 80.0;
        $t1->c78 = 30.0;
        $this->assertTrue($t1->save());

        $annual = new RegularizacionImpuesto();
        $annual->codejercicio = $exercise->codejercicio;
        $annual->periodo = 'Y';
        $annual->actividad390 = 'Comercio';
        $annual->claveactividad390 = 'A03';
        $annual->c390_106 = 12.5;
        $this->assertTrue($annual->save());

        $this->addCleanup(static function () use ($t1, $annual) {
            foreach ([$t1, $annual] as $reg) {
                if ($reg->exists()) {
                    $reg->delete();
                }
            }
        });

        $result = Modelo390Api::build(['codejercicio' => $exercise->codejercicio, 'periodo' => 'ANUAL']);
        $this->assertSame(200, $result['code']);
        $body = $result['body'];

        // la regularización anual guardada, con sus datos de presentación y casillas manuales
        $this->assertTrue($body['regularizacion']['existe']);
        $this->assertSame((int)$annual->idregiva, $body['regularizacion']['idregiva']);
        $this->assertSame('A03', $body['regularizacion']['clave_actividad']);
        $this->assertEqualsWithDelta(12.5, $body['regularizacion']['casillas_manuales']['106'], 0.001);

        // solo la regularización trimestral cuenta como periodo, no la anual
        $this->assertCount(1, $body['periodos']);
        $this->assertSame('T1', $body['periodos'][0]['periodo']);
        $this->assertSame((int)$t1->idregiva, $body['periodos'][0]['idregiva']);

        // compensación del ejercicio anterior: lo compensado (78), hasta lo pendiente al empezar (110)
        $this->assertEqualsWithDelta(30.0, $body['casillas']['85'], 0.001);

        // faltan los trimestres 2, 3 y 4
        $this->assertNotEmpty(array_filter($body['avisos'], static fn($aviso) => str_contains($aviso, '4, 5, 6')));

        // mismas cifras que la pantalla
        $screen = Modelo390::fromRegularizacion($annual);
        foreach (['47', '64', '65', '84', '85', '86', '95', '108'] as $box) {
            $this->assertEqualsWithDelta($screen->casilla($box), $body['casillas'][$box], 0.001, $box);
        }
        $this->assertEqualsWithDelta(12.5, $screen->casilla('106'), 0.001);
    }

    /**
     * Errores de parámetros: periodo distinto del anual, ejercicio, empresa y año.
     */
    public function testErrors(): void
    {
        foreach (['T1', '03', 'X'] as $period) {
            $result = Modelo390Api::build(['periodo' => $period]);
            $this->assertSame(400, $result['code'], $period);
            $this->assertSame('invalid-period', $result['body']['message']);
            $this->assertSame(['ANUAL'], $result['body']['allowed']);
        }

        $result = Modelo390Api::build(['codejercicio' => 'ZZZZ']);
        $this->assertSame(404, $result['code']);
        $this->assertSame('exercise-not-found', $result['body']['message']);

        $result = Modelo390Api::build(['idempresa' => 999999]);
        $this->assertSame(404, $result['code']);
        $this->assertSame('company-not-found', $result['body']['message']);

        $result = Modelo390Api::build(['anio' => '20x6']);
        $this->assertSame(400, $result['code']);
        $this->assertSame('invalid-year', $result['body']['message']);
    }
}
