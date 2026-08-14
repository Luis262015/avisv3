<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * La lista del catálogo vista desde una tienda.
 *
 * Lo que se protege aquí es que la lista parta de `products` y no de
 * `store_product_stocks`: consultando la tabla de existencias, un producto que
 * esa tienda nunca ha tenido no aparecería, y «no se maneja aquí» se vería igual
 * que «aquí no queda ninguno».
 */
class InventoryStockPorTiendaTest extends TestCase
{
    use RefreshDatabase;

    private Store $matriz;
    private Store $sucursal;
    private Product $laptop;
    private Product $mouse;
    private Product $servicio;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['inventory.view', 'inventory.adjust'] as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }

        $rol = Role::findOrCreate('admin', 'web');
        $rol->syncPermissions(['inventory.view', 'inventory.adjust']);

        $user = User::factory()->create();
        $user->assignRole($rol);
        $this->actingAs($user);

        $this->matriz   = Store::create(['name' => 'Casa Matriz', 'is_active' => true]);
        $this->sucursal = Store::create(['name' => 'Sucursal Sur', 'is_active' => true]);

        $categoria = Category::create(['name' => 'Computación', 'slug' => 'computacion']);

        $this->laptop = Product::create([
            'name' => 'Laptop HP', 'slug' => 'laptop-hp', 'sku' => 'LAP-1', 'category_id' => $categoria->id,
            'price' => 5000, 'cost' => 4000, 'stock' => 0, 'min_stock' => 2,
            'status' => 'active', 'track_inventory' => true,
        ]);

        $this->mouse = Product::create([
            'name' => 'Mouse USB', 'slug' => 'mouse-usb', 'sku' => 'MOU-1',
            'price' => 50, 'cost' => 30, 'stock' => 0, 'min_stock' => 5,
            'status' => 'active', 'track_inventory' => true,
        ]);

        // Un servicio no tiene existencias que contar.
        $this->servicio = Product::create([
            'name' => 'Instalación', 'slug' => 'instalacion', 'sku' => 'SRV-1',
            'price' => 200, 'cost' => 0, 'stock' => 0, 'min_stock' => 0,
            'status' => 'active', 'track_inventory' => false,
        ]);

        // Solo la matriz tiene existencias; la sucursal no tiene ni una fila.
        StoreStock::create(['store_id' => $this->matriz->id, 'product_id' => $this->laptop->id, 'stock' => 10]);
        StoreStock::create(['store_id' => $this->matriz->id, 'product_id' => $this->mouse->id, 'stock' => 3]);

        Product::whereKey($this->laptop->id)->update(['stock' => 10]);
        Product::whereKey($this->mouse->id)->update(['stock' => 3]);
    }

    /** @return array<int, array<string, mixed>> */
    private function filas(AssertableInertia $page): array
    {
        return $page->toArray()['props']['rows']['data'];
    }

    // ─── El catálogo completo, también donde no hay nada ─────────────────────

    /**
     * El caso que motivó la pantalla: una sucursal sin ninguna fila en
     * `store_product_stocks` tiene que enseñar igualmente todo el catálogo.
     */
    public function test_una_tienda_sin_existencias_muestra_igual_todo_el_catalogo(): void
    {
        $this->assertSame(0, StoreStock::where('store_id', $this->sucursal->id)->count());

        $this->get("/admin/inventory/stock?store_id={$this->sucursal->id}")
            ->assertInertia(function (AssertableInertia $page): void {
                $filas = $this->filas($page);

                $this->assertCount(3, $filas);
                $this->assertSame([0, 0, 0], array_column($filas, 'stock_tienda'));
            });
    }

    /** El total de la empresa da el contexto: si hay en otra tienda, es transferir. */
    public function test_cada_fila_trae_el_total_de_la_empresa_ademas_del_de_la_tienda(): void
    {
        $this->get("/admin/inventory/stock?store_id={$this->sucursal->id}&search=Laptop")
            ->assertInertia(function (AssertableInertia $page): void {
                $fila = $this->filas($page)[0];

                $this->assertSame(0, $fila['stock_tienda']);
                $this->assertSame(10, $fila['stock_total']);
            });
    }

    public function test_muestra_las_existencias_de_la_tienda_elegida(): void
    {
        $this->get("/admin/inventory/stock?store_id={$this->matriz->id}&search=Laptop")
            ->assertInertia(function (AssertableInertia $page): void {
                $this->assertSame(10, $this->filas($page)[0]['stock_tienda']);
            });
    }

    /** Sin tienda en la URL se toma la primera activa, no se muestra un vacío. */
    public function test_sin_tienda_elegida_usa_la_primera(): void
    {
        $this->get('/admin/inventory/stock')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filters.store_id', $this->matriz->id)
                ->has('rows.data', 3));
    }

    // ─── Mínimos ─────────────────────────────────────────────────────────────

    public function test_el_minimo_propio_de_la_tienda_manda_sobre_el_general(): void
    {
        StoreStock::where('store_id', $this->matriz->id)
            ->where('product_id', $this->laptop->id)
            ->update(['min_stock' => 8]);

        $this->get("/admin/inventory/stock?store_id={$this->matriz->id}&search=Laptop")
            ->assertInertia(function (AssertableInertia $page): void {
                $fila = $this->filas($page)[0];

                $this->assertSame(8, $fila['min_efectivo']);
                $this->assertSame(8, $fila['min_propio']);
            });
    }

    public function test_sin_minimo_propio_se_hereda_el_del_producto(): void
    {
        $this->get("/admin/inventory/stock?store_id={$this->matriz->id}&search=Laptop")
            ->assertInertia(function (AssertableInertia $page): void {
                $fila = $this->filas($page)[0];

                $this->assertSame(2, $fila['min_efectivo']);
                $this->assertNull($fila['min_propio']);
            });
    }

    // ─── Filtros ─────────────────────────────────────────────────────────────

    public function test_filtra_los_que_estan_bajo_minimo_en_esa_tienda(): void
    {
        // En la matriz solo el mouse (3 <= 5); la laptop tiene 10 sobre un mínimo de 2.
        $this->get("/admin/inventory/stock?store_id={$this->matriz->id}&estado=bajo")
            ->assertInertia(function (AssertableInertia $page): void {
                $filas = $this->filas($page);

                $this->assertCount(1, $filas);
                $this->assertSame('Mouse USB', $filas[0]['name']);
            });
    }

    /** Un servicio no está «sin stock»: no lleva control de inventario. */
    public function test_el_filtro_sin_stock_deja_fuera_lo_que_no_lleva_inventario(): void
    {
        $this->get("/admin/inventory/stock?store_id={$this->sucursal->id}&estado=sin_stock")
            ->assertInertia(function (AssertableInertia $page): void {
                $nombres = array_column($this->filas($page), 'name');

                $this->assertCount(2, $nombres);
                $this->assertNotContains('Instalación', $nombres);
            });
    }

    public function test_busca_por_nombre_y_por_sku(): void
    {
        $this->get("/admin/inventory/stock?store_id={$this->matriz->id}&search=MOU-1")
            ->assertInertia(fn (AssertableInertia $page) => $page->has('rows.data', 1));

        $this->get("/admin/inventory/stock?store_id={$this->matriz->id}&search=Mouse")
            ->assertInertia(fn (AssertableInertia $page) => $page->has('rows.data', 1));
    }

    public function test_filtra_por_categoria(): void
    {
        $categoriaId = $this->laptop->category_id;

        $this->get("/admin/inventory/stock?store_id={$this->matriz->id}&category_id={$categoriaId}")
            ->assertInertia(function (AssertableInertia $page): void {
                $filas = $this->filas($page);

                $this->assertCount(1, $filas);
                $this->assertSame('Laptop HP', $filas[0]['name']);
            });
    }

    // ─── Resumen ─────────────────────────────────────────────────────────────

    public function test_el_resumen_cuenta_por_tienda_y_no_por_empresa(): void
    {
        $this->get("/admin/inventory/stock?store_id={$this->matriz->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('resumen.productos', 3)
                ->where('resumen.con_stock', 2)
                ->where('resumen.sin_stock', 0)
                // El servicio no cuenta ni como con stock ni como sin stock.
                ->where('resumen.sin_control', 1)
                ->where('resumen.bajo_minimo', 1)
                ->where('resumen.unidades', 13)
                // 10 laptops a 4000 + 3 mouse a 30.
                ->where('resumen.valor', 40090));
    }

    public function test_en_una_tienda_vacia_el_resumen_lo_dice_sin_esconder_el_catalogo(): void
    {
        $this->get("/admin/inventory/stock?store_id={$this->sucursal->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('resumen.productos', 3)
                ->where('resumen.con_stock', 0)
                ->where('resumen.sin_stock', 2)
                ->where('resumen.bajo_minimo', 2)
                ->where('resumen.unidades', 0)
                ->where('resumen.valor', 0));
    }

    // ─── Acciones ────────────────────────────────────────────────────────────

    /** Ajustar desde aquí crea la fila que la tienda no tenía. */
    public function test_se_puede_ajustar_el_stock_de_un_producto_que_la_tienda_nunca_tuvo(): void
    {
        $this->post('/admin/inventory/adjust', [
            'product_id' => $this->laptop->id,
            'store_id'   => $this->sucursal->id,
            'new_stock'  => 4,
            'reason'     => 'Carga inicial de la sucursal',
        ])
            // Sin comprobar la redirección, un 403 por permisos pasaría por bueno:
            // tampoco deja errores en sesión.
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(4, (int) StoreStock::where('store_id', $this->sucursal->id)
            ->where('product_id', $this->laptop->id)
            ->value('stock'));

        // El total de la empresa es la suma: 10 de la matriz + 4 de la sucursal.
        $this->assertSame(14, (int) $this->laptop->fresh()->stock);
    }
}
