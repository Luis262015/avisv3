<?php

declare(strict_types=1);

namespace App\Services\Siat;

use App\Models\SiatCufdCode;
use App\Models\SiatEvento;
use App\Models\SiatInvoice;
use App\Models\SiatPaquete;
use App\Models\SiatSetting;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Facturación de contingencia: qué hacer cuando el SIN no está.
 *
 * El ciclo que exige la normativa tiene cuatro pasos y ninguno es opcional:
 *
 *   1. **Abrir el corte.** Se anota el momento en que dejó de haber servicio y el
 *      CUFD que estaba vigente. Desde ahí toda factura sale fuera de línea
 *      (tipoEmision 2) con el CAFC del Portal SIAT y queda en estado
 *      "contingencia", sin intentar enviarse.
 *   2. **Cerrar el corte** cuando vuelve la conexión.
 *   3. **Declararlo** con `registroEventoSignificativo`. El SIN devuelve un código
 *      de recepción que es el que justifica las facturas del corte.
 *   4. **Enviar el paquete** con esas facturas citando ese código, y consultar
 *      después si el SIN lo validó.
 *
 * Saltarse el paso 3 deja las facturas del corte sin respaldo ante Impuestos, así
 * que el empaquetado se niega a correr si el evento no está declarado.
 *
 * **Sobre el CAFC:** no va en toda factura fuera de línea, sino solo en los cortes
 * cuyo motivo lo exige. Enviarlo cuando no toca hace que el SIN observe el paquete
 * entero —verificado contra el piloto el 2026-08-12 con el motivo 2, que responde
 * «1045 VALOR DE CAFC NO VALIDO … Cafc esperado null»—. Por eso es un dato del
 * corte, opcional, y no una credencial de la configuración.
 */
final class SiatContingenciaService
{
    public function __construct(
        private readonly SiatOperacionesService $operaciones,
        private readonly SiatFacturacionService $facturacion,
        private readonly ConstructorFacturaXml $constructor,
        private readonly PaqueteFacturas $paquetes,
        private readonly CufdProvider $cufds,
    ) {}

    /** El corte en curso de una tienda, si lo hay. */
    public function eventoAbierto(int $storeId): ?SiatEvento
    {
        return SiatEvento::where('store_id', $storeId)->abierto()->latest()->first();
    }

    /**
     * El CUFD bajo el que se factura durante un corte.
     *
     * Es el que estaba vigente al empezar, no uno nuevo: pedir un CUFD implica
     * llamar al SIN, que es justo lo que no está disponible.
     */
    public function cufdDelEvento(SiatEvento $evento): SiatCufdCode
    {
        return $evento->cufdCode ?? throw new SiatException(
            'El corte abierto no tiene CUFD asociado, así que no se puede facturar fuera de línea. '
            . 'Ciérrelo y vuelva a abrirlo con un CUFD vigente.'
        );
    }

    /**
     * Declara localmente el inicio de un corte.
     *
     * @param  int  $codigoMotivo  De `sincronizarParametricaEventosSignificativos`.
     * @param  string|null  $cafc  Solo para los motivos que lo exigen; ver la nota
     *                             sobre el CAFC en la cabecera de esta clase.
     */
    public function abrir(
        SiatSetting $setting,
        int $codigoMotivo,
        string $descripcion,
        ?CarbonInterface $inicio = null,
        ?string $cafc = null,
    ): SiatEvento {
        if ($this->eventoAbierto($setting->store_id) !== null) {
            throw new SiatException('Ya hay un corte abierto para esta tienda; ciérrelo antes de abrir otro.');
        }

        // Sin CUFD vigente no hay con qué firmar el CUF de las facturas del corte,
        // y ya no se puede pedir uno porque el SIN está caído.
        $cufd = $this->cufds->activo($setting) ?? throw new SiatException(
            'No hay un CUFD vigente para esta tienda. El CUFD se pide con conexión y dura 24 horas: '
            . 'sin uno en vigor no se puede emitir fuera de línea.'
        );

        return SiatEvento::create([
            'store_id'             => $setting->store_id,
            'cufd_code_id'         => $cufd->id,
            'codigo_motivo_evento' => $codigoMotivo,
            'descripcion'          => $descripcion,
            'cafc'                 => $cafc,
            'fecha_inicio'         => $inicio ?? now(),
            'estado'               => 'abierto',
        ]);
    }

