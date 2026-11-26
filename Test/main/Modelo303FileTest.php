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
use FacturaScripts\Plugins\Modelo303\Lib\Modelo303File;

/**
 * Pruebas del generador del fichero del modelo 303 (diseños de registro AEAT de 2025 y 2026).
 * Verifica la estructura de etiquetas, las longitudes exactas de cada página y la posición
 * de algunas casillas dentro del fichero de posiciones fijas.
 */
final class Modelo303FileTest extends Modelo303TestCase
{
    private const LEN_CLOSE = 18;

    // longitudes oficiales del diseño de registro DR303 (2026 v1.01)
    private const LEN_HEADER = 328;

   // <T...> + bloque <AUX>...</AUX>
    private const LEN_PAGE1 = 1581;

    private const LEN_PAGE3 = 1017;

    private const LEN_PAGE_DID = 823;

    /** posición (base 0) del campo "Devolución - Marca SEPA" dentro de la página DID */
    private const POS_MARCA_SEPA = 193;

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

        $content = Modelo303File::generate($reg, $modelo->getSquares(), $empresa);

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
        $reg->tipodeclaracion = 'U';

        $content = $this->exportSimple($reg);

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

        $content = Modelo303File::generate($reg, $modelo->getSquares(), $empresa);

        // página 1
        $page1 = $this->extractPage($content, '<T30301000>', '</T30301000>');
        $this->assertSame(self::LEN_PAGE1, strlen($page1), 'La página 1 no tiene 1581 posiciones');

