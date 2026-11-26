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

use FacturaScripts\Dinamic\Lib\InvoiceOperation;
use FacturaScripts\Plugins\Modelo303\Lib\Modelo390;
use PHPUnit\Framework\TestCase;

/**
 * Pruebas unitarias del cálculo de las casillas del modelo 390: reparto del resumen de IVA por
 * tipo, totales oficiales, resultado de la liquidación anual y resultado de las liquidaciones a
 * partir de las regularizaciones periódicas. No necesita base de datos.
 *
 * @author Daniel Fernández Giménez <contacto@danielfg.es>
 */
final class Modelo390SquaresTest extends TestCase
{
    /**
     * Las casillas de tipos que ya no existen deben valer 0 desde 2025: si tienen importe, se avisa.
     */
    public function testCasillaQueDebeValerCero(): void
    {
        $rows = [$this->row('IVAREP', 5, 100.0, 5.0, '', 'venta')];

        $modelo2024 = $this->modelo($rows, 2024);
        $this->assertEqualsWithDelta(5.0, $modelo2024->casilla('703'), 0.001);
        $this->assertEmpty($modelo2024->getAvisos());

        $modelo2025 = $this->modelo($rows, 2025);
        $this->assertCount(2, $modelo2025->getAvisos(), implode("\n", $modelo2025->getAvisos()));
    }

    /**
     * Las compras exentas, al 0 % y las ventas exentas no entran en el régimen general: van a las
     * operaciones específicas (230) y al volumen de operaciones exentas (105).
     */
    public function testExentasYTipoCero(): void
    {
        $modelo = $this->modelo([
            $this->row('IVAREX', 0, 300.0, 0.0, '', 'venta'),
            $this->row('IVASEX', 0, 200.0, 0.0, '', 'compra'),
            $this->row('IVASOP', 0, 50.0, 0.0, '', 'compra'),
            $this->row('IVAREP', 0, 80.0, 0.0, '', 'venta'),
        ]);

        $this->assertEqualsWithDelta(300.0, $modelo->casilla('105'), 0.001);
        $this->assertEqualsWithDelta(250.0, $modelo->casilla('230'), 0.001);
        $this->assertEqualsWithDelta(80.0, $modelo->casilla('700'), 0.001);
        $this->assertEqualsWithDelta(80.0, $modelo->casilla('99'), 0.001);
        $this->assertEqualsWithDelta(80.0, $modelo->casilla('33'), 0.001);
        $this->assertEqualsWithDelta(0.0, $modelo->casilla('64'), 0.001);
        $this->assertEqualsWithDelta(380.0, $modelo->casilla('108'), 0.001);
        $this->assertEmpty($modelo->getAvisos());
    }

    /**
     * Si las regularizaciones periódicas no cubren los 12 meses, se avisa.
     */
    public function testFaltanRegularizacionesPeriodicas(): void
    {
        $modelo = new Modelo390(2026);
        $modelo->loadFromPeriodos([
            $this->periodo('T1', '2026-01-01', '2026-03-31', 10.0, 0.0),
            $this->periodo('T2', '2026-04-01', '2026-06-30', 10.0, 0.0),
        ]);

        $this->assertCount(1, $modelo->getAvisos());
        $this->assertStringContainsString('7, 8, 9, 10, 11, 12', $modelo->getAvisos()[0]);
    }

    /**
     * Importación: solo cuenta el IVA del DUA (> 0 %), en importaciones de bienes corrientes.
     */
    public function testImportacion(): void
    {
        $modelo = $this->modelo([
            $this->row('IVASOP', 21, 1000.0, 210.0, InvoiceOperation::IMPORT, 'compra'),
            $this->row('IVASOP', 0, 900.0, 0.0, InvoiceOperation::IMPORT, 'compra'),
        ]);

        $this->assertEqualsWithDelta(1000.0, $modelo->casilla('621'), 0.001);
        $this->assertEqualsWithDelta(210.0, $modelo->casilla('622'), 0.001);
        $this->assertEqualsWithDelta(210.0, $modelo->casilla('53'), 0.001);
        $this->assertEqualsWithDelta(210.0, $modelo->casilla('64'), 0.001);
        $this->assertEqualsWithDelta(-210.0, $modelo->casilla('65'), 0.001);
        $this->assertEqualsWithDelta(0.0, $modelo->casilla('230'), 0.001);
    }

