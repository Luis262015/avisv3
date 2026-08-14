<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SiatBulkHomologationRequest;
use App\Http\Requests\Admin\SiatHomologationRequest;
use App\Models\Product;
use App\Models\SiatAnexo;
use App\Models\SiatSetting;
use App\Services\Siat\SiatHomologacionCatalogo;
use App\Services\Siat\SiatSincronizacionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Homologación del catálogo propio con las paramétricas del SIN.
 *
 * Cada línea de factura debe declarar `codigoProductoSin` y `unidadMedida` de las
 * listas oficiales; sin eso el XML no pasa el XSD y la venta no se puede
 * facturar. Estas columnas existían en `products` desde que se implementó la
 * emisión, pero no había forma de rellenarlas desde la aplicación.
 */
class SiatHomologationController extends Controller
{
    public function __construct(
        private readonly SiatHomologacionCatalogo $catalogo,
        private readonly SiatSincronizacionService $sincronizacion,
    ) {}

    public function index(Request $request): Response
    {
        $settings = SiatSetting::with('store')
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        $setting = $settings->firstWhere('id', $request->integer('setting_id')) ?? $settings->first();

        $catalogo = $this->catalogo->para($setting);

        return Inertia::render('admin/siat/homologation/index', [
            'products'  => $this->products($request),
            'catalogo'  => $catalogo,
            'setting'   => $setting?->only(['id', 'actividad_economica', 'actividad_descripcion', 'ambiente']),
            'settings'  => $settings->map(fn (SiatSetting $s): array => [
                'id'    => $s->id,
                'label' => $s->store?->name ?? $s->razon_social,
            ])->values(),
            // Los tipos de anexo son fijos (paramétrica del SIN), pero se mandan
            // desde aquí para no tener la lista escrita dos veces.
            'tiposAnexo' => collect(SiatAnexo::TIPOS)
                ->map(fn (string $label, int $value): array => ['value' => $value, 'label' => $label])
                ->values(),
            'stats'     => [
                'total'       => Product::count(),
                'homologados' => Product::whereNotNull('codigo_producto_sin')->count(),
                'con_anexo'   => Product::whereNotNull('tipo_codigo_anexo')->count(),
            ],
            'filters'   => $request->only(['search', 'estado']),
        ]);
    }

    public function update(SiatHomologationRequest $request, Product $product): RedirectResponse
    {
        $product->update($request->safe()->only(['codigo_producto_sin', 'unidad_medida_sin', 'tipo_codigo_anexo']));

        return back()->with('success', "\"{$product->name}\" quedó homologado con el SIN.");
    }

    public function bulk(SiatBulkHomologationRequest $request): RedirectResponse
    {
        $datos = $request->safe()->only(['codigo_producto_sin', 'unidad_medida_sin', 'tipo_codigo_anexo']);

        $afectados = Product::whereIn('id', $request->validated('product_ids'))->update($datos);

        return back()->with('success', "{$afectados} producto(s) homologados con el SIN.");
    }

    /**
     * Vuelve a pedir las paramétricas al SIN.
     *
     * Se cachean 12 horas, así que un producto recién habilitado por Impuestos no
     * aparecería hasta que expire la caché.
     */
    public function refresh(Request $request): RedirectResponse
    {
        $setting = SiatSetting::find($request->integer('setting_id'));

        if (! $setting) {
            return back()->withErrors(['siat' => 'No se encontró la configuración SIAT indicada.']);
        }

        $this->sincronizacion->olvidarCache($setting);

        return back()->with('success', 'Catálogos del SIN descartados; se volverán a consultar.');
    }

    /**
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator<Product>
     */
    private function products(Request $request)
    {
        return Product::query()
            ->select(['id', 'name', 'sku', 'barcode', 'unit', 'codigo_producto_sin', 'unidad_medida_sin', 'tipo_codigo_anexo'])
            ->when($request->filled('search'), function ($q) use ($request): void {
                $termino = $request->string('search')->toString();

                $q->where(function ($sub) use ($termino): void {
                    $sub->where('name', 'like', "%{$termino}%")
                        ->orWhere('sku', 'like', "%{$termino}%")
                        ->orWhere('barcode', 'like', "%{$termino}%");
                });
            })
            ->when($request->input('estado') === 'pendientes',
                fn ($q) => $q->whereNull('codigo_producto_sin'))
            ->when($request->input('estado') === 'homologados',
                fn ($q) => $q->whereNotNull('codigo_producto_sin'))
            // Los que faltan por homologar son los que bloquean la facturación, así
            // que van primero sin necesidad de filtrar.
            ->orderByRaw('codigo_producto_sin is null desc')
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();
    }
}
