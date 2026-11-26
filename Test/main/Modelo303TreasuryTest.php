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

use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Lib\Modelo303;
use FacturaScripts\Dinamic\Model\Asiento;
use FacturaScripts\Dinamic\Model\Ejercicio;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Dinamic\Model\Partida;
use FacturaScripts\Dinamic\Model\Subcuenta;
use FacturaScripts\Test\Traits\DefaultSettingsTrait;
use FacturaScripts\Test\Traits\LogErrorsTrait;
use FacturaScripts\Test\Traits\RandomDataTrait;

/**
 * Pruebas de Modelo303::treasury(), que usa el plugin Informes para el informe de tesorería:
 * fechas de cada periodo en formato Y-m-d, todos los nombres del periodo anual y que solo suma
 * los asientos del ejercicio pedido, no los de otras empresas en las mismas fechas.
 *
 * @author Daniel Fernández Giménez <contacto@danielfg.es>
 */
final class Modelo303TreasuryTest extends Modelo303TestCase
{
    use DefaultSettingsTrait;
    use LogErrorsTrait;
    use RandomDataTrait;

    /**
     * Prepara la configuración por defecto y el plan contable.
     */
    public static function setUpBeforeClass(): void
    {
        self::setDefaultSettings();
        self::installAccountingPlan();
    }

    /**
     * Fechas de cada periodo y nombres admitidos del periodo anual.
     */
    public function testTreasuryDates(): void
    {
        $exercise = new Ejercicio();
        $exercise->fechainicio = '01-01-2026';

        $this->assertSame(['2026-01-01', '2026-03-31'], TestableModelo303::dates($exercise, 'T1'));
        $this->assertSame(['2026-04-01', '2026-06-30'], TestableModelo303::dates($exercise, 'T2'));
        $this->assertSame(['2026-07-01', '2026-09-30'], TestableModelo303::dates($exercise, 'T3'));
        $this->assertSame(['2026-10-01', '2026-12-31'], TestableModelo303::dates($exercise, 'T4'));
        foreach (Modelo303::ANNUAL_PERIODS as $annual) {
            $this->assertSame(['2026-01-01', '2026-12-31'], TestableModelo303::dates($exercise, $annual), $annual);
        }

        // un periodo desconocido se trata como el primer trimestre
        $this->assertSame(['2026-01-01', '2026-03-31'], TestableModelo303::dates($exercise, 'XX'));
    }

    /**
     * Un asiento de otra empresa con las mismas fechas no cuenta en la tesorería del ejercicio.
     */
    public function testTreasuryOnlyCountsTheExercise(): void
    {
        $exercise = $this->getRandomExercise();
        $date = date('d-m-Y', strtotime($exercise->fechainicio . ' +10 days'));

        $subaccount = new Subcuenta();
        $where = [Where::eq('codejercicio', $exercise->codejercicio), Where::like('codsubcuenta', '477%')];
        $this->assertTrue($subaccount->loadWhere($where), 'no hay subcuenta 477 en el ejercicio');

        $before = Modelo303::treasury($exercise->codejercicio, 'ANNUAL');
        $this->assertNotEmpty($before);
        $beforeT1 = Modelo303::treasury($exercise->codejercicio, 'T1');
        $beforeT2 = Modelo303::treasury($exercise->codejercicio, 'T2');

        // asiento del propio ejercicio, en el primer trimestre: cuenta
        $this->createEntry($exercise, $date, $subaccount, 100.0);

        // asiento de otra empresa en las mismas fechas: no cuenta
        $otherExercise = $this->createOtherCompanyExercise($exercise);
        $this->createEntry($otherExercise, $date, $subaccount, 1000.0);

        foreach (Modelo303::ANNUAL_PERIODS as $annual) {
            $after = Modelo303::treasury($exercise->codejercicio, $annual);
            $this->assertEqualsWithDelta($before['iva-repercutido'] - 100.0, $after['iva-repercutido'], 0.001, $annual);
        }

        // el primer trimestre incluye el asiento y el segundo no cambia
        $afterT1 = Modelo303::treasury($exercise->codejercicio, 'T1');
        $afterT2 = Modelo303::treasury($exercise->codejercicio, 'T2');
        $this->assertEqualsWithDelta($beforeT1['iva-repercutido'] - 100.0, $afterT1['iva-repercutido'], 0.001);
        $this->assertEqualsWithDelta($beforeT2['iva-repercutido'], $afterT2['iva-repercutido'], 0.001);
    }

    /**
     * Crea un asiento con una partida en el haber de la subcuenta indicada y programa su borrado.
     *
     * @param Ejercicio $exercise
     * @param string $date
     * @param Subcuenta $subaccount
     * @param float $amount importe al haber.
     * @return Asiento
     */
    private function createEntry(Ejercicio $exercise, string $date, Subcuenta $subaccount, float $amount): Asiento
    {
        $entry = new Asiento();
        $entry->codejercicio = $exercise->codejercicio;
        $entry->idempresa = $exercise->idempresa;
        $entry->concepto = 'Prueba tesorería';
        $entry->fecha = $date;
        $this->assertTrue($entry->save());
        $this->addCleanup(static function () use ($entry) {
            if ($entry->exists()) {
                $entry->delete();
            }
        });

        $line = new Partida();
        $line->idasiento = $entry->idasiento;
        $line->concepto = $entry->concepto;
        $line->idsubcuenta = $subaccount->idsubcuenta;
        $line->codsubcuenta = $subaccount->codsubcuenta;
        $line->haber = $amount;
        $this->assertTrue($line->save());

        return $entry;
    }

    /**
     * Crea en otra empresa un ejercicio con las mismas fechas y programa su borrado.
     *
     * @param Ejercicio $exercise
     * @return Ejercicio
     */
    private function createOtherCompanyExercise(Ejercicio $exercise): Ejercicio
    {
        $company = new Empresa();
        $company->nombre = 'Empresa tesorería ' . mt_rand(1, 9999);
        $company->nombrecorto = 'Tesorería';
        $company->cifnif = 'B' . mt_rand(10000000, 99999999);
        $this->assertTrue($company->save());

        // al guardar la empresa se crea su ejercicio del año en curso: usamos el de las mismas fechas
        $other = new Ejercicio();
        $where = [Where::eq('idempresa', $company->idempresa), Where::eq('fechainicio', $exercise->fechainicio)];
        if (false === $other->loadWhere($where)) {
            $other->idempresa = $company->idempresa;
            $other->codejercicio = 'T' . mt_rand(100, 999);
            $other->nombre = 'Tesorería ' . $other->codejercicio;
            $other->fechainicio = $exercise->fechainicio;
            $other->fechafin = $exercise->fechafin;
            $this->assertTrue($other->save());
        }

        $this->addCleanup(static function () use ($company, $other) {
            foreach (Asiento::all([Where::eq('codejercicio', $other->codejercicio)], [], 0, 0) as $entry) {
                $entry->delete();
            }
            $other->delete();
            $company->delete();
        });

        return $other;
    }
}

/**
 * Expone el cálculo de fechas de la tesorería, que es protegido.
 */
final class TestableModelo303 extends Modelo303
{
    /**
     * @param Ejercicio $exercise
     * @param string $period
     * @return array{0: string, 1: string}
     */
    public static function dates(Ejercicio $exercise, string $period): array
    {
        return static::treasuryDates($exercise, $period);
    }
}