    /**
     * Un tipo de IVA sin casilla en el 390 genera un aviso en lugar de perderse sin más.
     */
    public function testImporteSinCasillaGeneraAviso(): void
    {
        $modelo = $this->modelo([$this->row('IVAREP', 15, 100.0, 15.0, '', 'venta')]);
        $this->assertCount(1, $modelo->getAvisos());
        $this->assertEqualsWithDelta(0.0, $modelo->casilla('34'), 0.001);
    }

    /**
     * Adquisiciones intracomunitarias de bienes y servicios e inversión del sujeto pasivo: el
     * devengado y el deducible van a sus casillas y se compensan; las entregas intracomunitarias no
     * aportan partidas (su base va al volumen desde las facturas).
     */
    public function testIntracomunitariasEInversionSujetoPasivo(): void
    {
        $modelo = $this->modelo([
            $this->row('IVARUE', 21, 1000.0, 210.0, InvoiceOperation::INTRA_COMMUNITY, 'compra'),
            $this->row('IVASUE', 21, 1000.0, 210.0, InvoiceOperation::INTRA_COMMUNITY, 'compra'),
            $this->row('IVARUE', 21, 200.0, 42.0, InvoiceOperation::INTRA_COMMUNITY_SERVICES, 'compra'),
            $this->row('IVASUE', 21, 200.0, 42.0, InvoiceOperation::INTRA_COMMUNITY_SERVICES, 'compra'),
            $this->row('IVAREP', 21, 500.0, 105.0, InvoiceOperation::REVERSE_CHARGE, 'compra'),
            $this->row('IVASOP', 21, 500.0, 105.0, InvoiceOperation::REVERSE_CHARGE, 'compra'),
            $this->row('IVARUE', 21, 700.0, 147.0, InvoiceOperation::INTRA_COMMUNITY, 'venta'),
        ]);

        // devengado
        $this->assertEqualsWithDelta(1000.0, $modelo->casilla('25'), 0.001);
        $this->assertEqualsWithDelta(210.0, $modelo->casilla('26'), 0.001);
        $this->assertEqualsWithDelta(200.0, $modelo->casilla('551'), 0.001);
        $this->assertEqualsWithDelta(42.0, $modelo->casilla('552'), 0.001);
        $this->assertEqualsWithDelta(500.0, $modelo->casilla('27'), 0.001);
        $this->assertEqualsWithDelta(105.0, $modelo->casilla('28'), 0.001);
        $this->assertEqualsWithDelta(357.0, $modelo->casilla('47'), 0.001);

        // deducible
        $this->assertEqualsWithDelta(210.0, $modelo->casilla('630'), 0.001);
        $this->assertEqualsWithDelta(210.0, $modelo->casilla('57'), 0.001);
        $this->assertEqualsWithDelta(42.0, $modelo->casilla('638'), 0.001);
        $this->assertEqualsWithDelta(42.0, $modelo->casilla('598'), 0.001);
        $this->assertEqualsWithDelta(105.0, $modelo->casilla('606'), 0.001);
        $this->assertEqualsWithDelta(357.0, $modelo->casilla('64'), 0.001);

        // se compensan y no son volumen de operaciones
        $this->assertEqualsWithDelta(0.0, $modelo->casilla('65'), 0.001);
        $this->assertEqualsWithDelta(0.0, $modelo->casilla('99'), 0.001);
    }

