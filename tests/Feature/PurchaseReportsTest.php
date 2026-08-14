<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Reportes de compras.
 *
 * El fallo que motivó estos tests: la pantalla no abría. No era el servidor —el
 * informe respondía 200— sino que los agregados de SQL (`SUM`, `AVG`) llegaban
 * al navegador como **cadenas**, porque PDO no las convierte y Eloquent no
 * castea lo que no es una columna del modelo. La página los formateaba con
 * `.toFixed()`, que en una cadena no existe, y React se caía entero.
 *
 * De ahí que aquí no baste con comprobar que la ruta responde: hay que mirar los
 * tipos que salen hacia la interfaz, que es donde estaba el fallo. Y hacen falta
 * compras de verdad, porque con la base vacía los agregados son 0 y el problema
 * no se manifiesta.
 *
 * **Aviso: esta suite NO puede reproducir el fallo original.** Corre sobre SQLite
 * y allí `SUM()` ya devuelve un número; la cadena solo aparece en MySQL, que es
 * donde corre la aplicación (comprobado sobre la base real: llegaba `"200.00"`).
 * Estas comprobaciones fijan el contrato y detectarían una regresión si la suite
 * llegara a correr sobre MySQL, pero la defensa efectiva es que la página
 * convierte con `Number()` antes de formatear, que no depende del motor.
 */
class PurchaseReportsTest extends TestCase
{
    use RefreshDatabase;

    private Supplier $proveedor;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin', 'web');

        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);

        $store = Store::create(['name' => 'Casa Matriz', 'is_active' => true]);

        $this->proveedor = Supplier::create([
            'name' => 'Distribuidora Andina SRL', 'is_active' => true,
        ]);

        $producto = Product::create([
            'name' => 'Laptop HP', 'slug' => 'laptop-hp', 'sku' => 'LAP-1',
            'price' => 5000, 'cost' => 4000, 'stock' => 0, 'status' => 'active',
        ]);

        foreach ([['1500.50', '195.07'], ['2400.25', '312.03']] as $i => [$total, $tax]) {
            $compra = Purchase::create([
                'supplier_id'    => $this->proveedor->id,
                'store_id'       => $store->id,
                'user_id'        => $user->id,
                'folio'          => 'C-' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                'invoice_number' => 'F-' . uniqid(),
                'date'           => now()->toDateString(),
                'subtotal'       => $total,
                'tax'            => $tax,
                'total'          => $total,
                'status'         => 'received',
                'payment_status' => 'unpaid',
            ]);

            PurchaseItem::create([
                'purchase_id' => $compra->id,
                'product_id'  => $producto->id,
                'quantity'    => 3,
                'cost'        => 500,
                'subtotal'    => 1500,
            ]);
        }
    }

    private function props(): array
    {
        $props = [];

        $this->get('/admin/purchases-reports')
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page) use (&$props): void {
                $props = $page->toArray()['props'];
            });

        return $props;
    }

    public function test_el_reporte_abre(): void
    {
        $this->get('/admin/purchases-reports')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('admin/purchases/reports/index'));
    }

    // ─── Los tipos que llegan al navegador ───────────────────────────────────

    /**
     * El fallo original: `"3900.75"` en lugar de `3900.75`. La interfaz lo
     * formatea con `.toFixed()` y una cadena no lo tiene.
     */
    public function test_los_totales_del_resumen_son_numeros_y_no_cadenas(): void
    {
        $summary = $this->props()['summary'];

        foreach (['total_amount', 'avg_amount', 'total_tax', 'unpaid_amount', 'partial_amount'] as $campo) {
            $this->assertIsNumeric($summary[$campo]);
            $this->assertIsNotString($summary[$campo], "summary.{$campo} llega como cadena y rompe el formateo.");
        }

        $this->assertIsInt($summary['total_purchases']);
        $this->assertSame(3900.75, $summary['total_amount']);
    }

    public function test_los_agregados_por_proveedor_son_numeros(): void
    {
        $fila = $this->props()['bySupplier'][0];

        $this->assertIsInt($fila['count']);
        $this->assertIsNotString($fila['total_amount']);
        $this->assertSame(3900.75, $fila['total_amount']);
        $this->assertSame('Distribuidora Andina SRL', $fila['supplier']['name']);
    }

    public function test_los_agregados_por_producto_son_numeros(): void
    {
        $fila = $this->props()['byProduct'][0];

        $this->assertIsNotString($fila['total_quantity']);
        $this->assertIsNotString($fila['total_amount']);
        $this->assertIsNotString($fila['avg_cost']);
        $this->assertSame(6.0, (float) $fila['total_quantity']);
        $this->assertSame('LAP-1', $fila['product']['sku']);
    }

    public function test_los_agregados_de_la_evolucion_mensual_son_numeros(): void
    {
        $fila = $this->props()['costEvolution'][0];

        $this->assertIsString($fila['month']);
        $this->assertIsInt($fila['count']);
        $this->assertIsNotString($fila['total_amount']);
        $this->assertIsNotString($fila['total_tax']);
    }

    public function test_los_agregados_de_cumplimiento_son_numeros(): void
    {
        $fila = $this->props()['compliance'][0];

        foreach (['total_orders', 'completed_orders', 'paid_orders', 'measurable_deliveries', 'on_time_deliveries'] as $campo) {
            $this->assertIsInt($fila[$campo], "compliance.{$campo} debería ser entero.");
        }

        $this->assertIsNotString($fila['total_amount']);
        $this->assertIsNotString($fila['unpaid_amount']);
    }

    // ─── Filtros ─────────────────────────────────────────────────────────────

    public function test_filtra_por_rango_de_fechas(): void
    {
        $props = [];

        $this->get('/admin/purchases-reports?' . http_build_query([
            'from' => now()->subYear()->toDateString(),
            'to'   => now()->subMonths(6)->toDateString(),
        ]))
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page) use (&$props): void {
                $props = $page->toArray()['props'];
            });

        // Fuera del rango no hay compras, pero los tipos se mantienen.
        $this->assertSame(0, $props['summary']['total_purchases']);
        $this->assertIsNotString($props['summary']['total_amount']);
    }

    public function test_rechaza_un_rango_de_fechas_invertido(): void
    {
        $this->get('/admin/purchases-reports?' . http_build_query([
            'from' => now()->toDateString(),
            'to'   => now()->subMonth()->toDateString(),
        ]))->assertSessionHasErrors('to');
    }

    public function test_filtra_por_proveedor(): void
    {
        $this->get("/admin/purchases-reports?supplier_id={$this->proveedor->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('summary.total_purchases', 2));
    }
}
