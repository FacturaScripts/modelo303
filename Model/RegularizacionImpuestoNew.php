<?php

namespace FacturaScripts\Plugins\Modelo303\Model;

use FacturaScripts\Dinamic\Model\RegularizacionImpuesto;

class RegularizacionImpuestoNew extends RegularizacionImpuesto
{
    public function url(string $type = 'auto', string $list = 'List'): string
    {
        return parent::url($type, $list);
    }
}