    /**
     * Sin regularizaciones periódicas guardadas, se avisa de que el resultado de las liquidaciones
     * queda a 0.
     */
    public function testSinRegularizacionesPeriodicas(): void
    {
        $modelo = new Modelo390(2026);
        $modelo->loadFromPeriodos([]);
        $modelo->computeTotals();

        $this->assertCount(1, $modelo->getAvisos());
        $this->assertEqualsWithDelta(0.0, $modelo->casilla('95'), 0.001);
        $this->assertEqualsWithDelta(0.0, $modelo->casilla('85'), 0.001);
    }

    /**
     * El pago a cuenta de carburantes (112) resta en el resultado desde 2026 y no existe antes.
     */
    public function testPagoCuentaCarburantes(): void
    {
        $rows = [$this->row('IVAREP', 21, 1000.0, 210.0, '', 'venta')];

        $modelo2026 = $this->modelo($rows, 2026, ['112' => 30.0]);
        $this->assertEqualsWithDelta(210.0, $modelo2026->casilla('84'), 0.001);
        $this->assertEqualsWithDelta(180.0, $modelo2026->casilla('86'), 0.001);

        $modelo2025 = $this->modelo($rows, 2025, ['112' => 30.0]);
        $this->assertEqualsWithDelta(0.0, $modelo2025->casilla('112'), 0.001);
        $this->assertEqualsWithDelta(210.0, $modelo2025->casilla('86'), 0.001);
    }

    /**
     * Recargo de equivalencia: va a sus casillas por tipo de recargo y suma en el total devengado
     * (47), pero no en el total de IVA (34) ni en el volumen de operaciones.
     */
    public function testRecargoEquivalencia(): void
    {
        $modelo = $this->modelo([
            $this->row('IVAREP', 21, 1000.0, 210.0, '', 'venta'),
            $this->row('IVARRE', 21, 1000.0, 52.0, '', 'venta', 5.2),
            $this->row('IVARRE', 10, 100.0, 0.0, '', 'venta', 1.4),
        ]);

        $this->assertEqualsWithDelta(1000.0, $modelo->casilla('601'), 0.001);
        $this->assertEqualsWithDelta(52.0, $modelo->casilla('602'), 0.001);
        // si la cuota es 0, se calcula con el tipo de recargo
        $this->assertEqualsWithDelta(1.4, $modelo->casilla('600'), 0.001);
        $this->assertEqualsWithDelta(210.0, $modelo->casilla('34'), 0.001);
        $this->assertEqualsWithDelta(263.4, $modelo->casilla('47'), 0.001);
        $this->assertEqualsWithDelta(1000.0, $modelo->casilla('99'), 0.001);
    }

    /**
     * Resultado de las liquidaciones: lo ingresado en el año (95), el último periodo a compensar
     * (97), la compensación de cuotas del ejercicio anterior (85) y el resultado anual (86), que
     * debe cuadrar con lo liquidado.
     */
    public function testResultadoLiquidaciones(): void
    {
        // 46 de los periodos: -100, 300, 50 y -30 → resultado del régimen general anual 220
        $modelo = new Modelo390(2026);
        $modelo->loadFromResumen([$this->row('IVAREP', 21, 1000.0, 220.0, '', 'venta')]);
        $modelo->loadFromPeriodos([
            // empieza el año con 50 pendientes del ejercicio anterior
            $this->periodo('T1', '2026-01-01', '2026-03-31', -100.0, 50.0, 0.0, 50.0, 'C'),
            // compensa 150: los 50 del año anterior y los 100 del T1
            $this->periodo('T2', '2026-04-01', '2026-06-30', 150.0, 0.0, 150.0, 150.0, 'I'),
            $this->periodo('T3', '2026-07-01', '2026-09-30', 50.0, 0.0),
            $this->periodo('T4', '2026-10-01', '2026-12-31', -30.0, 0.0, 0.0, 0.0, 'C'),
        ]);
        $modelo->computeTotals();

        $this->assertEqualsWithDelta(220.0, $modelo->casilla('65'), 0.001);
        $this->assertEqualsWithDelta(220.0, $modelo->casilla('84'), 0.001);
        $this->assertEqualsWithDelta(50.0, $modelo->casilla('85'), 0.001);
        $this->assertEqualsWithDelta(170.0, $modelo->casilla('86'), 0.001);
        $this->assertEqualsWithDelta(200.0, $modelo->casilla('95'), 0.001);
        $this->assertEqualsWithDelta(30.0, $modelo->casilla('97'), 0.001);
        $this->assertEqualsWithDelta(0.0, $modelo->casilla('98'), 0.001);
        $this->assertEqualsWithDelta(0.0, $modelo->casilla('662'), 0.001);
        $this->assertEmpty($modelo->getAvisos(), implode("\n", $modelo->getAvisos()));
        $this->assertCount(4, $modelo->getPeriodos());
    }

