<?php

namespace FacturaScripts\Plugins\Modelo303\Lib;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\DataSrc\Impuestos;
use FacturaScripts\Core\Model\Asiento;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Partida;

class AccountingItems
{
    public static function groupedBySubaccount(string $codejercicio, string $fechainicio, string $fechafin): array
    {
        $impuestos = Impuestos::all();

        // obtenemos los codigos de subcuentas de los impuestos
        $subcuentas = array_values(array_unique(array_filter(array_merge(
            array_column($impuestos, 'codsubcuentarep'),
            array_column($impuestos, 'codsubcuentasop'),
        ))));

        // Obtenemos los asientos para poder filtrar
        // por fecha. Asi nos aseguramos que se filtra
        // primero por fecha de devengo y si no existe
        // por fecha de factura
        $asientos = Asiento::all([
            new DataBaseWhere('codejercicio', $codejercicio),
            new DataBaseWhere('fecha', $fechainicio, '>='),
            new DataBaseWhere('fecha', $fechafin, '<'),
        ], [], 0, 0);
        $idsAsientos = array_unique(array_column($asientos, Asiento::primaryColumn()));

        if(empty($idsAsientos)) {
            Tools::log()->warning('accounting-entry-not-found');
            return [];
        }

        $partidas = Partida::all([
            new DataBaseWhere('idasiento', $idsAsientos, 'IN'),
            new DataBaseWhere('codsubcuenta', $subcuentas, 'IN')
        ], [], 0, 0);

        $partidasAgrupadas = [];
        foreach ($partidas as $partida) {
            $partidasAgrupadas[$partida->codsubcuenta][] = $partida;
        }

        return $partidasAgrupadas;
    }
}