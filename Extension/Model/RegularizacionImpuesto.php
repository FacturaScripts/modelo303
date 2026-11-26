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

namespace FacturaScripts\Plugins\Modelo303\Extension\Model;

use Closure;

/**
 * Extensión del modelo RegularizacionImpuesto del core.
 *
 * Al eliminar una regularización, el core elimina también su asiento. Eso es correcto con el asiento
 * que crea el plugin, pero no con un asiento que ya existía y que solo se vinculó a mano: ese asiento
 * es del usuario y debe quedarse en la contabilidad.
 *
 * @property bool $asientomanual el asiento se vinculó a mano (columna añadida por Extension/Table).
 * @property ?int $idasiento
 *
 * @author Daniel Fernández Giménez <contacto@danielfg.es>
 */
class RegularizacionImpuesto
{
    /**
     * Antes de eliminar la regularización, olvida el asiento si se vinculó a mano, para que
     * RegularizacionImpuesto::delete() del core no lo elimine. El registro se elimina igual.
     *
     * @return Closure
     */
    public function deleteBefore(): Closure
    {
        return function () {
            if ($this->asientomanual && false === empty($this->idasiento)) {
                $this->idasiento = null;
            }

            return true;
        };
    }
}