    /**
     * Cuotas a compensar generadas en un periodo distinto del último y no aplicadas (662), y último
     * periodo a devolver (98).
     */
    public function testResultadoLiquidacionesPendientesYDevolucion(): void
    {
        // 46 de los periodos: 100, 100, -40 y -10 → 150
        $modelo = new Modelo390(2025);
        $modelo->loadFromResumen([$this->row('IVAREP', 21, 1000.0, 150.0, '', 'venta')]);
        $modelo->loadFromPeriodos([
            $this->periodo('T1', '2025-01-01', '2025-03-31', 100.0, 0.0),
            $this->periodo('T2', '2025-04-01', '2025-06-30', 100.0, 0.0),
            $this->periodo('T3', '2025-07-01', '2025-09-30', -40.0, 0.0, 0.0, 0.0, 'C'),
            // los 40 del T3 siguen pendientes al acabar el año
            $this->periodo('T4', '2025-10-01', '2025-12-31', -10.0, 40.0, 0.0, 40.0, 'D'),
        ]);
        $modelo->computeTotals();

        $this->assertEqualsWithDelta(150.0, $modelo->casilla('86'), 0.001);
        $this->assertEqualsWithDelta(200.0, $modelo->casilla('95'), 0.001);
        $this->assertEqualsWithDelta(0.0, $modelo->casilla('97'), 0.001);
        $this->assertEqualsWithDelta(10.0, $modelo->casilla('98'), 0.001);
        $this->assertEqualsWithDelta(40.0, $modelo->casilla('662'), 0.001);
        $this->assertEmpty($modelo->getAvisos(), implode("\n", $modelo->getAvisos()));
    }

    /**
     * Si lo liquidado en los periodos no cuadra con el resultado anual, se avisa.
     */
    public function testResultadoNoCuadraGeneraAviso(): void
    {
        $modelo = new Modelo390(2026);
        $modelo->loadFromResumen([$this->row('IVAREP', 21, 1000.0, 210.0, '', 'venta')]);
        $modelo->loadFromPeriodos([
            $this->periodo('Y1', '2026-01-01', '2026-12-31', 100.0, 0.0),
        ]);
        $modelo->computeTotals();

        $this->assertCount(1, $modelo->getAvisos());
    }

