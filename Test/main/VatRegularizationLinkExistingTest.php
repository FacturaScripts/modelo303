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
use FacturaScripts\Dinamic\Model\Asiento;
use FacturaScripts\Dinamic\Model\RegularizacionImpuesto;
use FacturaScripts\Plugins\Modelo303\Lib\Accounting\VatRegularizationToAccounting;
use FacturaScripts\Test\Traits\DefaultSettingsTrait;
use FacturaScripts\Test\Traits\LogErrorsTrait;
use FacturaScripts\Test\Traits\RandomDataTrait;

/**
 * These tests cover the reported bug: when a user creates the VAT regularization
 * accounting entry by hand (instead of using the "Crear asiento contable" assistant),
 * the entry was never recognized as a regularization and kept being counted in later
 * tax settlements. VatRegularizationToAccounting::linkExisting() lets a manual entry be
 * linked to a settlement, so it's registered exactly like an automatically generated
 * one and excluded afterwards.
 */
final class VatRegularizationLinkExistingTest extends Modelo303TestCase
{
    use DefaultSettingsTrait;
    use LogErrorsTrait;
    use RandomDataTrait;

    public static function setUpBeforeClass(): void
    {
        self::setDefaultSettings();
        self::installAccountingPlan();
        self::removeTaxRegularization();
    }

    public function testLinkExistingManualEntry()
    {
        $exercise = $this->getRandomExercise();

        // creamos a mano un asiento de regularización, sin pasar por el asistente del plugin
        $manualEntry = new Asiento();
        $manualEntry->codejercicio = $exercise->codejercicio;
        $manualEntry->idempresa = (int)Tools::settings('default', 'idempresa');
        $manualEntry->concepto = 'Regularización manual de IVA';
        $manualEntry->fecha = date('31-03-Y', strtotime($exercise->fechainicio));
        $this->assertTrue($manualEntry->save());
        $this->addCleanup(static function () use ($manualEntry) {
            if ($manualEntry->exists()) {
                $manualEntry->delete();
            }
        });

        // creamos la liquidación del trimestre
        $reg = new RegularizacionImpuesto();
        $reg->codejercicio = $exercise->codejercicio;
        $reg->periodo = 'T1';
        $this->assertTrue($reg->save());
        $this->addCleanup(static function () use ($reg) {
            if ($reg->exists()) {
                $reg->delete();
            }
        });

        // antes de vincularlo, el asiento manual no aparece entre las regularizaciones registradas
        $linkedIds = array_map(static fn($r) => $r->idasiento, RegularizacionImpuesto::all());
        $this->assertNotContains($manualEntry->idasiento, $linkedIds);

        // lo vinculamos a la regularización, en lugar de generar un asiento nuevo
        $linker = new VatRegularizationToAccounting();
        $this->assertTrue($linker->linkExisting($reg, $manualEntry->idasiento));

        // se ha completado la fecha del asiento y se ha bloqueado la regularización
        $this->assertEquals($manualEntry->idasiento, $reg->idasiento);
        $this->assertEquals($manualEntry->fecha, $reg->fechaasiento);
        $this->assertTrue($reg->bloquear);

        // ahora sí, el asiento manual queda registrado como asiento de una regularización,
        // que es justo el criterio que usa el plugin para excluirlo de los cálculos
        // posteriores (ver commonTaxWhere() y getSubtotals())
        $linkedIds = array_map(static fn($r) => $r->idasiento, RegularizacionImpuesto::all());
        $this->assertContains($manualEntry->idasiento, $linkedIds);
    }

    public function testCannotLinkNonexistentEntry()
    {
        $exercise = $this->getRandomExercise();

        $reg = new RegularizacionImpuesto();
        $reg->codejercicio = $exercise->codejercicio;
        $reg->periodo = 'T1';
        $this->assertTrue($reg->save());
        $this->addCleanup(static function () use ($reg) {
            if ($reg->exists()) {
                $reg->delete();
            }
        });

        $linker = new VatRegularizationToAccounting();
        $this->assertFalse($linker->linkExisting($reg, 999999999));
        $this->assertEmpty($reg->idasiento);
    }

    public function testCannotLinkEntryAlreadyLinkedToAnotherSettlement()
    {
        $exercise = $this->getRandomExercise();

        $manualEntry = new Asiento();
        $manualEntry->codejercicio = $exercise->codejercicio;
        $manualEntry->idempresa = (int)Tools::settings('default', 'idempresa');
        $manualEntry->concepto = 'Regularización manual de IVA';
        $manualEntry->fecha = date('31-03-Y', strtotime($exercise->fechainicio));
        $this->assertTrue($manualEntry->save());
        $this->addCleanup(static function () use ($manualEntry) {
            if ($manualEntry->exists()) {
                $manualEntry->delete();
            }
        });

        $reg1 = new RegularizacionImpuesto();
        $reg1->codejercicio = $exercise->codejercicio;
        $reg1->periodo = 'T1';
        $this->assertTrue($reg1->save());
        $this->addCleanup(static function () use ($reg1) {
            if ($reg1->exists()) {
                $reg1->delete();
            }
        });

        $reg2 = new RegularizacionImpuesto();
        $reg2->codejercicio = $exercise->codejercicio;
        $reg2->periodo = 'T2';
        $this->assertTrue($reg2->save());
        $this->addCleanup(static function () use ($reg2) {
            if ($reg2->exists()) {
                $reg2->delete();
            }
        });

        $linker = new VatRegularizationToAccounting();
        $this->assertTrue($linker->linkExisting($reg1, $manualEntry->idasiento));

        // el mismo asiento no puede vincularse también a la segunda liquidación
        $this->assertFalse($linker->linkExisting($reg2, $manualEntry->idasiento));
        $this->assertEmpty($reg2->idasiento);
    }
}
