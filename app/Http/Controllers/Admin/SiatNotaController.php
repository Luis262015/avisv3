<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SaleReturn;
use App\Models\SiatNota;
use App\Services\SiatNotaService;
use App\Services\SiatService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Notas de Crédito-Débito ante el SIN.
 *
 * Nacen de una devolución de venta, así que la emisión cuelga de ella; el resto
 * de acciones —reenvío, anulación, reversión y consulta— van sobre la nota, igual
 * que en las facturas.
 */
class SiatNotaController extends Controller
{
    public function __construct(private readonly SiatNotaService $notas) {}

    public function index(Request $request): Response
    {
        $query = SiatNota::with(['store:id,name', 'saleReturn:id,folio', 'invoice:id,numero_factura'])
            ->latest();

        if ($request->filled('estado')) {
            $query->where('estado', $request->string('estado'));
        }

        if ($request->filled('search')) {
            $buscado = $request->string('search');

            $query->where(function ($q) use ($buscado) {
                $q->where('numero_nota', 'like', "%{$buscado}%")
                    ->orWhere('cuf', 'like', "%{$buscado}%")
                    ->orWhere('nit_ci', 'like', "%{$buscado}%")
                    ->orWhere('nombre_razon_social', 'like', "%{$buscado}%");
            });
        }

        return Inertia::render('admin/siat/notas/index', [
            'notas'   => $query->paginate(20)->withQueryString(),
            'filtros' => $request->only('estado', 'search'),
        ]);
    }

    public function show(SiatNota $nota): Response
    {
        $nota->load([
            'store:id,name',
            'invoice:id,numero_factura,cuf,fecha_emision,importe_total',
            'saleReturn.items.product:id,name,sku',
            'saleReturn.sale:id,folio',
        ]);

        return Inertia::render('admin/siat/notas/show', [
            'nota'    => $nota,
            'sectores' => config('siat.nota.documentos_sector'),
        ]);
    }

    /**
     * Emite la nota de una devolución.
     *
     * El documento sector se deduce del descuento de la factura original; se
     * puede forzar por si el SIN pide otro en una prueba de homologación.
     */
    public function store(Request $request, SaleReturn $return)
    {
        $validado = $request->validate([
            'documento_sector' => ['nullable', 'integer', 'in:24,47'],
        ]);

        try {
            $nota = $this->notas->emitir($return, $validado['documento_sector'] ?? null);
        } catch (\Throwable $e) {
            return back()->withErrors(['siat' => $e->getMessage()]);
        }

        if ($nota->estado === 'rechazada') {
            return redirect()->route('admin.siat.notas.show', $nota)
                ->withErrors(['siat' => $nota->mensaje_error]);
        }

        return redirect()->route('admin.siat.notas.show', $nota)
            ->with('success', "Nota #{$nota->numero_nota} enviada al SIN.");
    }

    public function resend(SiatNota $nota)
    {
        if ($nota->estado === 'anulada') {
            return back()->withErrors(['siat' => 'No se puede reenviar una nota anulada.']);
        }

        try {
            $resultado = $this->notas->reenviar($nota);
        } catch (\Throwable $e) {
            return back()->withErrors(['siat' => $e->getMessage()]);
        }

        return back()->with(
            'success',
            'Nota enviada al SIN: ' . ($resultado['codigoDescripcion'] ?? 'recibida')
            . " (recepción {$resultado['codigoRecepcion']})."
        );
    }

    public function cancel(Request $request, SiatNota $nota)
    {
        $validado = $request->validate([
            'codigo_motivo' => ['nullable', 'integer', 'min:1', 'max:4'],
        ]);

        try {
            $this->notas->anular(
                $nota,
                $validado['codigo_motivo'] ?? SiatService::ANULACION_NOTA_MAL_EMITIDA,
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['siat' => $e->getMessage()]);
        }

        return back()->with('success', 'Nota anulada ante el SIN.');
    }

    public function revertCancellation(SiatNota $nota)
    {
        try {
            $resultado = $this->notas->revertirAnulacion($nota);
        } catch (\Throwable $e) {
            return back()->withErrors(['siat' => $e->getMessage()]);
        }

        return back()->with(
            'success',
            'Anulación revertida: ' . ($resultado['codigoDescripcion'] ?? 'la nota vuelve a estar vigente') . '.'
        );
    }

    public function checkStatus(SiatNota $nota)
    {
        try {
            $resultado = $this->notas->consultarEstado($nota);
        } catch (\Throwable $e) {
            return back()->withErrors(['siat' => $e->getMessage()]);
        }

        return back()->with(
            'success',
            'Estado en el SIN: ' . ($resultado['codigoDescripcion'] ?? 'sin descripción')
            . ' (' . $resultado['codigoEstado'] . ').'
        );
    }
}