    /**
     * Ventas y compras nacionales en régimen general: casillas por tipo, totales, resultado,
     * volumen de operaciones y filas visibles de la pantalla.
     */
    public function testVentasYComprasNacionales(): void
    {
        $modelo = $this->modelo([
            $this->row('IVAREP', 21, 1000.0, 210.0, '', 'venta'),
            $this->row('IVAREP', 10, 500.0, 50.0, '', 'venta'),
            $this->row('IVAREP', 4, 100.0, 4.0, '', 'venta'),
            $this->row('IVASOP', 21, 400.0, 84.0, '', 'compra'),
            $this->row('IVASOP', 10, 100.0, 10.0, '', 'compra'),
        ]);

        $this->assertEqualsWithDelta(1000.0, $modelo->casilla('05'), 0.001);
        $this->assertEqualsWithDelta(210.0, $modelo->casilla('06'), 0.001);
        $this->assertEqualsWithDelta(500.0, $modelo->casilla('03'), 0.001);
        $this->assertEqualsWithDelta(50.0, $modelo->casilla('04'), 0.001);
        $this->assertEqualsWithDelta(100.0, $modelo->casilla('01'), 0.001);
        $this->assertEqualsWithDelta(4.0, $modelo->casilla('02'), 0.001);
        $this->assertEqualsWithDelta(1600.0, $modelo->casilla('33'), 0.001);
        $this->assertEqualsWithDelta(264.0, $modelo->casilla('34'), 0.001);
        $this->assertEqualsWithDelta(264.0, $modelo->casilla('47'), 0.001);

        $this->assertEqualsWithDelta(400.0, $modelo->casilla('605'), 0.001);
        $this->assertEqualsWithDelta(84.0, $modelo->casilla('606'), 0.001);
        $this->assertEqualsWithDelta(10.0, $modelo->casilla('604'), 0.001);
        $this->assertEqualsWithDelta(500.0, $modelo->casilla('48'), 0.001);
        $this->assertEqualsWithDelta(94.0, $modelo->casilla('49'), 0.001);
        $this->assertEqualsWithDelta(94.0, $modelo->casilla('64'), 0.001);
        $this->assertEqualsWithDelta(170.0, $modelo->casilla('65'), 0.001);
        $this->assertEqualsWithDelta(170.0, $modelo->casilla('86'), 0.001);

        // volumen de operaciones: bases de las ventas
        $this->assertEqualsWithDelta(1600.0, $modelo->casilla('99'), 0.001);
        $this->assertEqualsWithDelta(1600.0, $modelo->casilla('108'), 0.001);

        $this->assertEmpty($modelo->getAvisos());

        // filas visibles: las de siempre y las que tienen importe
        $sections = $modelo->getSections();
        $boxes = array_column($sections['model390-section-deductible'], 'cuota');
        $this->assertContains('606', $boxes);
        $this->assertContains('604', $boxes);
        $this->assertNotContains('191', $boxes);
        $this->assertContains('64', $boxes);
        $this->assertArrayNotHasKey('model390-section-specific', $sections);
    }

    /**
     * Crea un modelo 390 a partir de filas del resumen, sin cargar regularizaciones periódicas.
     *
     * @param object[] $rows
     * @param int $year
     * @param array<string, float> $manual casillas manuales
     * @return Modelo390
     */
    private function modelo(array $rows, int $year = 2026, array $manual = []): Modelo390
    {
        $modelo = new Modelo390($year);
        $modelo->loadFromResumen($rows);
        foreach ($manual as $box => $value) {
            $modelo->setCasilla((string)$box, $value);
        }
        $modelo->computeTotals();
        return $modelo;
    }

    /**
     * Crea los datos de una regularización periódica como los de Modelo390::periodicResults().
     *
     * @param string $periodo
     * @param string $inicio
     * @param string $fin
     * @param float $resultado casilla 71
     * @param float $pendiente casilla 87
     * @param float $c78
     * @param float $c110
     * @param string $tipo
     * @return array
     */
    private function periodo(string $periodo, string $inicio, string $fin, float $resultado, float $pendiente,
                             float $c78 = 0.0, float $c110 = 0.0, string $tipo = 'I'): array
    {
        return [
            'idregiva' => 0,
            'periodo' => $periodo,
            'fechainicio' => $inicio,
            'fechafin' => $fin,
            'resultado' => $resultado,
            'pendiente' => $pendiente,
            'c76' => 0.0,
            'c77' => 0.0,
            'c78' => $c78,
            'c110' => $c110,
            'tipo' => $tipo,
        ];
    }

    /**
     * Crea una fila equivalente a una de PartidaImpuestoResumen.
     */
    private function row(
        string $codcuentaesp,
        float  $iva,
        float  $base,
        float  $cuota,
        string $operacion = '',
        string $tipodoc = '',
        float  $recargo = 0.0
    ): object {
        return (object)[
            'codcuentaesp' => $codcuentaesp,
            'operacion' => $operacion,
            'tipodoc' => $tipodoc,
            'iva' => $iva,
            'recargo' => $recargo,
            'baseimponible' => $base,
            'cuota' => $cuota,
        ];
    }
}
