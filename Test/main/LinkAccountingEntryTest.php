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

use FacturaScripts\Core\Base\MiniLog;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Model\Asiento;
use FacturaScripts\Dinamic\Model\Ejercicio;
use FacturaScripts\Dinamic\Model\RegularizacionImpuesto;
use FacturaScripts\Plugins\Modelo303\Controller\EditRegularizacionImpuesto;
use FacturaScripts\Test\Traits\DefaultSettingsTrait;
use FacturaScripts\Test\Traits\LogErrorsTrait;
use FacturaScripts\Test\Traits\RandomDataTrait;

/**
 * Estos tests cubren el bug original: cuando el usuario creaba a mano el asiento de
 * regularización de IVA (en lugar de usar el asistente "Crear asiento contable"), el asiento
 * no se reconocía como asiento de regularización y seguía contando en las liquidaciones
 * posteriores. Ahora la pestaña Asiento permite vincular un asiento existente y desvincularlo,
 * de forma que queda registrado igual que uno generado automáticamente y se excluye después.
 *
 * También cubren el filtro de asientos candidatos que alimenta el autocomplete del modal.
 */
final class LinkAccountingEntryTest extends Modelo303TestCase
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

    public function testAvailableAccEntries(): void
    {
        $exercise = $this->getRandomExercise();
        $reg = $this->createRegularization($exercise, 'T1');

        // candidatos: sin operación y con fecha igual o posterior al fin del periodo
        $onEndDate = $this->createAccEntry($exercise, $reg->fechafin);
        $afterEndDate = $this->createAccEntry($exercise, date('30-06-Y', strtotime($exercise->fechainicio)));

        // no candidatos: fecha anterior al fin del periodo, u operación especial
        $beforeEndDate = $this->createAccEntry($exercise, date('15-02-Y', strtotime($exercise->fechainicio)));
        $withOperation = $this->createAccEntry($exercise, $reg->fechafin, Asiento::OPERATION_REGULARIZATION);
        $opening = $this->createAccEntry($exercise, $reg->fechafin, Asiento::OPERATION_OPENING);
        $closing = $this->createAccEntry($exercise, $reg->fechafin, Asiento::OPERATION_CLOSING);

        // no candidato: ya vinculado a otra regularización
        $reg2 = $this->createRegularization($exercise, 'T2');
        $linkedToOther = $this->createAccEntry($exercise, $reg2->fechafin);
        $reg2->idasiento = $linkedToOther->idasiento;
        $this->assertTrue($reg2->save());

        $controller = $this->getController();
        $found = [];
        foreach (Asiento::all($controller->availableAccEntriesWhere($reg), [], 0, 0) as $item) {
            $found[] = $item->idasiento;
        }

        $this->assertContains($onEndDate->idasiento, $found);
        $this->assertContains($afterEndDate->idasiento, $found);
        $this->assertNotContains($beforeEndDate->idasiento, $found);
        $this->assertNotContains($withOperation->idasiento, $found);
        $this->assertNotContains($opening->idasiento, $found);
        $this->assertNotContains($closing->idasiento, $found);
        $this->assertNotContains($linkedToOther->idasiento, $found);

        // el asiento de la propia regularización no se autoexcluye
        $this->assertTrue($controller->linkAccountingEntry($reg, $onEndDate->idasiento));
        $found = [];
        foreach (Asiento::all($controller->availableAccEntriesWhere($reg), [], 0, 0) as $item) {
            $found[] = $item->idasiento;
        }
        $this->assertContains($onEndDate->idasiento, $found);
    }

    public function testAvailableAccEntriesIgnoresExercise(): void
    {
        $exercise = $this->getRandomExercise();
        $reg = $this->createRegularization($exercise, 'T1');

        // el filtro se hace por empresa, fecha y operación; el ejercicio es indiferente
        $sql = Where::multiSql($this->getController()->availableAccEntriesWhere($reg));
        $this->assertStringContainsString('idempresa', $sql);
        $this->assertStringContainsString('fecha', $sql);
        $this->assertStringContainsString('operacion', $sql);
        $this->assertStringNotContainsString('codejercicio', $sql);
    }

    public function testCannotLinkEntryAlreadyLinkedToAnotherSettlement(): void
    {
        $exercise = $this->getRandomExercise();
        $reg1 = $this->createRegularization($exercise, 'T1');
        $reg2 = $this->createRegularization($exercise, 'T2');
        $manualEntry = $this->createAccEntry($exercise, $reg2->fechafin);

        $controller = $this->getController();
        $this->assertTrue($controller->linkAccountingEntry($reg1, $manualEntry->idasiento));

        // el mismo asiento no puede vincularse también a la segunda liquidación
        MiniLog::clear();
        $this->assertFalse($controller->linkAccountingEntry($reg2, $manualEntry->idasiento));
        $this->assertEmpty($reg2->idasiento);
        $this->assertTrue($this->hasWarning('accounting-entry-already-linked'));
    }

    public function testCannotLinkEntryBeforeEndDate(): void
    {
        $exercise = $this->getRandomExercise();
        $reg = $this->createRegularization($exercise, 'T1');

        // un asiento con fecha anterior al fin del periodo no se puede vincular
        $entry = $this->createAccEntry($exercise, date('15-02-Y', strtotime($exercise->fechainicio)));

        $controller = $this->getController();
        MiniLog::clear();
        $this->assertFalse($controller->linkAccountingEntry($reg, $entry->idasiento));
        $this->assertEmpty($reg->idasiento);
        $this->assertTrue($this->hasWarning('accounting-entry-before-end-date'));
    }

    public function testCannotLinkEntryWithOperation(): void
    {
        $exercise = $this->getRandomExercise();
        $reg = $this->createRegularization($exercise, 'T1');

        // los asientos con operación especial no son candidatos
        $entry = $this->createAccEntry($exercise, $reg->fechafin, Asiento::OPERATION_REGULARIZATION);

        $controller = $this->getController();
        MiniLog::clear();
        $this->assertFalse($controller->linkAccountingEntry($reg, $entry->idasiento));
        $this->assertEmpty($reg->idasiento);
        $this->assertTrue($this->hasWarning('accounting-entry-with-operation'));
    }

    public function testCannotLinkNonexistentEntry(): void
    {
        $exercise = $this->getRandomExercise();
        $reg = $this->createRegularization($exercise, 'T1');

        $controller = $this->getController();
        MiniLog::clear();
        $this->assertFalse($controller->linkAccountingEntry($reg, 999999999));
        $this->assertEmpty($reg->idasiento);
        $this->assertTrue($this->hasWarning('accounting-entry-invalid'));
    }

    public function testCannotLinkWhenAlreadyHasEntry(): void
    {
        $exercise = $this->getRandomExercise();
        $reg = $this->createRegularization($exercise, 'T1');
        $first = $this->createAccEntry($exercise, $reg->fechafin);
        $second = $this->createAccEntry($exercise, $reg->fechafin);

        $controller = $this->getController();
        $this->assertTrue($controller->linkAccountingEntry($reg, $first->idasiento));

        // ya tiene asiento, no se puede vincular otro sin desvincular antes
        MiniLog::clear();
        $this->assertFalse($controller->linkAccountingEntry($reg, $second->idasiento));
        $this->assertEquals($first->idasiento, $reg->idasiento);
        $this->assertTrue($this->hasWarning('accounting-entry-already-created'));
    }

    public function testLinkDoesNotModifyEntry(): void
    {
        $exercise = $this->getRandomExercise();
        $reg = $this->createRegularization($exercise, 'T1');
        $manualEntry = $this->createAccEntry($exercise, $reg->fechafin);

        $controller = $this->getController();
        $this->assertTrue($controller->linkAccountingEntry($reg, $manualEntry->idasiento));

        // al vincular no se toca el asiento: ni su operación, ni la fecha ni el concepto
        $reloaded = new Asiento();
        $this->assertTrue($reloaded->load($manualEntry->idasiento));
        $this->assertEmpty($reloaded->operacion);
        $this->assertEquals($manualEntry->fecha, $reloaded->fecha);
        $this->assertEquals($manualEntry->concepto, $reloaded->concepto);
    }

    public function testLinkManualEntry(): void
    {
        $exercise = $this->getRandomExercise();
        $reg = $this->createRegularization($exercise, 'T1');

        // creamos a mano un asiento de regularización, sin pasar por el asistente del plugin
        $manualEntry = $this->createAccEntry($exercise, $reg->fechafin);

        // antes de vincularlo, el asiento manual no aparece entre las regularizaciones registradas
        $this->assertNotContains($manualEntry->idasiento, $this->linkedAccEntryIds());

        // lo vinculamos a la regularización, en lugar de generar un asiento nuevo
        $controller = $this->getController();
        $this->assertTrue($controller->linkAccountingEntry($reg, $manualEntry->idasiento));

        // se ha completado la fecha del asiento y se ha bloqueado la regularización
        $this->assertEquals($manualEntry->idasiento, $reg->idasiento);
        $this->assertEquals($manualEntry->fecha, $reg->fechaasiento);
        $this->assertTrue($reg->bloquear);

        // ahora sí, el asiento manual queda registrado como asiento de una regularización,
        // que es justo el criterio que usa el plugin para excluirlo de los cálculos
        // posteriores (ver commonTaxWhere() y getSubtotals())
        $this->assertContains($manualEntry->idasiento, $this->linkedAccEntryIds());
    }

    public function testUnlinkKeepsAccountingEntry(): void
    {
        $exercise = $this->getRandomExercise();
        $reg = $this->createRegularization($exercise, 'T1');
        $manualEntry = $this->createAccEntry($exercise, $reg->fechafin);

        $controller = $this->getController();
        $this->assertTrue($controller->linkAccountingEntry($reg, $manualEntry->idasiento));
        $this->assertTrue($controller->unlinkAccountingEntry($reg));

        // se han limpiado los campos derivados y se ha desbloqueado la liquidación
        $this->assertEmpty($reg->idasiento);
        $this->assertEmpty($reg->fechaasiento);
        $this->assertFalse((bool)$reg->bloquear);

        // y se ha guardado, no solo modificado en memoria
        $reloadedReg = new RegularizacionImpuesto();
        $this->assertTrue($reloadedReg->load($reg->idregiva));
        $this->assertEmpty($reloadedReg->idasiento);
        $this->assertEmpty($reloadedReg->fechaasiento);
        $this->assertFalse((bool)$reloadedReg->bloquear);

        // el asiento sigue existiendo y sin cambios, solo se ha deshecho el vínculo
        $reloadedEntry = new Asiento();
        $this->assertTrue($reloadedEntry->load($manualEntry->idasiento));
        $this->assertEmpty($reloadedEntry->operacion);
        $this->assertEquals($manualEntry->fecha, $reloadedEntry->fecha);

        // y vuelve a contar en los cálculos del periodo
        $this->assertNotContains($manualEntry->idasiento, $this->linkedAccEntryIds());
    }

    public function testUnlinkWithoutEntryFails(): void
    {
        $exercise = $this->getRandomExercise();
        $reg = $this->createRegularization($exercise, 'T1');

        $controller = $this->getController();
        MiniLog::clear();
        $this->assertFalse($controller->unlinkAccountingEntry($reg));
        $this->assertTrue($this->hasWarning('accounting-entry-not-linked'));
    }

    /**
     * Crea un asiento y programa su borrado al terminar el test.
     *
     * @param Ejercicio $exercise
     * @param string $fecha
     * @param string|null $operacion
     * @return Asiento
     */
    private function createAccEntry(Ejercicio $exercise, string $fecha, ?string $operacion = null): Asiento
    {
        $entry = new Asiento();
        $entry->codejercicio = $exercise->codejercicio;
        $entry->idempresa = (int)Tools::settings('default', 'idempresa');
        $entry->concepto = 'Regularización manual de IVA';
        $entry->fecha = $fecha;
        $entry->operacion = $operacion;
        $this->assertTrue($entry->save());
        $this->addCleanup(static function () use ($entry) {
            if ($entry->exists()) {
                $entry->delete();
            }
        });

        return $entry;
    }

    /**
     * Crea una liquidación y programa su borrado al terminar el test.
     *
     * @param Ejercicio $exercise
     * @param string $periodo
     * @return RegularizacionImpuesto
     */
    private function createRegularization(Ejercicio $exercise, string $periodo): RegularizacionImpuesto
    {
        $reg = new RegularizacionImpuesto();
        $reg->codejercicio = $exercise->codejercicio;
        $reg->periodo = $periodo;
        $this->assertTrue($reg->save());
        $this->addCleanup(static function () use ($reg) {
            if ($reg->exists()) {
                $reg->delete();
            }
        });

        return $reg;
    }

    private function getController(): TestableEditRegularizacionImpuesto
    {
        return new TestableEditRegularizacionImpuesto(
            'EditRegularizacionImpuesto',
            '/EditRegularizacionImpuesto'
        );
    }

    /**
     * Indica si el log contiene el aviso indicado.
     *
     * @param string $message
     * @return bool
     */
    private function hasWarning(string $message): bool
    {
        foreach (MiniLog::read('', ['warning']) as $item) {
            if ($item['original'] === $message) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ids de los asientos vinculados a alguna regularización, que es el criterio que usa
     * el plugin para excluirlos de los cálculos.
     *
     * @return array
     */
    private function linkedAccEntryIds(): array
    {
        $ids = [];
        foreach (RegularizacionImpuesto::all() as $reg) {
            $ids[] = $reg->idasiento;
        }

        return $ids;
    }
}

/**
 * Expone como públicos los métodos protegidos del controlador que implementan la
 * vinculación de asientos, para poder testearlos sin pasar por una petición http.
 */
final class TestableEditRegularizacionImpuesto extends EditRegularizacionImpuesto
{
    public function availableAccEntriesWhere($reg): array
    {
        return parent::availableAccEntriesWhere($reg);
    }

    public function linkAccountingEntry(&$reg, int $idasiento): bool
    {
        return parent::linkAccountingEntry($reg, $idasiento);
    }

    public function unlinkAccountingEntry(&$reg): bool
    {
        return parent::unlinkAccountingEntry($reg);
    }
}
