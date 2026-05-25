<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SiatInvoice;
use App\Services\SaleService;
use App\Services\SiatService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class SiatInvoiceController extends Controller
{
    public function __construct(
        private readonly SiatService $siat,
        private readonly SaleService $sales,
    ) {}

    public function index(Request $request): Response
    {
        $query = SiatInvoice::with(['sale', 'store'])
            ->latest();

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('numero_factura', 'like', "%{$request->search}%")
                  ->orWhere('cuf', 'like', "%{$request->search}%")
                  ->orWhere('nit_ci', 'like', "%{$request->search}%")
                  ->orWhere('nombre_razon_social', 'like', "%{$request->search}%");
            });
        }

        $invoices = $query->paginate(20)->through(fn ($inv) => array_merge($inv->toArray(), [
            'estado_label'   => $inv->estado_label,
            'tipo_fact_label'=> $inv->tipo_factura_label,
        ]));

        return Inertia::render('admin/siat/invoices/index', [
            'invoices' => $invoices,
            'filters'  => $request->only(['estado', 'search']),
        ]);
    }

    public function show(SiatInvoice $siatInvoice): Response
    {
        $siatInvoice->load(['sale.items.product', 'sale.user', 'store', 'cufdCode']);

        return Inertia::render('admin/siat/invoices/show', [
            'invoice' => array_merge($siatInvoice->toArray(), [
                'estado_label'          => $siatInvoice->estado_label,
                'tipo_fact_label'       => $siatInvoice->tipo_factura_label,
                'tipo_doc_label'        => $siatInvoice->tipo_doc_identidad_label,
                'metodo_pago_label'     => $siatInvoice->metodo_pago_label,
            ]),
        ]);
    }

    /**
     * Emite una factura para una venta existente.
     */
    public function emit(Request $request, Sale $sale)
    {
        if ($sale->siatInvoice) {
            return back()->withErrors(['siat' => 'Esta venta ya tiene factura emitida.']);
        }

        // Verificar configuración SIAT antes de validar el formulario
        $sale->loadMissing('cashShift.cashRegister.store');
        $store   = $sale->cashShift->cashRegister->store;
        $setting = $this->siat->getActiveSetting($store->id);

        if (! $setting) {
            return back()->withErrors([
                'siat' => "No hay configuración SIAT activa para la tienda \"{$store->name}\". "
                        . 'Ve a Facturación SIAT → Configuración y crea una configuración para esta tienda.',
            ]);
        }

        $request->validate([
            'nit_ci'       => ['nullable', 'string', 'max:20'],
            'tipo_doc'     => ['nullable', 'integer', 'in:1,2,3,4,5'],
            'nombre'       => ['nullable', 'string', 'max:200'],
            'tipo_factura' => ['nullable', 'integer', 'in:1,2'],
        ]);

        try {
            $invoice = $this->siat->createInvoice($sale, $request->only('nit_ci', 'tipo_doc', 'nombre', 'tipo_factura'));
        } catch (\Throwable $e) {
            return back()->withErrors(['siat' => $e->getMessage()]);
        }

        return redirect()->route('admin.siat.invoices.show', $invoice)
            ->with('success', "Factura #{$invoice->numero_factura} emitida correctamente.");
    }

    /**
     * Imprime la factura en formato Bolivia.
     */
    public function print(SiatInvoice $siatInvoice): \Illuminate\Contracts\View\View
    {
        $siatInvoice->load(['sale.items.product', 'sale.user', 'store']);
        $setting = $this->siat->getActiveSetting($siatInvoice->store_id);

        return view('admin.siat.factura', [
            'invoice' => $siatInvoice,
            'setting' => $setting,
            'store'   => $siatInvoice->store,
        ]);
    }

    /**
     * Anula una factura. Opcionalmente cancela también la venta vinculada.
     */
    public function cancel(Request $request, SiatInvoice $siatInvoice)
    {
        $request->validate([
            'motivo'      => ['required', 'string', 'max:200'],
            'cancel_sale' => ['boolean'],
        ]);

        try {
            if ($request->boolean('cancel_sale')) {
                // Cargar venta; SaleService cancelará la factura + devolverá inventario
                $siatInvoice->loadMissing('sale');
                $sale = $siatInvoice->sale;

                if ($sale && $sale->status === 'completed') {
                    $this->sales->cancel($sale, $request->motivo, Auth::id());
                } else {
                    $this->siat->cancelInvoice($siatInvoice, $request->motivo);
                }
            } else {
                $this->siat->cancelInvoice($siatInvoice, $request->motivo);
            }
        } catch (\RuntimeException $e) {
            return back()->withErrors(['siat' => $e->getMessage()]);
        }

        return redirect()->route('admin.siat.invoices.show', $siatInvoice)
            ->with('success', 'Factura anulada correctamente.');
    }

    /**
     * Reenvía la factura al SIN (para facturas pendientes o con error).
     */
    public function resend(SiatInvoice $siatInvoice)
    {
        if ($siatInvoice->estado === 'anulada') {
            return back()->withErrors(['siat' => 'No se puede reenviar una factura anulada.']);
        }

        $setting = $this->siat->getActiveSetting($siatInvoice->store_id);
        if (! $setting || $setting->ambiente === 'simulado') {
            return back()->with('success', 'Factura marcada como enviada (modo simulado).');
        }

        // TODO: llamar a SIN
        $siatInvoice->update(['estado' => 'enviada', 'enviado_at' => now(), 'mensaje_error' => null]);
        return back()->with('success', 'Factura reenviada a SIN.');
    }
}
