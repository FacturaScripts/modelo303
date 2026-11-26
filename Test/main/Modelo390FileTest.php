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

use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Dinamic\Model\RegularizacionImpuesto;
use FacturaScripts\Plugins\Modelo303\Lib\Modelo390File;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Pruebas del generador del fichero del modelo 390 (diseños de registro DR390 de 2024-2025 y 2026):
 * estructura de etiquetas, longitudes oficiales de cada página y posición de algunas casillas y
 * datos de identificación.
 *
 * @author Daniel Fernández Giménez <contacto@danielfg.es>
 */
final class Modelo390FileTest extends TestCase
{
    /** Longitud de la cabecera <T3900EEEE0A0000><AUX>...</AUX>. */
    private const LEN_HEADER = 328;

    /** Longitudes oficiales de cada página por diseño (comprobadas en los Excel de la AEAT). */
    private const PAGE_LENGTHS = [
        2025 => ['01000' => 1187, '02000' => 1806, '02B00' => 531, '03000' => 1840, '04000' => 854, '06000' => 828, '07000' => 776],
        2026 => ['01000' => 1188, '02000' => 1806, '02B00' => 531, '03000' => 1840, '04000' => 854, '06000' => 862, '07000' => 781],
    ];

    /**
     * Los ejercicios 2024 y 2025 comparten diseño; 2026 y siguientes usan el nuevo.
     */
    public function testDisenoPorEjercicio(): void
    {
        $this->assertSame(Modelo390File::LAYOUTS['2024'], Modelo390File::layout(2025));
        $this->assertSame(Modelo390File::LAYOUTS['2024'], Modelo390File::layout(2024));
        $this->assertSame(Modelo390File::LAYOUTS['2026'], Modelo390File::layout(2026));
        $this->assertSame(Modelo390File::LAYOUTS['2026'], Modelo390File::layout(2030));

        $this->expectException(InvalidArgumentException::class);
        Modelo390File::layout(2023);
    }

    /**
     * Estructura y longitudes: cabecera, páginas en orden con su longitud oficial y cierre.
     */
    public function testEstructuraYLongitudes(): void
    {
        foreach (self::PAGE_LENGTHS as $year => $lengths) {
            $content = $this->export($year, []);

            $this->assertStringStartsWith('<T3900' . $year . '0A0000><AUX>', $content);
            $this->assertStringEndsWith('</T3900' . $year . '0A0000>', $content);
            $this->assertSame('</AUX>', substr($content, self::LEN_HEADER - 6, 6));
            $this->assertSame(self::LEN_HEADER + array_sum($lengths) + 18, strlen($content), (string)$year);

            $offset = self::LEN_HEADER;
            foreach ($lengths as $page => $length) {
                $this->assertSame('<T390' . $page . '>', substr($content, $offset, 11), $year . ' ' . $page);
                $this->assertSame('</T390' . $page . '>', substr($content, $offset + $length - 12, 12), $year . ' ' . $page);
                $offset += $length;
            }

            // no se generan el régimen simplificado ni los sectores diferenciados
            $this->assertStringNotContainsString('<T39005000>', $content);
            $this->assertStringNotContainsString('<T39008000>', $content);
        }
    }

    /**
     * Datos de identificación de la página 01000, cuyas posiciones cambian de 2025 a 2026.
     */
    public function testIdentificacion(): void
    {
        // [posición de la marca de sustitutiva, posición de la actividad principal]
        $positions = [2025 => [133, 148], 2026 => [134, 149]];
        foreach ($positions as $year => [$posSustitutiva, $posActividad]) {
            $page = $this->page($this->export($year, []), '01000');

            $this->assertSame('B12345678', $this->field($page, 14, 9));
            $this->assertSame(str_pad('EMPRESA TEST SL', 60), $this->field($page, 23, 60));
            $this->assertSame((string)$year, $this->field($page, 103, 4));
            $this->assertSame('1', $this->field($page, $posSustitutiva, 1));
            $this->assertSame('ABC1234567890', $this->field($page, $posSustitutiva + 2, 13));
            $this->assertSame(str_pad('COMERCIO AL POR MENOR', 40), $this->field($page, $posActividad, 40));
            $this->assertSame('A03', $this->field($page, $posActividad + 40, 3));
            $this->assertSame('6599', $this->field($page, $posActividad + 43, 4));
        }

        // indicador del pago a cuenta de carburantes, solo en 2026
        $this->assertSame('2', $this->field($this->page($this->export(2026, []), '01000'), 133, 1));
    }

