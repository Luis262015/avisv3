<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaleRequest;
use App\Http\Requests\Admin\SaleUpdateRequest;
use App\Models\CashShift;
use App\Models\Product;
use App\Models\Sale;
use App\Services\SaleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class SaleController extends Controller
{
    public function __construct(private readonly SaleService $service) {}

    public function index(): Response
    {
        return Inertia::render('admin/sales/index', [
            'sales' => Sale::with(['cashShift.cashRegister.store', 'user'])->latest()->paginate(20),
        ]);
    }

    public function create(): Response
    {
        $activeShift = CashShift::where('user_id', Auth::id())
            ->where('status', 'open')
            ->with('cashRegister.store')
            ->latest()
            ->first();

        return Inertia::render('admin/sales/create', [
            'activeShift' => $activeShift,
            'openShifts'  => CashShift::where('status', 'open')->with('cashRegister.store')->get(),
            'products'    => Product::where('status', 'active')->with('primaryImage')->get(['id', 'name', 'sku', 'barcode', 'price', 'stock', 'track_inventory']),
        ]);
    }

    public function store(SaleRequest $request)
    {
        $shift = CashShift::findOrFail($request->cash_shift_id);
        $sale  = $this->service->create($shift, $request->safe()->except('items'), $request->items);
        return redirect()->route('admin.sales.show', $sale)
            ->with('success', 'Venta registrada.')
            ->with('print_receipt', true);
    }

    public function receipt(Sale $sale): \Illuminate\Contracts\View\View
    {
        $sale->load(['cashShift.cashRegister.store', 'user', 'items.product']);
        return view('admin.sales.receipt', [
            'sale'          => $sale,
            'store'         => $sale->cashShift->cashRegister->store,
            'cashRegister'  => $sale->cashShift->cashRegister,
        ]);
    }

    public function show(Sale $sale): Response
    {
        $sale->load(['cashShift.cashRegister.store', 'user', 'items.product', 'siatInvoice']);

        $siatInvoice = $sale->siatInvoice ? array_merge($sale->siatInvoice->toArray(), [
            'estado_label'    => $sale->siatInvoice->estado_label,
            'tipo_fact_label' => $sale->siatInvoice->tipo_factura_label,
        ]) : null;

        return Inertia::render('admin/sales/show', [
            'sale'        => $sale,
            'siatInvoice' => $siatInvoice,
        ]);
    }

    public function edit(Sale $sale): Response
    {
        $sale->load(['items.product']);
        return Inertia::render('admin/sales/edit', [
            'sale'     => $sale,
            'products' => Product::where('status', 'active')->with('primaryImage')->get(['id', 'name', 'sku', 'barcode', 'price', 'stock', 'track_inventory']),
        ]);
    }

    public function update(SaleUpdateRequest $request, Sale $sale)
    {
        $this->service->update(
            $sale,
            $request->safe()->except('items'),
            $request->items
        );
        return redirect()->route('admin.sales.show', $sale)->with('success', 'Venta actualizada.');
    }

    public function cancel(Request $request, Sale $sale)
    {
        if ($sale->status !== 'completed') {
            return back()->withErrors(['status' => 'Solo se pueden cancelar ventas completadas.']);
        }

        $request->validate([
            'motivo' => ['nullable', 'string', 'max:200'],
        ]);

        try {
            $this->service->cancel($sale, $request->motivo ?? '', Auth::id());
        } catch (\RuntimeException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return redirect()->route('admin.sales.show', $sale)->with('success', 'Venta cancelada correctamente.');
    }
}
