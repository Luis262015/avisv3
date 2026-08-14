<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\InventoryAdjustRequest;
use App\Http\Requests\Admin\StoreMinStockRequest;
use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Store;
use App\Models\StoreStock;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
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
            'filters'     => $filtros,
        ]);
    }

    /**
     * El catálogo visto desde una tienda: qué hay, cuánto y qué falta.
     *
     * Parte de `products` y no de `store_product_stocks` a propósito. Un producto
     * que nunca entró en esta tienda no tiene fila allí, así que consultando la
     * tabla de existencias simplemente no aparecería, y entonces "no se maneja
     * aquí" y "aquí no queda ninguno" se verían igual: como una ausencia. Con el
     * LEFT JOIN sale con 0, que es la respuesta correcta y además accionable.
     */
    public function stock(Request $request): Response
    {
        $filtros = $request->validate([
            'store_id'    => ['nullable', 'integer', 'exists:stores,id'],
            'search'      => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'estado'      => ['nullable', 'string', 'in:bajo,sin_stock,con_stock'],
        ]);

        $stores = Store::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        // Sin tienda no hay nada que listar: "todas" sería el producto cartesiano
        // de catálogo por sucursales, que no es una lista sino un informe.
        $storeId = (int) ($filtros['store_id'] ?? $stores->first()?->id ?? 0);

        $filtros['store_id']    = $storeId ?: null;
        $filtros['category_id'] = isset($filtros['category_id']) ? (int) $filtros['category_id'] : null;

        return Inertia::render('admin/inventory/stock', [
            // Un paginador vacío y no un array: la página no debería tener que
            // distinguir "no hay tiendas" de "no hay resultados" para pintarse.
            'rows'       => $storeId ? $this->existencias($storeId, $filtros) : new LengthAwarePaginator([], 0, 30),
            'resumen'    => $storeId ? $this->resumenExistencias($storeId) : null,
            'stores'     => $stores,
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'filters'    => $filtros,
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

    /**
     * Base de la consulta de existencias de una tienda.
     *
     * El `where` de la tienda va **dentro** del ON del LEFT JOIN, no en el WHERE:
     * puesto fuera convertiría el LEFT JOIN en INNER y volveríamos a perder los
     * productos que esa tienda nunca ha tenido, que es justo lo que se quiere ver.
     */
    private function baseExistencias(int $storeId)
    {
        return Product::query()
            ->leftJoin('store_product_stocks', function ($join) use ($storeId): void {
                $join->on('store_product_stocks.product_id', '=', 'products.id')
                    ->where('store_product_stocks.store_id', '=', $storeId);
            })
            ->where('products.status', 'active');
    }

    /** Stock de la tienda, con 0 cuando no hay fila. */
    private function stockDeLaTienda(): string
    {
        return 'COALESCE(store_product_stocks.stock, 0)';
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function existencias(int $storeId, array $filtros)
    {
        $stock  = $this->stockDeLaTienda();
        $minimo = StoreStock::minimoEfectivoSql();

        return $this->baseExistencias($storeId)
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->select([
                'products.id',
                'products.name',
                'products.sku',
                'products.barcode',
                'products.unit',
                'products.price',
                'products.track_inventory',
                'categories.name as category_name',
                // `products.stock` es el total denormalizado de la empresa; el de
                // esta tienda sale del LEFT JOIN.
                'products.stock as stock_total',
                'store_product_stocks.min_stock as min_propio',
            ])
            ->selectRaw("{$stock} as stock_tienda")
            ->selectRaw("COALESCE({$minimo}, 0) as min_efectivo")
            ->when($filtros['search'] ?? null, function ($q, $termino): void {
                $q->where(function ($sub) use ($termino): void {
                    $sub->where('products.name', 'like', "%{$termino}%")
                        ->orWhere('products.sku', 'like', "%{$termino}%")
                        ->orWhere('products.barcode', 'like', "%{$termino}%");
                });
            })
            ->when($filtros['category_id'] ?? null, fn ($q, $v) => $q->where('products.category_id', $v))
            // "Bajo mínimo" solo tiene sentido donde se lleva inventario y hay un
            // mínimo fijado; sin eso todo producto parecería estar bajo.
            ->when(($filtros['estado'] ?? null) === 'bajo', fn ($q) => $q
                ->where('products.track_inventory', true)
                ->whereRaw("{$minimo} > 0")
                ->whereRaw("{$stock} <= {$minimo}"))
            // Un servicio no está "sin stock": no lleva control de inventario, así
            // que se queda fuera de los dos filtros de existencias.
            ->when(($filtros['estado'] ?? null) === 'sin_stock', fn ($q) => $q
                ->where('products.track_inventory', true)
                ->whereRaw("{$stock} <= 0"))
            ->when(($filtros['estado'] ?? null) === 'con_stock', fn ($q) => $q
                ->where('products.track_inventory', true)
                ->whereRaw("{$stock} > 0"))
            ->orderBy('products.name')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (Product $p): array => [
                'id'              => (int) $p->id,
                'name'            => (string) $p->name,
                'sku'             => $p->sku,
                'barcode'         => $p->barcode,
                'unit'            => $p->unit,
                'category'        => $p->category_name,
                'price'           => (float) $p->price,
                'stock_tienda'    => (int) $p->stock_tienda,
                // El total de la empresa da el contexto que hace accionable la
                // cifra: sin stock aquí pero con existencias en otra tienda es una
                // transferencia, no una compra.
                'stock_total'     => (int) $p->stock_total,
                'min_efectivo'    => (int) $p->min_efectivo,
                // Si es null, la tienda hereda el mínimo general del producto.
                'min_propio'      => $p->min_propio === null ? null : (int) $p->min_propio,
                'track_inventory' => (bool) $p->track_inventory,
            ]);
    }

    /**
     * @return array{productos: int, con_stock: int, sin_stock: int, sin_control: int, bajo_minimo: int, unidades: int, valor: float}
     */
    private function resumenExistencias(int $storeId): array
    {
        $stock  = $this->stockDeLaTienda();
        $minimo = StoreStock::minimoEfectivoSql();

        $fila = $this->baseExistencias($storeId)
            ->selectRaw("
                COUNT(*) as productos,
                SUM(CASE WHEN products.track_inventory = 1 AND {$stock} > 0 THEN 1 ELSE 0 END) as con_stock,
                SUM(CASE WHEN products.track_inventory = 1 AND {$stock} <= 0 THEN 1 ELSE 0 END) as sin_stock,
                SUM(CASE WHEN products.track_inventory = 0 THEN 1 ELSE 0 END) as sin_control,
                SUM(CASE WHEN products.track_inventory = 1 AND {$minimo} > 0 AND {$stock} <= {$minimo}
                    THEN 1 ELSE 0 END) as bajo_minimo,
                COALESCE(SUM({$stock}), 0) as unidades,
                COALESCE(SUM({$stock} * products.cost), 0) as valor
            ")
            ->first();

        return [
            'productos'   => (int) ($fila->productos ?? 0),
            'con_stock'   => (int) ($fila->con_stock ?? 0),
            'sin_stock'   => (int) ($fila->sin_stock ?? 0),
            'sin_control' => (int) ($fila->sin_control ?? 0),
            'bajo_minimo' => (int) ($fila->bajo_minimo ?? 0),
            'unidades'    => (int) ($fila->unidades ?? 0),
            // Valorizado a costo, que es lo que vale reponerlo, no lo que se espera cobrar.
            'valor'       => round((float) ($fila->valor ?? 0), 2),
        ];
    }
}
