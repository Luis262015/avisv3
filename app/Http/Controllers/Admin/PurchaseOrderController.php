<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PurchaseOrderRequest;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Store;
use App\Models\Supplier;
use App\Services\PurchaseOrderService;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseOrderController extends Controller
{
    public function __construct(private readonly PurchaseOrderService $service) {}

    public function index(): Response
    {
        return Inertia::render('admin/purchase-orders/index', [
            'orders' => PurchaseOrder::with(['supplier', 'user'])
                ->latest()
                ->paginate(20),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/purchase-orders/create', [
            'suppliers' => Supplier::where('is_active', true)->get(['id', 'name', 'payment_terms', 'lead_time_days']),
            'stores'    => Store::where('is_active', true)->get(['id', 'name']),
            'products'  => Product::where('status', 'active')->get(['id', 'name', 'sku', 'cost']),
        ]);
    }

    public function store(PurchaseOrderRequest $request)
    {
        $order = $this->service->create(
            $request->safe()->except('items'),
            $request->items
        );
        return redirect()->route('admin.purchase-orders.show', $order)
            ->with('success', 'Orden de compra creada.');
    }

    public function show(PurchaseOrder $purchaseOrder): Response
    {
        $purchaseOrder->load(['supplier', 'store', 'user', 'items.product', 'purchases']);
        return Inertia::render('admin/purchase-orders/show', [
            'order' => $purchaseOrder,
        ]);
    }

    public function edit(PurchaseOrder $purchaseOrder): Response
    {
        if (! $purchaseOrder->isEditable()) {
            return redirect()->route('admin.purchase-orders.show', $purchaseOrder)
                ->withErrors(['status' => 'Esta orden no puede editarse.']);
        }

        return Inertia::render('admin/purchase-orders/edit', [
            'order'     => $purchaseOrder->load(['supplier', 'store', 'items.product']),
            'suppliers' => Supplier::where('is_active', true)->get(['id', 'name', 'payment_terms', 'lead_time_days']),
            'stores'    => Store::where('is_active', true)->get(['id', 'name']),
            'products'  => Product::where('status', 'active')->get(['id', 'name', 'sku', 'cost']),
        ]);
    }

    public function update(PurchaseOrderRequest $request, PurchaseOrder $purchaseOrder)
    {
        $this->service->update(
            $purchaseOrder,
            $request->safe()->except('items'),
            $request->items
        );
        return redirect()->route('admin.purchase-orders.show', $purchaseOrder)
            ->with('success', 'Orden de compra actualizada.');
    }

    public function confirm(PurchaseOrder $purchaseOrder)
    {
        try {
            $this->service->confirm($purchaseOrder);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }
        return redirect()->route('admin.purchase-orders.show', $purchaseOrder)
            ->with('success', 'Orden confirmada.');
    }

    public function markSent(PurchaseOrder $purchaseOrder)
    {
        try {
            $this->service->markSent($purchaseOrder);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }
        return redirect()->route('admin.purchase-orders.show', $purchaseOrder)
            ->with('success', 'Orden marcada como enviada al proveedor.');
    }

    public function convert(PurchaseOrder $purchaseOrder)
    {
        try {
            $purchase = $this->service->convertToPurchase($purchaseOrder);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }
        return redirect()->route('admin.purchases.show', $purchase)
            ->with('success', 'Orden convertida en compra exitosamente.');
    }

    public function cancel(PurchaseOrder $purchaseOrder)
    {
        try {
            $this->service->cancel($purchaseOrder);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }
        return redirect()->route('admin.purchase-orders.show', $purchaseOrder)
            ->with('success', 'Orden cancelada.');
    }
}