    /** Marca el fin del corte. A partir de aquí se vuelve a emitir en línea. */
    public function cerrar(SiatEvento $evento, ?CarbonInterface $fin = null): SiatEvento
    {
        if (! $evento->estaAbierto()) {
            throw new SiatException('Ese corte ya está cerrado.');
        }

        $evento->update(['fecha_fin' => $fin ?? now(), 'estado' => 'cerrado']);

        return $evento->refresh();
    }

    /**
     * Declara el corte ante el SIN y guarda el código de recepción del evento.
     */
    public function declarar(SiatEvento $evento, SiatSetting $setting): SiatEvento
    {
        if ($evento->estado === 'registrado') {
            return $evento;
        }

        if ($evento->estaAbierto()) {
            throw new SiatException('Cierre el corte antes de declararlo: el SIN exige la fecha de fin.');
        }

        // El SIN distingue el CUFD del corte del CUFD con el que se declara, que ya
        // es otro porque el anterior venció mientras no había servicio.
        $cufdEvento = $this->cufdDelEvento($evento);
        $cufdActual = $this->cufds->vigente($setting);

        try {
            $codigo = $this->operaciones->registrarEvento(
                setting: $setting,
                codigoMotivoEvento: (int) $evento->codigo_motivo_evento,
                descripcion: (string) $evento->descripcion,
                inicio: $evento->fecha_inicio,
                fin: $evento->fecha_fin ?? Carbon::now(),
                cufdEvento: $cufdEvento->codigo,
                cufd: $cufdActual->codigo,
            );
        } catch (SiatException $e) {
            $evento->update(['mensaje_error' => $e->getMessage()]);

            throw $e;
        }

        $evento->update([
            'codigo_recepcion_evento' => $codigo,
            'estado'                  => 'registrado',
            'mensaje_error'           => null,
        ]);

        return $evento->refresh();
    }

    /**
     * Empaqueta y envía al SIN las facturas emitidas durante el corte.
     *
     * El paquete se registra antes de salir: si el envío falla a mitad, queda la
     * constancia de qué facturas iban dentro en vez de un lote fantasma.
     */
    public function enviarPaquete(SiatEvento $evento, SiatSetting $setting): SiatPaquete
    {
        if ($evento->estado !== 'registrado' || blank($evento->codigo_recepcion_evento)) {
            throw new SiatException(
                'El corte todavía no está declarado ante el SIN. Sin el código de recepción del evento '
                . 'las facturas del paquete no tendrían con qué justificarse.'
            );
        }

        $facturas = $evento->invoices()
            ->where('estado', 'contingencia')
            ->with(['cufdCode', 'sale.user', 'sale.items.product'])
            ->get();

        if ($facturas->isEmpty()) {
            throw new SiatException('No quedan facturas de contingencia pendientes en este corte.');
        }

        $xmls = [];

        foreach ($facturas as $factura) {
            $xmls[$factura->cuf] = $this->constructor->construir(
                $factura,
                $setting,
                $factura->cufdCode ?? $this->cufdDelEvento($evento),
                $factura->fecha_emision ?? $factura->created_at,
            );
        }

        $archivo = $this->paquetes->construir($xmls);

        $paquete = SiatPaquete::create([
            'store_id'          => $evento->store_id,
            'evento_id'         => $evento->id,
            'tipo'              => 'paquete',
            'cantidad_facturas' => $facturas->count(),
            'hash_archivo'      => $this->paquetes->hash($archivo),
            'estado'            => 'pendiente',
        ]);

        try {
            $resultado = $this->facturacion->recepcionPaqueteFactura(
                setting: $setting,
                paquete: $archivo,
                cufd: $this->cufds->vigente($setting)->codigo,
                fechaEnvio: now(),
                // El paquete es homogéneo: todas las facturas del corte comparten
                // tipo, que es el que el SIN contrasta con cada CUF.
                tipoFacturaDocumento: (int) $facturas->first()->tipo_factura,
                cantidadFacturas: $facturas->count(),
                codigoEvento: (string) $evento->codigo_recepcion_evento,
                // El mismo CAFC que llevan sus facturas: el del corte, que puede
                // ser nulo según el motivo.
                cafc: $evento->cafc,
            );
        } catch (SiatException $e) {
            Log::error('SIAT: no se pudo enviar el paquete de contingencia', [
                'paquete_id' => $paquete->id,
                'evento_id'  => $evento->id,
                'error'      => $e->getMessage(),
            ]);

            $paquete->update(['estado' => 'rechazado', 'mensaje_error' => $e->getMessage()]);

            throw $e;
        }

        DB::transaction(function () use ($paquete, $facturas, $resultado): void {
            $paquete->update([
                'estado'           => 'enviado',
                'codigo_recepcion' => $resultado['codigoRecepcion'],
                'codigo_estado'    => $resultado['codigoEstado'],
                'enviado_at'       => now(),
                'mensaje_error'    => null,
            ]);

            SiatInvoice::whereIn('id', $facturas->pluck('id'))->update([
                'paquete_id'       => $paquete->id,
                'estado'           => 'enviada',
                'codigo_recepcion' => $resultado['codigoRecepcion'],
                'enviado_at'       => now(),
                'mensaje_error'    => null,
            ]);
        });

        return $paquete->refresh();
    }

