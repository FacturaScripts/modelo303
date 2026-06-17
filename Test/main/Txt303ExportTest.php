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
use FacturaScripts\Plugins\Modelo303\Lib\Modelo303;
use FacturaScripts\Plugins\Modelo303\Lib\Txt303Export;

/**
 * Pruebas del generador del fichero del modelo 303 (diseño de registro AEAT 2026).
 * Verifica la estructura de etiquetas, las longitudes exactas de cada página y la posición
 * de algunas casillas dentro del fichero de posiciones fijas.
 */
final class Txt303ExportTest extends Modelo303TestCase
{
    private const LEN_CLOSE = 18;

    // longitudes oficiales del diseño de registro DR303 (2026 v1.01)
    private const LEN_HEADER = 328;

   // <T...> + bloque <AUX>...</AUX>
    private const LEN_PAGE1 = 1581;

    private const LEN_PAGE3 = 1017;

    private const LEN_PAGE_DID = 823;

     // </T3030AAAAPP0000>

    public function testEstructuraYLongitudes(): void
    {
        $reg = new RegularizacionImpuesto();
        $reg->fechainicio = '2026-01-01';
        $reg->fechafin = '2026-03-31';
        $reg->periodo = 'T1';

        $empresa = new Empresa();
        $empresa->cifnif = 'B12345678';
        $empresa->nombre = 'EMPRESA TEST SL';
        $empresa->regimeniva = 'General';

        // casillas simuladas del régimen general
        $modelo = new Modelo303();
        $modelo->setCasilla('01', 1000.0);
        $modelo->setCasilla('03', 210.0);
        $modelo->setCasilla('27', 210.0);
        $modelo->setCasilla('28', 500.0);
        $modelo->setCasilla('29', 105.0);
        $modelo->setCasilla('45', 105.0);
        $modelo->setCasilla('46', 105.0);
        $modelo->computeResultado();

        $content = Txt303Export::export($reg, $modelo->getSquares(), $empresa);

        // sin IBAN no debe incluirse la página de domiciliación/devolución
        $this->assertStringNotContainsString('<T303DID00>', $content);

        // longitud total = cabecera + página 1 + página 3 + cierre
        $expected = self::LEN_HEADER + self::LEN_PAGE1 + self::LEN_PAGE3 + self::LEN_CLOSE;
        $this->assertSame($expected, strlen($content), 'Longitud total del fichero incorrecta');

        // etiqueta de apertura: <T + 303 + 0 + EEEE + PP + 0000>
        $this->assertSame('<T303020261T0000>', substr($content, 0, 17));

        // etiqueta de cierre
        $this->assertStringEndsWith('</T303020261T0000>', $content);

        // bloque AUX
        $this->assertStringContainsString('<AUX>', $content);
        $this->assertStringContainsString('</AUX>', $content);
    }

    public function testIncluyePaginaDidConIban(): void
    {
        $reg = new RegularizacionImpuesto();
        $reg->fechainicio = '2026-01-01';
        $reg->periodo = 'T1';
        $reg->iban = 'ES9121000418450200051332';

        $empresa = new Empresa();
        $empresa->cifnif = 'B12345678';
        $empresa->nombre = 'EMPRESA TEST SL';

        $modelo = new Modelo303();
        $modelo->computeResultado();

        $content = Txt303Export::export($reg, $modelo->getSquares(), $empresa);

        $this->assertStringContainsString('<T303DID00>', $content);
        $pageDid = $this->extractPage($content, '<T303DID00>', '</T303DID00>');
        $this->assertSame(self::LEN_PAGE_DID, strlen($pageDid), 'La página DID no tiene 823 posiciones');

        // IBAN en posición 23, longitud 34, alineado a la izquierda
        $iban = rtrim(substr($pageDid, 22, 34));
        $this->assertSame('ES9121000418450200051332', $iban, 'IBAN mal posicionado');
    }

    public function testLongitudPaginas(): void
    {
        $reg = new RegularizacionImpuesto();
        $reg->fechainicio = '2026-01-01';
        $reg->periodo = 'T1';

        $empresa = new Empresa();
        $empresa->cifnif = 'B12345678';
        $empresa->nombre = 'EMPRESA TEST SL';

        $modelo = new Modelo303();
        $modelo->computeResultado();

        $content = Txt303Export::export($reg, $modelo->getSquares(), $empresa);

        // página 1
        $page1 = $this->extractPage($content, '<T30301000>', '</T30301000>');
        $this->assertSame(self::LEN_PAGE1, strlen($page1), 'La página 1 no tiene 1581 posiciones');

        // página 3
        $page3 = $this->extractPage($content, '<T30303000>', '</T30303000>');
        $this->assertSame(self::LEN_PAGE3, strlen($page3), 'La página 3 no tiene 1017 posiciones');
    }

    public function testPosicionCasillas(): void
    {
        $reg = new RegularizacionImpuesto();
        $reg->fechainicio = '2026-01-01';
        $reg->periodo = 'T1';

        $empresa = new Empresa();
        $empresa->cifnif = 'B12345678';
        $empresa->nombre = 'EMPRESA TEST SL';

        $modelo = new Modelo303();
        $modelo->setCasilla('03', 210.0); // cuota 4% -> casilla 03
        $modelo->setCasilla('46', 105.0); // resultado régimen general
        $modelo->computeResultado();

        $content = Txt303Export::export($reg, $modelo->getSquares(), $empresa);
        $page1 = $this->extractPage($content, '<T30301000>', '</T30301000>');

        // casilla 03: posición 231, longitud 17, sin signo (21000 céntimos)
        $box03 = substr($page1, 230, 17);
        $this->assertSame(str_pad('21000', 17, '0', STR_PAD_LEFT), $box03, 'Casilla 03 mal posicionada');

        // NIF del declarante: posición 14, longitud 9 (alineado a la izquierda)
        $nif = substr($page1, 13, 9);
        $this->assertSame('B12345678', $nif, 'NIF mal posicionado');

        // % atribuible a la Administración del Estado (casilla 65) en página 3 = 100,00 -> 10000
        $page3 = $this->extractPage($content, '<T30303000>', '</T30303000>');
        $box65 = substr($page3, 215, 5); // posición 216, longitud 5
        $this->assertSame('10000', $box65, 'Casilla 65 (%) mal posicionada');
    }

    /**
     * Extrae el bloque completo de una página, incluyendo sus etiquetas de apertura y cierre.
     *
     * @param string $content
     * @param string $openTag
     * @param string $closeTag
     * @return string
     */
    private function extractPage(string $content, string $openTag, string $closeTag): string
    {
        $start = strpos($content, $openTag);
        $end = strpos($content, $closeTag);
        $this->assertNotFalse($start, 'No se encontró la etiqueta ' . $openTag);
        $this->assertNotFalse($end, 'No se encontró la etiqueta ' . $closeTag);

        return substr($content, $start, $end - $start + strlen($closeTag));
    }
}
