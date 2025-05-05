<?php

namespace FacturaScripts\Test\Plugins;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\FacturaCliente;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\Modelo303\Model\Join\PartidaImpuestoResumen;
use FacturaScripts\Test\Traits\RandomDataTrait;

class ComprobarFechaDevengoTest extends Modelo303TestCase
{
    use RandomDataTrait;

    /**
     * El plugin filtra por la fecha del asiento.
     * La fecha del asiento se asigna primero si existe la fecha de devengo
     * y si no existe por la fecha de la factura.
     *
     * @return void
     */
    public function testComprobarFechaDevengo()
    {
        $invoiceFechaHoy = $this->getRandomCustomerInvoice();

        // creamos una factura con fecha de devengo anterior
        $invoiceFechaDevengoAnterior = $this->getRandomCustomerInvoice();
        $invoiceFechaDevengoAnterior->fechadevengo = Tools::date('-1 month');
        $this->assertTrue($invoiceFechaDevengoAnterior->save());

        // creamos una factura con fecha de devengo posterior
        $invoiceFechaDevengoPosterior = $this->getRandomCustomerInvoice();
        $invoiceFechaDevengoPosterior->fechadevengo = Tools::date('+1 month');
        $this->assertTrue($invoiceFechaDevengoPosterior->save());

        // comprobamos que calcula filtrando por la fecha de devengo de las facturas

        // comprobamos que solo obtiene los resultados de la factura de hoy
        $partidaImpuestoResumen = new PartidaImpuestoResumen();
        $partida = $partidaImpuestoResumen->all([
            new DataBaseWhere('COALESCE(subcuentas.codcuentaesp, cuentas.codcuentaesp)', 'IVAREP'),
            new DataBaseWhere('fecha', Tools::date()),
        ])[0];
        $this->assertEquals($invoiceFechaHoy->totaliva, $partida->cuotaiva);
        $this->assertEquals($invoiceFechaHoy->totaliva, $partida->haber);

        // comprobamos que solo obtiene los resultados de la factura de fecha anterior
        $partidaImpuestoResumen = new PartidaImpuestoResumen();
        $partida = $partidaImpuestoResumen->all([
            new DataBaseWhere('COALESCE(subcuentas.codcuentaesp, cuentas.codcuentaesp)', 'IVAREP'),
            new DataBaseWhere('fecha', Tools::date('-1 month')),
        ])[0];
        $this->assertEquals($invoiceFechaDevengoAnterior->totaliva, $partida->cuotaiva);
        $this->assertEquals($invoiceFechaDevengoAnterior->totaliva, $partida->haber);

        // comprobamos que solo obtiene los resultados de la factura de fecha posterior
        $partidaImpuestoResumen = new PartidaImpuestoResumen();
        $partida = $partidaImpuestoResumen->all([
            new DataBaseWhere('COALESCE(subcuentas.codcuentaesp, cuentas.codcuentaesp)', 'IVAREP'),
            new DataBaseWhere('fecha', Tools::date('+1 month')),
        ])[0];
        $this->assertEquals($invoiceFechaDevengoPosterior->totaliva, $partida->cuotaiva);
        $this->assertEquals($invoiceFechaDevengoPosterior->totaliva, $partida->haber);

        // comprobamos que solo obtiene los resultados de todas las facturas
        $totalIVAFacturasCliente = FacturaCliente::table()->sum('totaliva');

        $partidaImpuestoResumen = new PartidaImpuestoResumen();
        $partida = $partidaImpuestoResumen->all([
            new DataBaseWhere('COALESCE(subcuentas.codcuentaesp, cuentas.codcuentaesp)', 'IVAREP'),
        ])[0];
        $this->assertEquals($totalIVAFacturasCliente, $partida->cuotaiva);
        $this->assertEquals($totalIVAFacturasCliente, $partida->haber);
    }
}