    /**
     * Pregunta al SIN si validó el paquete.
     *
     * La recepción no es aceptación: un paquete recibido puede rechazarse después,
     * y hasta consultarlo no se sabe si las facturas quedaron en firme.
     *
     * @return array{codigoRecepcion: ?string, codigoEstado: ?int, codigoDescripcion: ?string, mensajes: list<string>, respuesta: array<string, mixed>}
     */
    public function validarPaquete(SiatPaquete $paquete, SiatSetting $setting): array
    {
        if (blank($paquete->codigo_recepcion)) {
            throw new SiatException('Ese paquete no llegó a enviarse, así que no hay nada que consultar.');
        }

        $tipoFactura = (int) ($paquete->invoices()->value('tipo_factura')
            ?? $setting->tipo_factura_default);

        $resultado = $paquete->tipo === 'masivo'
            ? $this->facturacion->validacionRecepcionMasiva(
                $setting, $paquete->codigo_recepcion, $this->cufds->vigente($setting)->codigo, $tipoFactura)
            : $this->facturacion->validacionRecepcionPaquete(
                $setting, $paquete->codigo_recepcion, $this->cufds->vigente($setting)->codigo, $tipoFactura);

        $validado = $resultado['codigoEstado'] === SiatFacturacionService::ESTADO_VALIDADA;

        $paquete->update([
            'estado'        => $validado ? 'validado' : 'enviado',
            'codigo_estado' => $resultado['codigoEstado'],
            'validado_at'   => $validado ? now() : null,
            'mensaje_error' => $resultado['mensajes'] ? implode(' | ', $resultado['mensajes']) : null,
        ]);

        return $resultado;
    }

    /**
     * Envío masivo: un lote de facturas emitidas con conexión (emisión 3).
     *
     * No nace de un corte, así que no lleva evento ni CAFC; sirve para puntos de
     * venta de alto volumen que agrupan sus envíos.
     *
     * @param  \Illuminate\Support\Collection<int, SiatInvoice>  $facturas
     */
    public function enviarMasivo(SiatSetting $setting, $facturas): SiatPaquete
    {
        if ($facturas->isEmpty()) {
            throw new SiatException('No hay facturas que enviar.');
        }

        $xmls = [];

        foreach ($facturas as $factura) {
            $factura->loadMissing(['cufdCode', 'sale.user', 'sale.items.product']);

            $xmls[$factura->cuf] = $this->constructor->construir(
                $factura,
                $setting,
                $factura->cufdCode ?? $this->cufds->vigente($setting),
                $factura->fecha_emision ?? $factura->created_at,
            );
        }

        $archivo = $this->paquetes->construir($xmls);

        $paquete = SiatPaquete::create([
            'store_id'          => $setting->store_id,
            'tipo'              => 'masivo',
            'cantidad_facturas' => $facturas->count(),
            'hash_archivo'      => $this->paquetes->hash($archivo),
            'estado'            => 'pendiente',
        ]);

        $resultado = $this->facturacion->recepcionMasivaFactura(
            setting: $setting,
            paquete: $archivo,
            cufd: $this->cufds->vigente($setting)->codigo,
            fechaEnvio: now(),
            tipoFacturaDocumento: (int) $facturas->first()->tipo_factura,
            cantidadFacturas: $facturas->count(),
        );

        DB::transaction(function () use ($paquete, $facturas, $resultado): void {
            $paquete->update([
                'estado'           => 'enviado',
                'codigo_recepcion' => $resultado['codigoRecepcion'],
                'codigo_estado'    => $resultado['codigoEstado'],
                'enviado_at'       => now(),
            ]);

            SiatInvoice::whereIn('id', $facturas->pluck('id'))->update([
                'paquete_id'       => $paquete->id,
                'estado'           => 'enviada',
                'codigo_recepcion' => $resultado['codigoRecepcion'],
                'enviado_at'       => now(),
                'mensaje_error'    => null,
            ]);
        });

        return $paquete->refresh();
    }
}
