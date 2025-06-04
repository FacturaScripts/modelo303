<?php

namespace FacturaScripts\Plugins\Modelo303\Lib;

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\DataSrc\Impuestos;
use FacturaScripts\Core\KernelException;
use FacturaScripts\Core\Lib\InvoiceOperation;

class Modelo303Calculator
{
    /**
     * @throws KernelException
     */
    public static function calculate(string $codejercicio, string $fechainicio, string $fechafin): Modelo303Data
    {
        // obtenemos las partidas agrupadas por subcuenta
        $partidasAgrupadas = AccountingItems::groupedBySubaccount(
            $codejercicio, $fechainicio, $fechafin
        );

        $impuestos = Impuestos::all();
        $subcuentasSegunIVA = self::getSubAccountCodesByTax($impuestos);
        $subcuentasSegunRecargo = self::getSubAccountCodesBySurchargeType($impuestos);

        $modelo303 = new Modelo303Data();

        foreach ($partidasAgrupadas as $subcuenta => $movimientos) {
            foreach ($movimientos as $mov) {
                // IVA 4%
                if (in_array($subcuenta, $subcuentasSegunIVA[4]['repercutido'])) {
                    $modelo303->add('01', $mov->baseimponible);
                    $modelo303->add('03', $mov->haber);
                }

                // IVA 10%
                if (in_array($subcuenta, $subcuentasSegunIVA[10]['repercutido'])) {
                    $modelo303->add('04', $mov->baseimponible);
                    $modelo303->add('06', $mov->haber);
                }

                // IVA 21%
                if (in_array($subcuenta, $subcuentasSegunIVA[21]['repercutido'])) {
                    $modelo303->add('07', $mov->baseimponible);
                    $modelo303->add('09', $mov->haber);
                }

                // IVA 0%
                if (in_array($subcuenta, $subcuentasSegunIVA[0]['repercutido'])) {
                    $modelo303->add('150', $mov->baseimponible);
                    $modelo303->add('152', $mov->haber);
                }

                // RECARGO 1.75%
                if (in_array($subcuenta, $subcuentasSegunRecargo[1.75]['repercutido'])) {
                    $modelo303->add('156', $mov->baseimponible);
                    $modelo303->add('158', $mov->haber);
                }

                // RECARGO 0.5%
                if (in_array($subcuenta, $subcuentasSegunRecargo[0.5]['repercutido'])) {
                    $modelo303->add('168', $mov->baseimponible);
                    $modelo303->add('170', $mov->haber);
                }

                // RECARGO 1.4%
                if (in_array($subcuenta, $subcuentasSegunRecargo[1.4]['repercutido'])) {
                    $modelo303->add('19', $mov->baseimponible);
                    $modelo303->add('21', $mov->haber);
                }

                // RECARGO 5.2%
                if (in_array($subcuenta, $subcuentasSegunRecargo[5.2]['repercutido'])) {
                    $modelo303->add('22', $mov->baseimponible);
                    $modelo303->add('24', $mov->haber);
                }
            }
        }

        // Total cuota devengada
        $modelo303->set('27', $modelo303->get('152') + $modelo303->get('167') + $modelo303->get('03') + $modelo303->get('155') + $modelo303->get('06') + $modelo303->get('09') + $modelo303->get('11') + $modelo303->get('13') + $modelo303->get('15') + $modelo303->get('158') + $modelo303->get('170') + $modelo303->get('18') + $modelo303->get('21') + $modelo303->get('24') + $modelo303->get('26'));

        /**
         * IVA DEDUCIBLE
         */
        // Por cuotas soportadas en operaciones interiores corrientes
        foreach ($partidasAgrupadas as $subcuenta => $movimientos) {
            foreach ($movimientos as $mov) {
                // IVA 4%
                if (in_array($subcuenta, $subcuentasSegunIVA[4]['soportado'])) {
                    $modelo303->add('28', $mov->baseimponible);
                    $modelo303->add('29', $mov->debe);
                }

                // IVA 10%
                if (in_array($subcuenta, $subcuentasSegunIVA[10]['soportado'])) {
                    $modelo303->add('28', $mov->baseimponible);
                    $modelo303->add('29', $mov->debe);
                }

                // IVA 21%
                if (in_array($subcuenta, $subcuentasSegunIVA[21]['soportado'])) {
                    $modelo303->add('28', $mov->baseimponible);
                    $modelo303->add('29', $mov->debe);
                }
            }
        }

        // Total a deducir
        $modelo303->set('45', $modelo303->get('29') + $modelo303->get('31') + $modelo303->get('33') + $modelo303->get('35') + $modelo303->get('37') + $modelo303->get('39') + $modelo303->get('41') + $modelo303->get('42') + $modelo303->get('43') + $modelo303->get('44'));

        // Resultado régimen general
        $modelo303->set('46', $modelo303->get('27') - $modelo303->get('45'));

        // Información adicional
        // Ventas intracomunitarias
        $modelo303->set('59', self::getNetoFacturasVentasIntra($codejercicio, $fechainicio, $fechafin));

        // Compras intracomunitarias
        $totalesFacturasComprasIntra = self::getTotalesFacturasComprasIntra($codejercicio, $fechainicio, $fechafin);
        $baseFacturasComprasIntra = $totalesFacturasComprasIntra['base'];
        $cuotaFacturasComprasIntra = $totalesFacturasComprasIntra['cuota'];

        $modelo303->set('10', $baseFacturasComprasIntra);
        $modelo303->set('11', $cuotaFacturasComprasIntra);

        return $modelo303;
    }

