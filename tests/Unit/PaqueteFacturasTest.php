<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Siat\PaqueteFacturas;
use App\Services\Siat\SiatException;
use PharData;
use Tests\TestCase;

/**
 * El SIN no acepta los XML sueltos en un envío por lote: espera un .tar.gz con un
 * fichero por factura. Aquí se comprueba que lo que sale es exactamente eso,
 * descomprimiéndolo de vuelta.
 */
class PaqueteFacturasTest extends TestCase
{
    private PaqueteFacturas $paquetes;

    protected function setUp(): void
    {
        parent::setUp();
        $this->paquetes = new PaqueteFacturas();
    }

    /**
     * Escribe los bytes en disco y los abre como tar.gz para inspeccionarlos.
     *
     * @return array<string, string> nombre => contenido
     */
    private function descomprimir(string $bytes): array
    {
        $ruta = tempnam(sys_get_temp_dir(), 'test') . '.tar.gz';
        file_put_contents($ruta, $bytes);

        $contenido = [];

        try {
            foreach (new PharData($ruta) as $fichero) {
                $contenido[$fichero->getFilename()] = file_get_contents($fichero->getPathname());
            }
        } finally {
            @unlink($ruta);
        }

        return $contenido;
    }

    public function test_it_packs_one_xml_file_per_invoice_named_after_its_cuf(): void
    {
        $bytes = $this->paquetes->construir([
            'ABC123' => '<factura>1</factura>',
            'DEF456' => '<factura>2</factura>',
        ]);

        $contenido = $this->descomprimir($bytes);

        $this->assertCount(2, $contenido);
        $this->assertSame('<factura>1</factura>', $contenido['ABC123.xml']);
        $this->assertSame('<factura>2</factura>', $contenido['DEF456.xml']);
    }

    public function test_it_produces_a_gzip_stream(): void
    {
        $bytes = $this->paquetes->construir(['ABC123' => '<factura/>']);

        // Cabecera mágica de gzip: el SIN rechaza cualquier otro formato.
        $this->assertSame("\x1f\x8b", substr($bytes, 0, 2));
    }

    /** El hash que viaja con el envío se calcula sobre los bytes comprimidos. */
    public function test_the_hash_covers_the_compressed_bytes(): void
    {
        $bytes = $this->paquetes->construir(['ABC123' => '<factura/>']);

        $this->assertSame(hash('sha256', $bytes), $this->paquetes->hash($bytes));
    }

    public function test_it_refuses_to_build_an_empty_package(): void
    {
        $this->expectException(SiatException::class);
        $this->expectExceptionMessageMatches('/No hay facturas/');

        $this->paquetes->construir([]);
    }

    public function test_it_reads_back_what_it_packed(): void
    {
        $original = ['ABC123' => '<factura>1</factura>', 'DEF456' => '<factura>2</factura>'];

        $leido = $this->paquetes->leer($this->paquetes->construir($original));

        $this->assertSame([
            'ABC123.xml' => '<factura>1</factura>',
            'DEF456.xml' => '<factura>2</factura>',
        ], $leido);
    }

    /**
     * Los .tar.gz que devuelve el SIN no vienen rellenados hasta el bloque final
     * que exige el formato: PharData los rechaza como "corrupted tar (truncated)"
     * y se perdía el contenido entero.
     */
    public function test_it_reads_a_tar_that_lacks_the_trailing_padding(): void
    {
        $completo = gzdecode($this->paquetes->construir(['F0' => '<registroCompra/>']));

        // Se recorta todo lo que sigue al último dato útil, como hace el SIN.
        $truncado = substr($completo, 0, 512 + 512);

        $leido = $this->paquetes->leer(gzencode($truncado));

        $this->assertSame('<registroCompra/>', trim($leido['F0.xml']));
    }

    public function test_reading_something_that_is_not_gzip_does_not_crash(): void
    {
        // Un tar sin comprimir se acepta igual; lo que no puede es reventar.
        $this->assertSame([], $this->paquetes->leer(str_repeat("\0", 512)));
    }

    /** No deja restos: los temporales se borran aunque el paquete sea grande. */
    public function test_it_does_not_leave_temporary_files_behind(): void
    {
        $antes = count(glob(sys_get_temp_dir() . '/siat*') ?: []);

        $this->paquetes->construir(['ABC123' => '<factura/>']);

        $this->assertSame($antes, count(glob(sys_get_temp_dir() . '/siat*') ?: []));
    }
}
