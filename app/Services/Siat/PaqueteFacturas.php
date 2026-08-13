<?php

declare(strict_types=1);

namespace App\Services\Siat;

use PharData;

/**
 * Empaquetado de facturas para `recepcionPaqueteFactura` y
 * `recepcionMasivaFactura`.
 *
 * El SIN no acepta los XML sueltos: espera un .tar.gz con un fichero por factura,
 * nombrado con su CUF. El `hashArchivo` que acompaña al envío se calcula sobre los
 * bytes comprimidos, igual que en el envío individual.
 *
 * @see https://siatinfo.impuestos.gob.bo/index.php/facturacion-en-linea/implementacion-servicios-facturacion/facturacion/recepcion-paquete-factura
 */
final class PaqueteFacturas
{
    /**
     * Construye el .tar.gz y devuelve sus bytes.
     *
     * @param  array<string, string>  $facturas  CUF => XML
     */
    public function construir(array $facturas): string
    {
        if ($facturas === []) {
            throw new SiatException('No hay facturas que empaquetar.');
        }

        if (! class_exists(PharData::class)) {
            throw new SiatException(
                'La extensión Phar de PHP no está habilitada y el SIN exige el paquete en formato .tar.gz. '
                . 'Habilite "extension=phar" en php.ini.'
            );
        }

        $base = $this->rutaTemporal();
        $tar  = $base . '.tar';
        $gz   = $tar . '.gz';

        // PharData deja el fichero abierto hasta que se destruye el objeto, y en
        // Windows eso impide borrarlo; de ahí el unset explícito antes de limpiar.
        try {
            $archivo = new PharData($tar);

            foreach ($facturas as $cuf => $xml) {
                $archivo->addFromString($this->nombreDe((string) $cuf), $xml);
            }

            $archivo->compress(\Phar::GZ);
            unset($archivo);

            $bytes = file_get_contents($gz);

            if ($bytes === false) {
                throw new SiatException('No se pudo leer el paquete comprimido de facturas.');
            }

            return $bytes;
        } catch (\UnexpectedValueException | \BadMethodCallException | \PharException $e) {
            throw new SiatException(
                'No se pudo construir el paquete de facturas: ' . $e->getMessage(),
                previous: $e,
            );
        } finally {
            foreach ([$tar, $gz] as $temporal) {
                if (is_file($temporal)) {
                    @unlink($temporal);
                }
            }
        }
    }

    /** El hash viaja junto al paquete y se calcula sobre los bytes comprimidos. */
    public function hash(string $paquete): string
    {
        return hash('sha256', $paquete);
    }

    /**
     * Abre un .tar.gz del SIN y devuelve su contenido.
     *
     * El tar se recorre a mano en vez de con `PharData` porque los que devuelve el
     * SIN no vienen rellenados hasta el bloque final que exige el formato, y Phar
     * los rechaza en bloque con "corrupted tar file (truncated)". El formato es
     * simple —cabecera de 512 bytes, datos alineados a 512— y leerlo así tolera
     * ese final abrupto sin perder ningún fichero.
     *
     * @return array<string, string> nombre => contenido
     */
    public function leer(string $paquete): array
    {
        $datos = str_starts_with($paquete, "\x1f\x8b") ? gzdecode($paquete) : $paquete;

        if ($datos === false) {
            throw new SiatException('El paquete recibido no es un gzip válido.');
        }

        $archivos = [];
        $posicion = 0;
        $largo    = strlen($datos);

        while ($posicion + 512 <= $largo) {
            $cabecera = substr($datos, $posicion, 512);
            $nombre   = trim(substr($cabecera, 0, 100), "\0 ");

            // Un nombre vacío marca el bloque de fin del archivo.
            if ($nombre === '') {
                break;
            }

            $tamano = (int) octdec(trim(substr($cabecera, 124, 12), "\0 "));
            $posicion += 512;

            $archivos[$nombre] = substr($datos, $posicion, $tamano);

            // Los datos de cada fichero se alinean al siguiente múltiplo de 512.
            $posicion += (int) ceil($tamano / 512) * 512;
        }

        return $archivos;
    }

    /**
     * Un nombre por factura. El CUF es hexadecimal, así que sirve tal cual como
     * nombre de fichero; se sanea igualmente por si llegara algo inesperado.
     */
    private function nombreDe(string $cuf): string
    {
        $limpio = preg_replace('/[^A-Za-z0-9]/', '', $cuf) ?: 'factura';

        return "{$limpio}.xml";
    }

    private function rutaTemporal(): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'siat');

        if ($ruta === false) {
            throw new SiatException('No se pudo crear el archivo temporal del paquete de facturas.');
        }

        // tempnam crea el fichero, y PharData exige que el .tar no exista todavía.
        unlink($ruta);

        return $ruta;
    }
}
