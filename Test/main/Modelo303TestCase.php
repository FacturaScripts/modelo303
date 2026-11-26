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

    /**
     * Cleanup callbacks to run after each test in reverse order.
     *
     * @var array<int, callable>
     */
    private array $cleanupCallbacks = [];

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

        // también las tablas de líneas, recibos y pagos: si se crean por primera vez dentro de la
        // transacción de guardar o borrar una factura, la operación falla y deja la transacción
        // abierta para los tests siguientes
        foreach (['LineaFactura', 'Recibo', 'Pago'] as $model) {
            foreach (['Cliente', 'Proveedor'] as $subject) {
                $className = '\\FacturaScripts\\Dinamic\\Model\\' . $model . $subject;
                new $className();
            }
        }

        self::setDefaultSettings();
        self::installAccountingPlan();
    }

    protected function addCleanup(callable $callback): void
    {
        $this->cleanupCallbacks[] = $callback;
    }

    protected function tearDown(): void
    {
        while ($callback = array_pop($this->cleanupCallbacks)) {
            try {
                $callback();
            } catch (\Throwable $th) {
                error_log($th->getMessage());
            }
        }

        $this->logErrors();
    }
}