        // página 3
        $page3 = $this->extractPage($content, '<T30303000>', '</T30303000>');
        $this->assertSame(self::LEN_PAGE3, strlen($page3), 'La página 3 no tiene 1017 posiciones');
    }

    /**
     * En una devolución (D) con cuenta española la marca SEPA debe ser "1".
     */
    public function testMarcaSepaEnDevolucion(): void
    {
        $reg = new RegularizacionImpuesto();
        $reg->fechainicio = '2026-01-01';
        $reg->periodo = 'T1';
        $reg->iban = 'ES9121000418450200051332';
        $reg->tipodeclaracion = 'D';

        $pageDid = $this->extractPage($this->exportSimple($reg), '<T303DID00>', '</T303DID00>');
        $this->assertSame('1', substr($pageDid, self::POS_MARCA_SEPA, 1), 'Marca SEPA incorrecta en devolución');
    }

    /**
     * En una domiciliación (U) la marca SEPA debe ser "0": la AEAT rechaza el fichero con el
     * error EDID113 si tiene contenido y la declaración no es a devolver.
     */
    public function testMarcaSepaVaciaEnDomiciliacion(): void
    {
        $reg = new RegularizacionImpuesto();
        $reg->fechainicio = '2026-01-01';
        $reg->periodo = 'T1';
        $reg->iban = 'ES9121000418450200051332';
        $reg->tipodeclaracion = 'U';

        $pageDid = $this->extractPage($this->exportSimple($reg), '<T303DID00>', '</T303DID00>');
        $this->assertSame('0', substr($pageDid, self::POS_MARCA_SEPA, 1), 'Marca SEPA con contenido en domiciliación');
    }

    /**
     * El resumen anual (periodo Y, modelo 390) no tiene fichero .303: antes se generaba con el
     * periodo 1T, que no le corresponde.
     */
    public function testPeriodoAnualNoGeneraFichero303(): void
    {
        $reg = new RegularizacionImpuesto();
        $reg->fechainicio = '2026-01-01';
        $reg->fechafin = '2026-12-31';
        $reg->periodo = 'Y';

        $empresa = new Empresa();
        $empresa->cifnif = 'B12345678';
        $empresa->nombre = 'EMPRESA TEST SL';

        $this->expectException(\InvalidArgumentException::class);
        Modelo303File::generate($reg, (new Modelo303())->getSquares(), $empresa);
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

        $content = Modelo303File::generate($reg, $modelo->getSquares(), $empresa);
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
     * Los ejercicios anteriores a 2026 usan el diseño de registro de 2025 (DR303e25 v1.02): sin el
     * indicador de gasolinas, con las casillas [165]-[170] al final de la página 1 y la [108] tras el
     * justificante anterior en la página 3. Las páginas miden lo mismo que en 2026.
     */
    public function testDisenoRegistro2025(): void
    {
        $reg = new RegularizacionImpuesto();
        $reg->fechainicio = '2025-01-01';
        $reg->periodo = 'T1';

        $modelo = $this->modeloConCasillas();
        $content = Modelo303File::generate($reg, $modelo->getSquares(), $this->empresa());
        $this->assertSame(self::LEN_HEADER + self::LEN_PAGE1 + self::LEN_PAGE3 + self::LEN_CLOSE, strlen($content));
        $this->assertSame('<T303020251T0000>', substr($content, 0, 17));

        $page1 = $this->extractPage($content, '<T30301000>', '</T30301000>');
        $this->assertSame(self::LEN_PAGE1, strlen($page1));
        // [150] en la posición 130 (no hay indicador de gasolinas) y [03] en la 191
        $this->assertSame(str_repeat('0', 17), substr($page1, 129, 17));
        $this->assertSame($this->importe(210.0), substr($page1, 190, 17));
        // [46] en la 940, seguida de [165]-[167] en la 957 y [168]-[170] en la 996
        $this->assertSame($this->importe(105.0), substr($page1, 939, 17));
        $this->assertSame($this->importe(100.0), substr($page1, 956, 17));
        $this->assertSame($this->importe(200.0), substr($page1, 995, 17));
        $this->assertSame('00050', substr($page1, 1012, 5));
        $this->assertSame($this->importe(1.0), substr($page1, 1017, 17));

        $page3 = $this->extractPage($content, '<T30303000>', '</T30303000>');
        $this->assertSame(self::LEN_PAGE3, strlen($page3));
        // [71] en la 374 y [108] en la 407
        $this->assertSame($this->importe($modelo->casilla('71')), substr($page3, 373, 17));
        $this->assertSame($this->importe(7.0), substr($page3, 406, 17));
    }

    /**
     * En 2026 las casillas [168]-[170] van tras las [156]-[158] y la [71] tras la [112].
     */
    public function testDisenoRegistro2026Posiciones(): void
    {
        $reg = new RegularizacionImpuesto();
        $reg->fechainicio = '2026-01-01';
        $reg->periodo = 'T1';

        $modelo = $this->modeloConCasillas();
        $content = Modelo303File::generate($reg, $modelo->getSquares(), $this->empresa());

        $page1 = $this->extractPage($content, '<T30301000>', '</T30301000>');
        $this->assertSame($this->importe(100.0), substr($page1, 169, 17));
        $this->assertSame($this->importe(200.0), substr($page1, 505, 17));
        $this->assertSame('00050', substr($page1, 522, 5));

        $page3 = $this->extractPage($content, '<T30303000>', '</T30303000>');
        $this->assertSame($this->importe(7.0), substr($page3, 322, 17));
        $this->assertSame($this->importe($modelo->casilla('71')), substr($page3, 407, 17));
    }

    /**
     * La casilla 112 (pago a cuenta de gasolinas) no existe antes de 2026: se avisa y no se resta en la
     * casilla 71. En 2026 sí se resta.
     */
    public function testCasilla112SoloDesde2026(): void
    {
        $reg = new RegularizacionImpuesto();
        $reg->codejercicio = 'X';
        $reg->fechainicio = '2025-04-01';
        $reg->fechafin = '2025-06-30';
        $reg->periodo = 'T2';
        $reg->c112 = 50.0;

        $modelo = Modelo303::fromRegularizacion($reg, []);
        $this->assertEqualsWithDelta(0.0, $modelo->casilla('112'), 0.001);
        $this->assertEqualsWithDelta(0.0, $modelo->casilla('71'), 0.001);
        $this->assertCount(1, $modelo->getAvisos());

        $reg->fechainicio = '2026-04-01';
        $reg->fechafin = '2026-06-30';
        $modelo = Modelo303::fromRegularizacion($reg, []);
        $this->assertEqualsWithDelta(50.0, $modelo->casilla('112'), 0.001);
        $this->assertEqualsWithDelta(-50.0, $modelo->casilla('71'), 0.001);
        $this->assertEmpty($modelo->getAvisos());
    }

    /**
     * En un ingreso (I) no se deben enviar datos bancarios aunque el usuario haya rellenado el IBAN.
     */
    public function testSinPaginaDidEnIngreso(): void
    {
        $reg = new RegularizacionImpuesto();
        $reg->fechainicio = '2026-01-01';
        $reg->periodo = 'T1';
        $reg->iban = 'ES9121000418450200051332';
        $reg->tipodeclaracion = 'I';

        $this->assertStringNotContainsString('<T303DID00>', $this->exportSimple($reg));
    }

    /**
     * Empresa declarante de prueba.
     *
     * @return Empresa
     */
    private function empresa(): Empresa
    {
        $empresa = new Empresa();
        $empresa->cifnif = 'B12345678';
        $empresa->nombre = 'EMPRESA TEST SL';
        return $empresa;
    }

    /**
     * Importe en el formato del fichero: 17 posiciones con 2 decimales y ceros a la izquierda.
     *
     * @param float $amount
     * @return string
     */
    private function importe(float $amount): string
    {
        return str_pad((string)round($amount * 100), 17, '0', STR_PAD_LEFT);
    }

    /**
     * Modelo con importes en las casillas que cambian de sitio entre los diseños de 2025 y 2026.
     *
     * @return Modelo303
     */
    private function modeloConCasillas(): Modelo303
    {
        $modelo = new Modelo303();
        $modelo->setCasilla('03', 210.0);
        $modelo->setCasilla('46', 105.0);
        $modelo->setCasilla('165', 100.0);
        $modelo->setCasilla('168', 200.0);
        $modelo->setCasilla('170', 1.0);
        $modelo->setCasilla('108', 7.0);
        $modelo->computeResultado();
        return $modelo;
    }

    /**
     * Exporta la regularización indicada con una empresa de prueba y sin casillas calculadas.
     *
     * @param RegularizacionImpuesto $reg
     * @return string
     */
    private function exportSimple(RegularizacionImpuesto $reg): string
    {
        $empresa = new Empresa();
        $empresa->cifnif = 'B12345678';
        $empresa->nombre = 'EMPRESA TEST SL';

        $modelo = new Modelo303();
        $modelo->computeResultado();

        return Modelo303File::generate($reg, $modelo->getSquares(), $empresa);
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
