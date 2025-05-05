<?php

namespace FacturaScripts\Test\Plugins;

use FacturaScripts\Core\Model\User;
use FacturaScripts\Test\Traits\DefaultSettingsTrait;
use FacturaScripts\Test\Traits\LogErrorsTrait;
use PHPUnit\Framework\TestCase;

class Modelo303TestCase extends TestCase
{
    use DefaultSettingsTrait;
    use LogErrorsTrait;

    public function setUp(): void
    {
        // inicializamos los modelos para que se creen las
        // tablas necesarias y no de error de Foreign Key
        new User();
        foreach (['Factura', 'Albaran', 'Presupuesto', 'Pedido'] as $doc) {
            foreach (['Cliente', 'Proveedor'] as $subject) {
                $className = '\\FacturaScripts\\Dinamic\\Model\\' . $doc . $subject;
                new $className();
            }
        }

        self::setDefaultSettings();
        self::installAccountingPlan();
    }

    protected function tearDown(): void
    {
        $this->logErrors();
    }
}
