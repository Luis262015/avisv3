<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StockTransferRequest;
use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\Store;
use App\Services\StockTransferService;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class StockTransferController extends Controller
{
    public function __construct(private readonly StockTransferService $service) {}

    public function index(): Response
    {
        return Inertia::render('admin/stock-transfers/index', [
            'transfers' => StockTransfer::with(['fromStore', 'toStore', 'user'])
                ->latest()
                ->paginate(20),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/stock-transfers/create', [
            'stores'   => Store::where('is_active', true)->get(['id', 'name']),
            'products' => Product::where('status', 'active')
                ->where('track_inventory', true)
                ->get(['id', 'name', 'sku']),
        ]);
    }

    public function store(StockTransferRequest $request)
    {
        $transfer = $this->service->create(
            $request->safe()->except('items'),
            $request->items
        );

        return redirect()
            ->route('admin.stock-transfers.show', $transfer)
            ->with('success', "Transferencia {$transfer->folio} creada.");
    }

    public function show(StockTransfer $stockTransfer): Response
    {
        $stockTransfer->load(['fromStore', 'toStore', 'user', 'items.product']);

        return Inertia::render('admin/stock-transfers/show', [
            'transfer' => $stockTransfer,
        ]);
    }

    public function complete(StockTransfer $stockTransfer)
    {
        try {
            $this->service->complete($stockTransfer);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()
            ->route('admin.stock-transfers.show', $stockTransfer)
            ->with('success', "Transferencia {$stockTransfer->folio} completada. Stock actualizado.");
    }

    public function cancel(StockTransfer $stockTransfer)
    {
        try {
            $this->service->cancel($stockTransfer);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()
            ->route('admin.stock-transfers.show', $stockTransfer)
            ->with('success', "Transferencia {$stockTransfer->folio} cancelada.");
    }
}
