<?php

declare(strict_types=1);

namespace App\Services\Siat;

use App\Models\Purchase;
use App\Models\SiatPaquete;
use App\Models\SiatSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Declaración del Registro de Compras por periodo.
 *
 * Arma el .tar.gz con un XML `registroCompra` por compra, lo manda por
 * {@see SiatComprasService} y deja constancia local de qué se declaró y cuándo.
 * Es a las compras lo que {@see SiatContingenciaService} es a los lotes de venta.
 */
final class RegistroComprasService
{
    public function __construct(
        private readonly SiatComprasService $compras,
        private readonly RegistroCompraXml $xml,
        private readonly PaqueteFacturas $paquetes,
    ) {}

    /**
     * Compras declarables de un periodo, con lo que le falta a cada una.
     *
     * La pantalla necesita listarlo todo junto: descubrir los datos que faltan de
     * una excepción en una haría el cierre de periodo insufrible.
     *
     * @param  Collection<int, Purchase>  $compras
     * @return array<int, list<string>>  id de la compra => problemas
     */
    public function revisar(Collection $compras): array
    {
        return $compras
            ->mapWithKeys(fn (Purchase $c): array => [$c->id => $this->xml->problemas($c)])
            ->filter(fn (array $problemas): bool => $problemas !== [])
            ->all();
    }

    /**
     * Empaqueta y envía las compras de un periodo.
     *
     * @param  Collection<int, Purchase>  $compras
     * @param  int  $periodo  Mes, de 1 a 12.
     */
    public function enviarPeriodo(
        SiatSetting $setting,
        Collection $compras,
        int $gestion,
        int $periodo,
    ): SiatPaquete {
        if ($compras->isEmpty()) {
            throw new SiatException('No hay compras que declarar en ese periodo.');
        }

        $archivos = [];
        $nro      = 1;

        foreach ($compras as $compra) {
            if ($problemas = $this->xml->problemas($compra)) {
                throw new SiatException(
                    "La compra {$compra->folio} no se puede declarar: " . implode('; ', $problemas) . '.'
                );
            }

            // El nombre no puede ser el CUF como en ventas: una compra se
            // identifica por su correlativo dentro del paquete.
            $archivos["compra{$nro}"] = $this->xml->build($compra, $nro);
            $nro++;
        }

        $archivo = $this->paquetes->construir($archivos);

        // El paquete se registra antes de salir: si el envío falla a mitad, queda
        // constancia de qué compras iban dentro.
        $paquete = SiatPaquete::create([
            'store_id'          => $setting->store_id,
            'tipo'              => 'compras',
            'gestion'           => $gestion,
            'periodo'           => $periodo,
            'cantidad_facturas' => $compras->count(),
            'hash_archivo'      => $this->paquetes->hash($archivo),
            'estado'            => 'pendiente',
        ]);

        try {
            $resultado = $this->compras->recepcionPaqueteCompras(
                $setting, $archivo, $compras->count(), $gestion, $periodo,
            );
        } catch (SiatException $e) {
            Log::error('SIAT: no se pudo enviar el paquete de compras', [
                'paquete_id' => $paquete->id,
                'error'      => $e->getMessage(),
            ]);

            $paquete->update(['estado' => 'rechazado', 'mensaje_error' => $e->getMessage()]);

            throw $e;
        }

        DB::transaction(function () use ($paquete, $compras, $resultado): void {
            $paquete->update([
                'estado'           => 'enviado',
                'codigo_recepcion' => $resultado['codigoRecepcion'],
                'codigo_estado'    => $resultado['codigoEstado'],
                'enviado_at'       => now(),
                'mensaje_error'    => null,
            ]);

            Purchase::whereIn('id', $compras->pluck('id'))->update(['paquete_id' => $paquete->id]);
        });

        return $paquete->refresh();
    }

    /**
     * Pregunta al SIN en qué estado quedó el paquete.
     *
     * @return array{codigoRecepcion: ?string, codigoEstado: ?int, codigoDescripcion: ?string, mensajes: list<string>, respuesta: array<string, mixed>}
     */
    public function validarPaquete(SiatPaquete $paquete, SiatSetting $setting): array
    {
        if (blank($paquete->codigo_recepcion)) {
            throw new SiatException('Ese paquete no llegó a enviarse, así que no hay nada que consultar.');
        }

        $resultado = $this->compras->validacionRecepcionPaqueteCompras($setting, $paquete->codigo_recepcion);

        // El Registro de Compras no comparte la tabla de estados de la facturación
        // y el SIN no la publica en la página de la operación: el piloto devuelve
        // 5152 de forma estable tras aceptar un paquete. Se guarda el código tal
        // cual y solo se da por validado el 908, que sí está documentado; inventar
        // una equivalencia haría que la pantalla mintiera sobre el estado real.
        $validado = $resultado['codigoEstado'] === SiatFacturacionService::ESTADO_VALIDADA;

        $paquete->update([
            'estado'        => $validado ? 'validado' : 'enviado',
            'codigo_estado' => $resultado['codigoEstado'],
            'validado_at'   => $validado ? now() : null,
            'mensaje_error' => $resultado['mensajes'] ? implode(' | ', $resultado['mensajes']) : null,
        ]);

        return $resultado;
    }
}
