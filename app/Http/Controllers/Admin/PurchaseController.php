<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PurchaseReceiveRequest;
use App\Http\Requests\Admin\PurchaseRequest;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseOrder;
use App\Models\Store;
use App\Models\Supplier;
use App\Services\PurchaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseController extends Controller
{
    public function __construct(private readonly PurchaseService $service) {}

    public function index(): Response
    {
        return Inertia::render('admin/purchases/index', [
            'purchases' => Purchase::with(['supplier', 'user'])
                ->latest()
                ->paginate(20),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/purchases/create', [
            'suppliers'      => Supplier::where('is_active', true)->get(['id', 'name', 'payment_terms']),
            'stores'         => Store::where('is_active', true)->get(['id', 'name']),
            'products'       => Product::where('status', 'active')->get(['id', 'name', 'sku', 'cost']),
            'purchaseOrders' => PurchaseOrder::whereIn('status', ['confirmed', 'sent'])
                ->with('supplier:id,name')
                ->get(['id', 'folio', 'supplier_id', 'total', 'expected_date']),
        ]);
    }

    public function store(PurchaseRequest $request)
    {
        $purchase = $this->service->create(
            $request->safe()->except('items'),
            $request->items
        );
        return redirect()->route('admin.purchases.show', $purchase)
            ->with('success', 'Compra registrada.');
    }

    public function show(Purchase $purchase): Response
    {
        $purchase->load([
            'supplier',
            'user',
            'items.product',
            'payable.payments',
            'auditLogs.user',
            'purchaseOrder',
        ]);
        return Inertia::render('admin/purchases/show', compact('purchase'));
    }

    public function edit(Purchase $purchase): Response
    {
        return Inertia::render('admin/purchases/edit', [
            'purchase'  => $purchase->load(['supplier', 'store', 'items.product']),
            'suppliers' => Supplier::where('is_active', true)->get(['id', 'name', 'payment_terms']),
            'stores'    => Store::where('is_active', true)->get(['id', 'name']),
            'products'  => Product::where('status', 'active')->get(['id', 'name', 'sku', 'cost']),
        ]);
    }

    public function update(PurchaseRequest $request, Purchase $purchase)
    {
        $this->service->update(
            $purchase,
            $request->safe()->except('items'),
            $request->items
        );
        return redirect()->route('admin.purchases.show', $purchase)
            ->with('success', 'Compra actualizada.');
    }

    public function receive(Purchase $purchase)
    {
        if ($purchase->status !== 'pending') {
            return back()->withErrors(['status' => 'Solo se pueden recibir compras pendientes.']);
        }
        $this->service->receive($purchase);
        return redirect()->route('admin.purchases.show', $purchase)
            ->with('success', 'Compra recibida. Stock e inventario actualizados.');
    }

    public function receivePartial(PurchaseReceiveRequest $request, Purchase $purchase)
    {
        if (! in_array($purchase->status, ['pending', 'partial'])) {
            return back()->withErrors(['status' => 'Esta compra no admite recepciones adicionales.']);
        }
        $this->service->receivePartial($purchase, $request->items);
        return redirect()->route('admin.purchases.show', $purchase)
            ->with('success', 'Recepción parcial registrada.');
    }

    public function attachDocument(Request $request, Purchase $purchase)
    {
        $request->validate([
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        if ($purchase->document_path) {
            Storage::disk('private')->delete($purchase->document_path);
        }

        $path = $request->file('document')->store('purchases/documents', 'private');
        $this->service->attachDocument($purchase, $path);

        return back()->with('success', 'Documento adjuntado correctamente.');
    }

    public function cancel(Purchase $purchase)
    {
        if ($purchase->status === 'cancelled') {
            return back()->withErrors(['status' => 'La compra ya está cancelada.']);
        }
        $this->service->cancel($purchase);
        return redirect()->route('admin.purchases.show', $purchase)
            ->with('success', 'Compra cancelada.');
    }
}
