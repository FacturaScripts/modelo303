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
use FacturaScripts\Plugins\Modelo303\Lib\Modelo303;
use PHPUnit\Framework\TestCase;

/**
 * Pruebas unitarias del reparto de importes a casillas del modelo 303.
 * No necesita base de datos: alimenta Modelo303 con filas simuladas del resumen.
 */
final class Modelo303SquaresTest extends TestCase
{
    public function testCompraImportacion(): void
    {
        $modelo = new Modelo303();
        $modelo->loadFromResumen([
            $this->row('IVASIM', 21, 5000.0, 1050.0, InvoiceOperation::IMPORT, 'compra'),
        ]);

        $this->assertEqualsWithDelta(5000.0, $modelo->casilla('32'), 0.001);
        $this->assertEqualsWithDelta(1050.0, $modelo->casilla('33'), 0.001);
    }

    public function testCompraIntracomunitaria(): void
    {
        $modelo = new Modelo303();
        // intracomunitaria con cuentas específicas IVARUE/IVASUE
        $modelo->loadFromResumen([
            $this->row('IVARUE', 21, 4000.0, 840.0, InvoiceOperation::INTRA_COMMUNITY, 'compra'),
            $this->row('IVASUE', 21, 4000.0, 840.0, InvoiceOperation::INTRA_COMMUNITY, 'compra'),
        ]);

        // devengado -> 10/11
        $this->assertEqualsWithDelta(4000.0, $modelo->casilla('10'), 0.001);
        $this->assertEqualsWithDelta(840.0, $modelo->casilla('11'), 0.001);
        // deducible -> 36/37
        $this->assertEqualsWithDelta(4000.0, $modelo->casilla('36'), 0.001);
        $this->assertEqualsWithDelta(840.0, $modelo->casilla('37'), 0.001);
        $this->assertEqualsWithDelta(0.0, $modelo->casilla('46'), 0.001);
    }

    public function testCompraIntracomunitariaConCuentasGenericas(): void
    {
        $modelo = new Modelo303();
        // intracomunitaria pero contabilizada en IVAREP/IVASOP genéricos:
        // el tipo de operación debe forzar igualmente 10/11 y 36/37
        $modelo->loadFromResumen([
            $this->row('IVAREP', 21, 1000.0, 210.0, InvoiceOperation::INTRA_COMMUNITY_SERVICES, 'compra'),
            $this->row('IVASOP', 21, 1000.0, 210.0, InvoiceOperation::INTRA_COMMUNITY_SERVICES, 'compra'),
        ]);

        $this->assertEqualsWithDelta(1000.0, $modelo->casilla('10'), 0.001);
        $this->assertEqualsWithDelta(210.0, $modelo->casilla('11'), 0.001);
        $this->assertEqualsWithDelta(1000.0, $modelo->casilla('36'), 0.001);
        $this->assertEqualsWithDelta(210.0, $modelo->casilla('37'), 0.001);
        // no debe ir a las casillas nacionales (07/09) ni a las de ISP (12/13)
        $this->assertEqualsWithDelta(0.0, $modelo->casilla('09'), 0.001);
        $this->assertEqualsWithDelta(0.0, $modelo->casilla('13'), 0.001);
    }

    public function testCompraInversionSujetoPasivo(): void
    {
        $modelo = new Modelo303();
        // autorrepercusión: IVAREP (devengado) + IVASOP (deducible), mismo importe
        $modelo->loadFromResumen([
            $this->row('IVAREP', 21, 3000.0, 630.0, InvoiceOperation::REVERSE_CHARGE, 'compra'),
            $this->row('IVASOP', 21, 3000.0, 630.0, InvoiceOperation::REVERSE_CHARGE, 'compra'),
        ]);

        // devengado -> 12/13
        $this->assertEqualsWithDelta(3000.0, $modelo->casilla('12'), 0.001);
        $this->assertEqualsWithDelta(630.0, $modelo->casilla('13'), 0.001);
        // deducible -> 28/29
        $this->assertEqualsWithDelta(3000.0, $modelo->casilla('28'), 0.001);
        $this->assertEqualsWithDelta(630.0, $modelo->casilla('29'), 0.001);

        // el IVA se compensa: resultado del régimen general (46) = 0
        $this->assertEqualsWithDelta(0.0, $modelo->casilla('46'), 0.001);
        // y NO debe contaminar las casillas de ventas nacionales (07/09)
        $this->assertEqualsWithDelta(0.0, $modelo->casilla('09'), 0.001);
    }

    public function testCompraNacionalDeducible(): void
    {
        $modelo = new Modelo303();
        $modelo->loadFromResumen([
            $this->row('IVASOP', 21, 2000.0, 420.0, '', 'compra'),
            // tipo no contemplado en el mapa antiguo (5%): debe sumarse igual via comodín
            $this->row('IVASOP', 5, 100.0, 5.0, '', 'compra'),
        ]);

        $this->assertEqualsWithDelta(2100.0, $modelo->casilla('28'), 0.001);
        $this->assertEqualsWithDelta(425.0, $modelo->casilla('29'), 0.001);
        $this->assertEqualsWithDelta(425.0, $modelo->casilla('45'), 0.001);
        $this->assertEmpty($modelo->getAvisos());
    }

