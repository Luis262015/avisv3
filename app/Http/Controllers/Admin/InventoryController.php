<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\InventoryAdjustRequest;
use App\Http\Requests\Admin\StoreMinStockRequest;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Store;
use App\Models\StoreStock;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    public function __construct(private readonly InventoryService $service) {}

    public function index(Request $request): Response
    {
        $filtros = $request->validate([
            'store_id'   => ['nullable', 'integer', 'exists:stores,id'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'type'       => ['nullable', 'string', 'in:in,out,return,adjustment,transfer_in,transfer_out'],
            'from'       => ['nullable', 'date'],
            'to'         => ['nullable', 'date'],
        ]);

        // `validate()` comprueba pero no convierte: lo que viene de la query string
        // sigue siendo texto, y los métodos de abajo esperan enteros.
        $storeId   = isset($filtros['store_id']) ? (int) $filtros['store_id'] : null;
        $productId = isset($filtros['product_id']) ? (int) $filtros['product_id'] : null;

        $filtros['store_id']   = $storeId;
        $filtros['product_id'] = $productId;

        return Inertia::render('admin/inventory/index', [
            'movements'   => $this->historial($filtros),
            'lowStock'    => $this->stockBajo($storeId),
            'stores'      => Store::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'products'    => Product::where('status', 'active')->orderBy('name')->get(['id', 'name', 'sku']),
            'storeStocks' => $this->existenciasPorTienda($storeId),
            'filters'     => $filtros,
        ]);
    }

    public function adjust(InventoryAdjustRequest $request): RedirectResponse
    {
        $product = Product::findOrFail($request->validated('product_id'));

        $this->service->adjust(
            $product,
            (int) $request->validated('new_stock'),
            $request->validated('reason'),
            (int) $request->validated('store_id'),
        );

        return back()->with('success', 'Inventario ajustado.');
    }

    /** Fija o retira el mínimo propio de una tienda. */
    public function setMinStock(StoreMinStockRequest $request): RedirectResponse
    {
        $product  = Product::findOrFail($request->validated('product_id'));
        $minStock = $request->validated('min_stock');

        $this->service->setMinimoTienda(
            $product,
            (int) $request->validated('store_id'),
            $minStock === null ? null : (int) $minStock,
        );

        return back()->with(
            'success',
            $minStock === null
                ? 'La tienda vuelve a usar el mínimo general del producto.'
                : 'Mínimo de la tienda actualizado.'
        );
    }

    /**
     * Historial de movimientos. Es el registro de todo lo que entró y salió, y la
     * única forma de reconstruir por qué una tienda tiene las existencias que
     * tiene, así que se filtra pero nunca se recorta a la fuerza.
     *
     * @param  array<string, mixed>  $filtros
     */
    private function historial(array $filtros)
    {
        return InventoryMovement::query()
            ->with(['product:id,name,sku', 'user:id,name', 'store:id,name'])
            ->when($filtros['store_id'] ?? null, fn ($q, $v) => $q->where('store_id', $v))
            ->when($filtros['product_id'] ?? null, fn ($q, $v) => $q->where('product_id', $v))
            ->when($filtros['type'] ?? null, fn ($q, $v) => $q->where('type', $v))
            ->when($filtros['from'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filtros['to'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->latest('id')
            ->paginate(30)
            ->withQueryString();
    }

    /**
     * Productos por debajo de su mínimo, evaluado tienda por tienda.
     *
     * Antes se comparaba el total de la empresa contra el mínimo del producto, lo
     * que escondía que una sucursal estuviera vacía mientras otra tenía de sobra.
     */
    private function stockBajo(?int $storeId)
    {
        return StoreStock::query()
            ->join('products', 'products.id', '=', 'store_product_stocks.product_id')
            ->join('stores', 'stores.id', '=', 'store_product_stocks.store_id')
            ->where('products.status', 'active')
            ->where('products.track_inventory', true)
            ->when($storeId, fn ($q, $v) => $q->where('store_product_stocks.store_id', $v))
            ->whereRaw(StoreStock::minimoEfectivoSql() . ' > 0')
            ->whereRaw('store_product_stocks.stock <= ' . StoreStock::minimoEfectivoSql())
            ->orderBy('store_product_stocks.stock')
            ->get([
                'store_product_stocks.id as id',
                'products.id as product_id',
                'products.name as name',
                'products.sku as sku',
                'stores.id as store_id',
                'stores.name as store_name',
                'store_product_stocks.stock as stock',
                'store_product_stocks.min_stock as min_propio',
                'products.min_stock as min_general',
            ])
            ->map(fn ($f): array => [
                'id'          => (int) $f->id,
                'product_id'  => (int) $f->product_id,
                'name'        => (string) $f->name,
                'sku'         => $f->sku,
                'store_id'    => (int) $f->store_id,
                'store_name'  => (string) $f->store_name,
                'stock'       => (int) $f->stock,
                'min_stock'   => (int) ($f->min_propio ?? $f->min_general),
                'min_propio'  => $f->min_propio === null ? null : (int) $f->min_propio,
            ]);
    }

    private function existenciasPorTienda(?int $storeId)
    {
        return StoreStock::query()
            ->with(['store:id,name', 'product:id,name,sku,min_stock'])
            ->when($storeId, fn ($q, $v) => $q->where('store_id', $v))
            ->get()
            ->groupBy('store_id');
    }
}
