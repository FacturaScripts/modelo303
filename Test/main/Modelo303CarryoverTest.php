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

use FacturaScripts\Dinamic\Model\Ejercicio;
use FacturaScripts\Dinamic\Model\RegularizacionImpuesto;
use FacturaScripts\Plugins\Modelo303\Lib\Modelo303;
use FacturaScripts\Test\Traits\DefaultSettingsTrait;
use FacturaScripts\Test\Traits\LogErrorsTrait;
use FacturaScripts\Test\Traits\RandomDataTrait;

/**
 * Pruebas del arrastre de cuotas a compensar entre periodos (casilla 110 del 303): lo que deja
 * pendiente una regularización y cuál es la regularización anterior.
 *
 * @author Daniel Fernández Giménez <contacto@danielfg.es>
 */
final class Modelo303CarryoverTest extends Modelo303TestCase
{
    use DefaultSettingsTrait;
    use LogErrorsTrait;
    use RandomDataTrait;

    /**
     * Prepara el plan contable y elimina las regularizaciones previas.
     */
    public static function setUpBeforeClass(): void
    {
        self::setDefaultSettings();
        self::installAccountingPlan();
        self::removeTaxRegularization();
    }

    /**
     * Tipo de declaración automático según el resultado y el IBAN, salvo que se indique a mano.
     */
    public function testDeclarationType(): void
    {
        $reg = new RegularizacionImpuesto();
        $this->assertSame('I', Modelo303::declarationType($reg, 10.0));
        $this->assertSame('C', Modelo303::declarationType($reg, -10.0));
        $this->assertSame('N', Modelo303::declarationType($reg, 0.0));

        $reg->iban = 'ES9121000418450200051332';
        $this->assertSame('U', Modelo303::declarationType($reg, 10.0));
        $this->assertSame('D', Modelo303::declarationType($reg, -10.0));

        $reg->tipodeclaracion = 'c';
        $this->assertSame('C', Modelo303::declarationType($reg, -10.0));
    }

    /**
     * Un resultado negativo que se pide compensar pasa al periodo siguiente junto con lo que ya
     * estaba pendiente (casilla 87).
     */
    public function testPendingCarryoverACompensar(): void
    {
        $reg = $this->createRegularization($this->getRandomExercise(), 'T1', 'C');
        $modelo = Modelo303::fromRegularizacion($reg);

        $this->assertEqualsWithDelta(60.0, $modelo->casilla('87'), 0.001);
        $this->assertLessThan(0.0, $modelo->casilla('71'));
        $this->assertEqualsWithDelta(
            60.0 + abs($modelo->casilla('71')),
            Modelo303::pendingCarryover($reg),
            0.01
        );
    }

    /**
     * Un resultado negativo que se pide devolver no se arrastra: solo queda lo pendiente (87).
     */
    public function testPendingCarryoverADevolver(): void
    {
        $reg = $this->createRegularization($this->getRandomExercise(), 'T2', 'D');
        $this->assertEqualsWithDelta(60.0, Modelo303::pendingCarryover($reg), 0.01);
        $this->assertSame('D', Modelo303::declarationType($reg, -1.0));
    }

    /**
     * La regularización anterior es la del último periodo, nunca la anual (modelo 390), aunque
     * terminen el mismo día.
     */
    public function testPreviousRegularizationIgnoresAnnual(): void
    {
        $exercise = $this->getRandomExercise();
        $t4 = $this->createRegularization($exercise, 'T4');
        $this->createRegularization($exercise, 'Y');

        // un periodo del año siguiente: su regularización anterior es el T4
        $next = new RegularizacionImpuesto();
        $next->idempresa = $exercise->idempresa;
        $next->fechainicio = (date('Y', strtotime($exercise->fechainicio)) + 1) . '-01-01';

        $previous = Modelo303::previousRegularization($next);
        $this->assertNotNull($previous);
        $this->assertSame((int)$t4->idregiva, (int)$previous->idregiva);

        // sin regularizaciones anteriores
        $first = new RegularizacionImpuesto();
        $first->idempresa = $exercise->idempresa;
        $first->fechainicio = '1990-01-01';
        $this->assertNull(Modelo303::previousRegularization($first));
    }

    /**
     * Crea una regularización con 100 pendientes de periodos anteriores, 40 compensados (87 = 60) y
     * un resultado negativo forzado con la casilla 70, y programa su borrado.
     *
     * @param Ejercicio $exercise
     * @param string $periodo
     * @param string $tipo tipo de declaración
     * @return RegularizacionImpuesto
     */
    private function createRegularization(Ejercicio $exercise, string $periodo, string $tipo = ''): RegularizacionImpuesto
    {
        $reg = new RegularizacionImpuesto();
        $reg->codejercicio = $exercise->codejercicio;
        $reg->periodo = $periodo;
        $reg->c110 = 100.0;
        $reg->c78 = 40.0;
        $reg->c70 = 10000000.0;
        $reg->tipodeclaracion = $tipo;
        $this->assertTrue($reg->save());
        $this->addCleanup(static function () use ($reg) {
            if ($reg->exists()) {
                $reg->delete();
            }
        });

        return $reg;
    }
}
