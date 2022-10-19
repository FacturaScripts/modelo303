<?php
/**
 * This file is part of Modelo303 plugin for FacturaScripts
 * Copyright (C) 2019-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
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

use FacturaScripts\Dinamic\Model\RegularizacionImpuesto;
use FacturaScripts\Plugins\Modelo303\Lib\Accounting\VatRegularizationToAccounting;
use FacturaScripts\Test\Traits\DefaultSettingsTrait;
use FacturaScripts\Test\Traits\LogErrorsTrait;
use FacturaScripts\Test\Traits\RandomDataTrait;
use PHPUnit\Framework\TestCase;

final class VatRegularizationToAccountingTest extends TestCase
{
    use DefaultSettingsTrait;
    use LogErrorsTrait;
    use RandomDataTrait;

    public static function setUpBeforeClass(): void
    {
        self::setDefaultSettings();
        self::installAccountingPlan();
    }

    public function testCreate()
    {
        // creamos una factura de proveedor
        $supplierInvoice = $this->getRandomSupplierInvoice('10-01-2022');
        $this->assertTrue($supplierInvoice->save());

        // creamos dos facturas de cliente
        $customerInvoice1 = $this->getRandomCustomerInvoice('11-01-2022');
        $this->assertTrue($customerInvoice1->save());

        $customerInvoice2 = $this->getRandomCustomerInvoice('12-01-2022');
        $this->assertTrue($customerInvoice2->save());

        // creamos una regularización
        $reg = new RegularizacionImpuesto();
        $reg->codejercicio = $supplierInvoice->codejercicio;
        $reg->periodo = 'T1';
        $this->assertTrue($reg->save());

        // generamos el asiento contable
        $generator = new VatRegularizationToAccounting();
        $this->assertTrue($generator->generate($reg));

        // comprobamos que se ha creado el asiento contable
        $this->assertNotEmpty($reg->idasiento);

        // eliminamos la regularización
        $this->assertTrue($reg->delete());

        // comprobamos que se ha eliminado el asiento contable
        $this->assertFalse($reg->getAccountingEntry()->exists());

        $this->assertTrue($supplierInvoice->delete());
        $this->assertTrue($supplierInvoice->getSubject()->delete());
        $this->assertTrue($customerInvoice1->delete());
        $this->assertTrue($customerInvoice2->delete());
        $this->assertTrue($customerInvoice1->getSubject()->delete());
        $this->assertTrue($customerInvoice2->getSubject()->delete());
    }

    protected function tearDown(): void
    {
        $this->logErrors();
    }
}
