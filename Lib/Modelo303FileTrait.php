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

namespace FacturaScripts\Plugins\Modelo303\Lib;

use FacturaScripts\Core\Tools;

/**
 * Formateadores de campos de los ficheros de posiciones fijas de la AEAT («diseños de registro»),
 * compartidos por los ficheros de los modelos 303 (Modelo303File) y 390 (Modelo390File):
 * alfanuméricos en mayúsculas y sin acentos, importes con y sin signo y porcentajes.
 *
 * Es un trait y no una clase para que no se despliegue en Dinamic/Lib, que es común a todos los plugins.
 *
 * @author Daniel Fernández Giménez <contacto@danielfg.es>
 */
trait Modelo303FileTrait
{
    /**
     * Formatea un campo alfanumérico: mayúsculas, sin acentos, alineado a la izquierda y
     * relleno con blancos por la derecha (según Nota 2 del diseño de registro).
     *
     * @param string $value
     * @param int $length
     * @return string
     */
    private static function alpha(string $value, int $length): string
    {
        $value = mb_strtoupper(self::sanitize($value), 'UTF-8');
        $value = mb_substr($value, 0, $length, 'UTF-8');
        $pad = $length - mb_strlen($value, 'UTF-8');
        if ($pad > 0) {
            $value .= str_repeat(' ', $pad);
        }
        return $value;
    }

    /**
     * Formatea un importe con signo (campo "N"): 'N' en la primera posición si es negativo,
     * en otro caso relleno con ceros a la izquierda.
     *
     * @param float $value
     * @param int $length
     * @return string
     */
    private static function amountSigned(float $value, int $length): string
    {
        $cents = (int)round(abs($value) * 100.0);
        if ($value < 0.0) {
            return 'N' . str_pad((string)$cents, $length - 1, '0', STR_PAD_LEFT);
        }
        return str_pad((string)$cents, $length, '0', STR_PAD_LEFT);
    }

    /**
     * Formatea un importe sin signo (campo "Num"): 2 decimales implícitos, relleno con ceros.
     *
     * @param float $value
     * @param int $length
     * @return string
     */
    private static function amountUnsigned(float $value, int $length): string
    {
        $cents = (int)round(abs($value) * 100.0);
        return str_pad((string)$cents, $length, '0', STR_PAD_LEFT);
    }

    /**
     * Formatea un porcentaje (3 enteros + 2 decimales), relleno con ceros a la izquierda.
     *
     * @param float $value
     * @param int $length
     * @return string
     */
    private static function percent(float $value, int $length): string
    {
        $hundredths = (int)round(abs($value) * 100.0);
        return str_pad((string)$hundredths, $length, '0', STR_PAD_LEFT);
    }

    /**
     * Elimina acentos y caracteres no admitidos en los campos alfanuméricos.
     *
     * @param string|null $txt
     * @return string
     */
    private static function sanitize(?string $txt): string
    {
        $changes = ['/à/' => 'a', '/á/' => 'a', '/â/' => 'a', '/ã/' => 'a', '/ä/' => 'a',
            '/å/' => 'a', '/æ/' => 'ae', '/ç/' => 'c', '/è/' => 'e', '/é/' => 'e', '/ê/' => 'e',
            '/ë/' => 'e', '/ì/' => 'i', '/í/' => 'i', '/î/' => 'i', '/ï/' => 'i', '/ð/' => 'd',
            '/ñ/' => 'N', '/ò/' => 'o', '/ó/' => 'o', '/ô/' => 'o', '/õ/' => 'o', '/ö/' => 'o',
            '/ő/' => 'o', '/ø/' => 'o', '/ù/' => 'u', '/ú/' => 'u', '/û/' => 'u', '/ü/' => 'u',
            '/ű/' => 'u', '/ý/' => 'y', '/þ/' => 'th', '/ÿ/' => 'y',
            '/&quot;/' => '-', '/€/' => 'EUR', '/º/' => '.',
            '/À/' => 'A', '/Á/' => 'A', '/Â/' => 'A', '/Ä/' => 'A',
            '/Ç/' => 'C', '/È/' => 'E', '/É/' => 'E', '/Ê/' => 'E',
            '/Ë/' => 'E', '/Ì/' => 'I', '/Í/' => 'I', '/Î/' => 'I', '/Ï/' => 'I',
            '/Ñ/' => 'N', '/Ò/' => 'O', '/Ó/' => 'O', '/Ô/' => 'O', '/Ö/' => 'O',
            '/Ù/' => 'U', '/Ú/' => 'U', '/Û/' => 'U', '/Ü/' => 'U',
            '/Ý/' => 'Y', '/Ÿ/' => 'Y'
        ];

        $txtNoHtml = Tools::noHtml($txt) ?? '';
        return preg_replace(array_keys($changes), $changes, $txtNoHtml);
    }
}
