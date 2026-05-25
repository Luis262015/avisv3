<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Store;
use App\Models\StoreStock;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    public function __construct(private readonly InventoryService $service) {}

    public function index(): Response
    {
        return Inertia::render('admin/inventory/index', [
            'movements' => InventoryMovement::with(['product', 'user', 'store'])
                ->latest()
                ->paginate(30),
            'lowStock'  => Product::where('status', 'active')
                ->whereColumn('stock', '<=', 'min_stock')
                ->where('min_stock', '>', 0)
                ->with('primaryImage')
                ->get(),
            'stores'    => Store::where('is_active', true)->get(['id', 'name']),
            'storeStocks' => StoreStock::with(['store', 'product.primaryImage'])
                ->get()
                ->groupBy('store_id'),
        ]);
    }

    public function adjust(Request $request)
    {
        $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'store_id'   => ['nullable', 'exists:stores,id'],
            'new_stock'  => ['required', 'integer', 'min:0'],
            'reason'     => ['required', 'string', 'max:255'],
        ]);

        $product = Product::findOrFail($request->product_id);
        $this->service->adjust(
            $product,
            $request->new_stock,
            $request->reason,
            $request->store_id ? (int) $request->store_id : null
        );

        return back()->with('success', 'Inventario ajustado.');
    }
}