    public function testImporteSinCasillaGeneraAviso(): void
    {
        $modelo = new Modelo303();
        // cuenta especial desconocida con importe: debe generar aviso y no perderse en silencio
        $modelo->loadFromResumen([
            $this->row('IVAXXX', 21, 1000.0, 210.0, '', 'venta'),
        ]);

        $this->assertNotEmpty($modelo->getAvisos());
    }

    public function testRecargoEquivalencia(): void
    {
        $modelo = new Modelo303();
        $modelo->loadFromResumen([
            $this->row('IVARRE', 21, 1000.0, 52.0, '', 'venta', 5.2),
        ]);

        // recargo 5.2 -> base 22 / cuota 24
        $this->assertEqualsWithDelta(1000.0, $modelo->casilla('22'), 0.001);
        $this->assertEqualsWithDelta(52.0, $modelo->casilla('24'), 0.001);
    }

    public function testRecargoEquivalenciaEnCuentaIVAREP(): void
    {
        // Cuando no hay subcuenta IVARRE configurada, el núcleo contabiliza el recargo en la
        // cuenta de IVA repercutido (IVAREP), con iva = 0 y recargo > 0. Una venta al 21% con
        // recargo 5.2 genera dos partidas sobre IVAREP: la cuota de IVA y la del recargo.
        $modelo = new Modelo303();
        $modelo->loadFromResumen([
            $this->row('IVAREP', 21, 1000.0, 210.0, '', 'venta'),
            $this->row('IVAREP', 0, 1000.0, 52.0, '', 'venta', 5.2),
        ]);

        // el IVA sigue yendo al régimen general 21% -> base 07 / cuota 09
        $this->assertEqualsWithDelta(1000.0, $modelo->casilla('07'), 0.001);
        $this->assertEqualsWithDelta(210.0, $modelo->casilla('09'), 0.001);
        // el recargo 5.2 -> base 22 / cuota 24, aunque esté en la cuenta IVAREP
        $this->assertEqualsWithDelta(1000.0, $modelo->casilla('22'), 0.001);
        $this->assertEqualsWithDelta(52.0, $modelo->casilla('24'), 0.001);
        // y no debe quedar ningún importe sin casilla
        $this->assertEmpty($modelo->getAvisos());
    }

    public function testVentaIntracomunitariaNoContaminaRegimenGeneral(): void
    {
        $modelo = new Modelo303();
        // una venta intracomunitaria genera IVARUE/IVASUE (neto cero); NO debe sumar en 10/11/36/37
        $modelo->loadFromResumen([
            $this->row('IVARUE', 21, 7000.0, 1470.0, InvoiceOperation::INTRA_COMMUNITY, 'venta'),
            $this->row('IVASUE', 21, 7000.0, 1470.0, InvoiceOperation::INTRA_COMMUNITY, 'venta'),
        ]);

        $this->assertEqualsWithDelta(0.0, $modelo->casilla('10'), 0.001);
        $this->assertEqualsWithDelta(0.0, $modelo->casilla('11'), 0.001);
        $this->assertEqualsWithDelta(0.0, $modelo->casilla('36'), 0.001);
        $this->assertEqualsWithDelta(0.0, $modelo->casilla('37'), 0.001);
        $this->assertEqualsWithDelta(0.0, $modelo->casilla('46'), 0.001);
    }

    public function testVentaNacionalRegimenGeneral(): void
    {
        $modelo = new Modelo303();
        $modelo->loadFromResumen([
            $this->row('IVAREP', 21, 1000.0, 210.0),
            $this->row('IVAREP', 10, 500.0, 50.0),
            $this->row('IVAREP', 4, 100.0, 4.0),
        ]);

        // 21% -> base 07 / cuota 09
        $this->assertEqualsWithDelta(1000.0, $modelo->casilla('07'), 0.001);
        $this->assertEqualsWithDelta(210.0, $modelo->casilla('09'), 0.001);
        // 10% -> base 04 / cuota 06
        $this->assertEqualsWithDelta(500.0, $modelo->casilla('04'), 0.001);
        $this->assertEqualsWithDelta(50.0, $modelo->casilla('06'), 0.001);
        // 4% -> base 01 / cuota 03
        $this->assertEqualsWithDelta(100.0, $modelo->casilla('01'), 0.001);
        $this->assertEqualsWithDelta(4.0, $modelo->casilla('03'), 0.001);

        // total devengado (casilla 27) = 210 + 50 + 4
        $this->assertEqualsWithDelta(264.0, $modelo->casilla('27'), 0.001);
        $this->assertEmpty($modelo->getAvisos());
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
