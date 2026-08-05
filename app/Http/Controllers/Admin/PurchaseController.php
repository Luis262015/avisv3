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

    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'status', 'payment_status', 'supplier_id', 'store_id', 'from', 'to']);

        $purchases = Purchase::query()
            ->with(['supplier:id,name', 'store:id,name', 'user:id,name'])
            ->when($filters['search'] ?? null, fn($q, $v) => $q->where(
                fn($q2) => $q2->where('folio', 'like', "%{$v}%")
                    ->orWhere('invoice_number', 'like', "%{$v}%")
            ))
            ->when($filters['status'] ?? null, fn($q, $v) => $q->where('status', $v))
            ->when($filters['payment_status'] ?? null, fn($q, $v) => $q->where('payment_status', $v))
            ->when($filters['supplier_id'] ?? null, fn($q, $v) => $q->where('supplier_id', $v))
            ->when($filters['store_id'] ?? null, fn($q, $v) => $q->where('store_id', $v))
            ->when($filters['from'] ?? null, fn($q, $v) => $q->whereDate('date', '>=', $v))
            ->when($filters['to'] ?? null, fn($q, $v) => $q->whereDate('date', '<=', $v))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/purchases/index', [
            'purchases' => $purchases,
            'filters'   => $filters,
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'stores'    => Store::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/purchases/create', [
            'suppliers'      => Supplier::where('is_active', true)->get(['id', 'name', 'payment_terms']),
            'stores'         => Store::where('is_active', true)->get(['id', 'name']),
            'products'       => Product::where('status', 'active')->get(['id', 'name', 'sku', 'cost']),
            'purchaseOrders' => PurchaseOrder::whereIn('status', ['confirmed', 'sent'])
                ->whereDoesntHave('purchases', fn($q) => $q->where('status', '!=', 'cancelled'))
                ->with('supplier:id,name')
                ->get(['id', 'folio', 'supplier_id', 'store_id', 'total', 'expected_date']),
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
            'store',
            'user',
            'items.product:id,name,sku',
            'payable.payments.user:id,name',
            'auditLogs.user:id,name',
            'purchaseOrder:id,folio,status',
            'inventoryMovements',
        ]);

        return Inertia::render('admin/purchases/show', compact('purchase'));
    }

    public function edit(Purchase $purchase): Response|\Illuminate\Http\RedirectResponse
    {
        if (! $purchase->isEditable()) {
            return redirect()->route('admin.purchases.show', $purchase)
                ->withErrors(['status' => 'Solo pueden editarse compras pendientes.']);
        }

        return Inertia::render('admin/purchases/edit', [
            'purchase'  => $purchase->load(['supplier', 'store', 'items.product']),
            'suppliers' => Supplier::where('is_active', true)->get(['id', 'name', 'payment_terms']),
            'stores'    => Store::where('is_active', true)->get(['id', 'name']),
            'products'  => Product::where('status', 'active')->get(['id', 'name', 'sku', 'cost']),
        ]);
    }

    public function update(PurchaseRequest $request, Purchase $purchase)
    {
        try {
            $this->service->update(
                $purchase,
                $request->safe()->except('items'),
                $request->items
            );
        } catch (\RuntimeException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return redirect()->route('admin.purchases.show', $purchase)
            ->with('success', 'Compra actualizada.');
    }

    public function receive(Purchase $purchase)
    {
        if (! $purchase->isReceivable()) {
            return back()->withErrors(['status' => 'Esta compra no admite recepciones.']);
        }

        try {
            $this->service->receive($purchase);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return redirect()->route('admin.purchases.show', $purchase)
            ->with('success', 'Compra recibida. Stock e inventario actualizados.');
    }

    public function receivePartial(PurchaseReceiveRequest $request, Purchase $purchase)
    {
        if (! $purchase->isReceivable()) {
            return back()->withErrors(['status' => 'Esta compra no admite recepciones adicionales.']);
        }

        try {
            $this->service->receivePartial($purchase, $request->items);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

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

        try {
            $this->service->cancel($purchase);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return redirect()->route('admin.purchases.show', $purchase)
            ->with('success', 'Compra cancelada.');
    }
}