    /**
     * Posición y formato de algunas casillas: importes en céntimos, negativos con 'N' y la casilla
     * 86 y el volumen (108), que cambian de posición en 2026.
     */
    public function testPosicionCasillas(): void
    {
        $squares = ['05' => 1000.0, '06' => 210.0, '33' => 1000.0, '34' => 210.0, '47' => 210.0,
            '605' => 2000.0, '606' => 420.0, '64' => 420.0, '65' => -210.0, '85' => 50.0,
            '86' => -260.0, '108' => 1000.0, '230' => 75.5];

        // [posición de la 86, posición de la 108] en la página 06000
        $positions = [2025 => [81, 650], 2026 => [98, 684]];
        foreach ($positions as $year => [$pos86, $pos108]) {
            $content = $this->export($year, $squares);

            $page = $this->page($content, '02000');
            $this->assertSame('00000000000100000', $this->field($page, 217, 17));
            $this->assertSame('00000000000021000', $this->field($page, 234, 17));
            $this->assertSame('00000000000021000', $this->field($page, 1628, 17));

            $this->assertSame('00000000000021000', $this->field($this->page($content, '02B00'), 353, 17));

            $page = $this->page($content, '03000');
            $this->assertSame('00000000000200000', $this->field($page, 183, 17));
            $this->assertSame('00000000000042000', $this->field($page, 200, 17));

            $page = $this->page($content, '04000');
            $this->assertSame('00000000000042000', $this->field($page, 659, 17));
            $this->assertSame('N0000000000021000', $this->field($page, 676, 17));

            $page = $this->page($content, '06000');
            $this->assertSame('00000000000005000', $this->field($page, 64, 17));
            $this->assertSame('N0000000000026000', $this->field($page, $pos86, 17));
            $this->assertSame('00000000000100000', $this->field($page, $pos108, 17));

            $this->assertSame('00000000000007550', $this->field($this->page($content, '07000'), 13, 17));
        }
    }

    /**
     * El fichero solo se genera para la regularización anual.
     */
    public function testSoloRegularizacionAnual(): void
    {
        $reg = $this->regularization(2026);
        $reg->periodo = 'T4';

        $this->expectException(InvalidArgumentException::class);
        Modelo390File::generate($reg, [], $this->company());
    }

    /**
     * Empresa de prueba.
     *
     * @return Empresa
     */
    private function company(): Empresa
    {
        $empresa = new Empresa();
        $empresa->cifnif = 'B12345678';
        $empresa->nombre = 'Empresa Test SL';
        $empresa->regimeniva = 'General';
        return $empresa;
    }

    /**
     * Genera el fichero de una regularización anual de prueba.
     *
     * @param int $year
     * @param array<string, float> $squares
     * @return string
     */
    private function export(int $year, array $squares): string
    {
        return Modelo390File::generate($this->regularization($year), $squares, $this->company());
    }

    /**
     * Devuelve un campo de una página por su posición (base 1) y longitud, como en el diseño.
     *
     * @param string $page
     * @param int $position
     * @param int $length
     * @return string
     */
    private function field(string $page, int $position, int $length): string
    {
        return substr($page, $position - 1, $length);
    }

    /**
     * Extrae una página completa, con sus etiquetas de apertura y cierre.
     *
     * @param string $content
     * @param string $code
     * @return string
     */
    private function page(string $content, string $code): string
    {
        $start = strpos($content, '<T390' . $code . '>');
        $end = strpos($content, '</T390' . $code . '>');
        $this->assertNotFalse($start, 'No se encontró la página ' . $code);
        $this->assertNotFalse($end, 'No se encontró el cierre de la página ' . $code);
        return substr($content, $start, $end - $start + 12);
    }

    /**
     * Regularización anual de prueba con los datos de presentación del 390.
     *
     * @param int $year
     * @return RegularizacionImpuesto
     */
    private function regularization(int $year): RegularizacionImpuesto
    {
        $reg = new RegularizacionImpuesto();
        $reg->periodo = 'Y';
        $reg->fechainicio = $year . '-01-01';
        $reg->fechafin = $year . '-12-31';
        $reg->rectificativa = true;
        $reg->justificanteant = 'ABC1234567890';
        $reg->actividad390 = 'Comercio al por menor';
        $reg->claveactividad390 = 'A03';
        $reg->epigrafeiae390 = '6599';
        $reg->operaciones347 = true;
        return $reg;
    }
}
