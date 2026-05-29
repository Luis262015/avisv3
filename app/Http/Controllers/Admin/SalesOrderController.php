<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SalesOrderRequest;
use App\Http\Requests\Admin\ShipmentRequest;
use App\Models\CashShift;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Quote;
use App\Models\SalesOrder;
use App\Services\SalesOrderService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SalesOrderController extends Controller
{
    public function __construct(private readonly SalesOrderService $service) {}

    public function index(): Response
    {
        return Inertia::render('admin/sales-orders/index', [
            'orders' => SalesOrder::with(['customer', 'user', 'shipment'])->latest()->paginate(20),
        ]);
    }

    public function create(Request $request): Response
    {
        $fromQuote = null;
        if ($request->filled('quote_id')) {
            $fromQuote = Quote::with('items.product')->find($request->quote_id);
        }

        return Inertia::render('admin/sales-orders/create', [
            'customers' => Customer::active()->get(['id', 'name']),
            'products'  => Product::where('status', 'active')->get(['id', 'name', 'sku', 'price']),
            'fromQuote' => $fromQuote,
        ]);
    }

    public function store(SalesOrderRequest $request)
    {
        $order = $this->service->create($request->safe()->except('items'), $request->items);
        return redirect()->route('admin.sales-orders.show', $order)->with('success', 'Pedido creado.');
    }

    public function show(SalesOrder $salesOrder): Response
    {
        $salesOrder->load(['customer', 'user', 'items.product', 'shipment', 'sale', 'quote']);

        return Inertia::render('admin/sales-orders/show', [
            'order'      => $salesOrder,
            'openShifts' => CashShift::where('status', 'open')->with('cashRegister.store')->get(),
        ]);
    }

    public function edit(SalesOrder $salesOrder): Response
    {
        if (! $salesOrder->isEditable()) {
            return redirect()->route('admin.sales-orders.show', $salesOrder)
                ->withErrors(['status' => 'Este pedido no puede editarse.']);
        }

        return Inertia::render('admin/sales-orders/edit', [
            'order'     => $salesOrder->load(['customer', 'items.product']),
            'customers' => Customer::active()->get(['id', 'name']),
            'products'  => Product::where('status', 'active')->get(['id', 'name', 'sku', 'price']),
        ]);
    }

    public function update(SalesOrderRequest $request, SalesOrder $salesOrder)
    {
        try {
            $this->service->update($salesOrder, $request->safe()->except('items'), $request->items);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }
        return redirect()->route('admin.sales-orders.show', $salesOrder)->with('success', 'Pedido actualizado.');
    }

    public function confirm(SalesOrder $salesOrder)
    {
        return $this->transition(fn() => $this->service->confirm($salesOrder), $salesOrder, 'Pedido confirmado.');
    }

    public function prepare(SalesOrder $salesOrder)
    {
        return $this->transition(fn() => $this->service->markPreparing($salesOrder), $salesOrder, 'Pedido en preparación.');
    }

    public function ship(ShipmentRequest $request, SalesOrder $salesOrder)
    {
        return $this->transition(fn() => $this->service->ship($salesOrder, $request->validated()), $salesOrder, 'Envío registrado.');
    }

    public function cancel(SalesOrder $salesOrder)
    {
        return $this->transition(fn() => $this->service->cancel($salesOrder), $salesOrder, 'Pedido cancelado.');
    }

    public function deliver(Request $request, SalesOrder $salesOrder)
    {
        $data = $request->validate([
            'cash_shift_id'  => ['required', 'exists:cash_shifts,id'],
            'payment_method' => ['required', 'in:cash,card,transfer,mixed'],
            'amount_paid'    => ['required', 'numeric', 'min:0'],
        ]);

        $shift = CashShift::findOrFail($data['cash_shift_id']);

        try {
            $sale = $this->service->deliver($salesOrder, $shift, $data);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return redirect()->route('admin.sales.show', $sale)
            ->with('success', 'Pedido entregado y venta generada.');
    }

    private function transition(callable $action, SalesOrder $order, string $message)
    {
        try {
            $action();
        } catch (\RuntimeException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }
        return redirect()->route('admin.sales-orders.show', $order)->with('success', $message);
    }
}