    /**
     * obtenemos los códigos de subcuentas agrupados según tipo iva
     * esto lo hacemos por si existen varios impuestos
     * del mismo iva y distintas subcuentas
     *
     * @param array $impuestos
     *
     * @return array
     */
    public static function getSubAccountCodesByTax(array $impuestos): array
    {
        $subcuentasSegunIVA = [];
        foreach ($impuestos as $impuesto) {
            $subcuentasSegunIVA[$impuesto->iva]['repercutido'][] = $impuesto->codsubcuentarep;
            $subcuentasSegunIVA[$impuesto->iva]['soportado'][] = $impuesto->codsubcuentasop;
        }
        return $subcuentasSegunIVA;
    }

    /**
     * obtenemos los codigos de subcuentas agrupados según tipo recargo
     * esto lo hacemos por si existen varios impuestos
     * del mismo recargo y distintas subcuentas
     *
     * @param array $impuestos
     *
     * @return array
     */
    public static function getSubAccountCodesBySurchargeType(array $impuestos): array
    {
        $subcuentasSegunRecargo = [];
        foreach ($impuestos as $impuesto) {
            $subcuentasSegunRecargo[$impuesto->recargo]['repercutido'][] = $impuesto->codsubcuentarepre;
            $subcuentasSegunRecargo[$impuesto->recargo]['soportado'][] = $impuesto->codsubcuentasopre;
        }
        return $subcuentasSegunRecargo;
    }

    /**
     * @throws KernelException
     */
    private static function getNetoFacturasVentasIntra(string $codejercicio, string $fechainicio, string $fechafin)
    {
        $dataBase = new DataBase();

        $sql = "SELECT SUM(neto) AS neto FROM facturascli ";
        $sql .= "WHERE codejercicio = " . $dataBase->var2str($codejercicio) . " AND ";
        $sql .= "COALESCE(fechadevengo, fecha) >= " . $dataBase->var2str($fechainicio) . " AND ";
        $sql .= "COALESCE(fechadevengo, fecha) < " . $dataBase->var2str($fechafin) . " AND ";
        $sql .= "operacion = " . $dataBase->var2str(InvoiceOperation::INTRA_COMMUNITY) . ";";

        return $dataBase->select($sql)[0]['neto'];
    }

    /**
     * @throws KernelException
     */
    private static function getTotalesFacturasComprasIntra(string $codejercicio, string $fechainicio, string $fechafin)
    {
        $dataBase = new DataBase();

        $sql = "SELECT SUM(neto) AS base, SUM(totaliva) AS cuota FROM facturasprov ";
        $sql .= "WHERE codejercicio = " . $dataBase->var2str($codejercicio) . " AND ";
        $sql .= "COALESCE(fechadevengo, fecha) >= " . $dataBase->var2str($fechainicio) . " AND ";
        $sql .= "COALESCE(fechadevengo, fecha) < " . $dataBase->var2str($fechafin) . " AND ";
        $sql .= "operacion = " . $dataBase->var2str(InvoiceOperation::INTRA_COMMUNITY) . ";";

        return $dataBase->select($sql)[0];
    }
}