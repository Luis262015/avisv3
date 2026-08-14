<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Las existencias viven por tienda y `products.stock` es solo su suma.
 *
 * Lo que se protege aquí es ese invariante: mientras se cumpla, el total y sus
 * partes cuentan la misma historia. Antes había dos formas de romperlo —el
 * formulario de productos y los ajustes sin tienda— y ninguna avisaba.
 */
class InventoryStoreScopedTest extends TestCase
{
    use RefreshDatabase;

    private Store $sur;
    private Store $norte;
    private Product $producto;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['inventory.view', 'inventory.adjust', 'products.create', 'products.edit'] as $p) {
            Permission::findOrCreate($p, 'web');
        }

        $rol = Role::findOrCreate('admin', 'web');
        $rol->syncPermissions(['inventory.view', 'inventory.adjust', 'products.create', 'products.edit']);

        $user = User::factory()->create();
        $user->assignRole($rol);
        $this->actingAs($user);

        $this->sur   = Store::create(['name' => 'Sucursal Sur', 'is_active' => true]);
        $this->norte = Store::create(['name' => 'Sucursal Norte', 'is_active' => true]);

        $this->producto = Product::create([
            'name' => 'Teclado mecánico', 'sku' => 'TEC-1', 'price' => 300, 'cost' => 180,
            'stock' => 0, 'min_stock' => 5, 'unit' => 'pza',
            'status' => 'active', 'track_inventory' => true,
        ]);
    }

    private function inventario(): InventoryService
    {
        return app(InventoryService::class);
    }

    // ── El invariante ───────────────────────────────────────────────────────

    public function test_el_total_del_producto_es_la_suma_de_sus_tiendas(): void
    {
        $this->inventario()->adjust($this->producto, 10, 'Carga inicial', $this->sur->id);
        $this->inventario()->adjust($this->producto, 4, 'Carga inicial', $this->norte->id);

        $this->assertSame(14, (int) $this->producto->refresh()->stock);
        $this->assertSame(10, (int) StoreStock::where('store_id', $this->sur->id)->value('stock'));
        $this->assertSame(4, (int) StoreStock::where('store_id', $this->norte->id)->value('stock'));
    }

    /**
     * El campo se quitó del formulario; si alguien lo reintroduce, esto avisa.
     * Un producto con existencias que ninguna tienda tiene se ve en el listado y
     * no se puede vender.
     */
    public function test_el_formulario_de_productos_no_puede_fijar_existencias(): void
    {
        $this->post('/admin/products', [
            'name' => 'Monitor 24"', 'sku' => 'MON-24', 'price' => 900, 'cost' => 600,
            'stock' => 25, 'min_stock' => 3, 'unit' => 'pza',
            'status' => 'active', 'track_inventory' => true,
        ]);

        $creado = Product::where('sku', 'MON-24')->firstOrFail();

        $this->assertSame(0, (int) $creado->stock, 'El stock enviado por el formulario no debe aplicarse.');
        $this->assertSame(0, StoreStock::where('product_id', $creado->id)->count());
    }

    public function test_editar_un_producto_no_altera_sus_existencias(): void
    {
        $this->inventario()->adjust($this->producto, 7, 'Carga', $this->sur->id);

        $this->put("/admin/products/{$this->producto->id}", [
            'name' => 'Teclado mecánico RGB', 'sku' => 'TEC-1', 'price' => 320, 'cost' => 180,
            'stock' => 999, 'min_stock' => 5, 'unit' => 'pza',
            'status' => 'active', 'track_inventory' => true,
        ]);

        $this->producto->refresh();

        $this->assertSame('Teclado mecánico RGB', $this->producto->name);
        $this->assertSame(7, (int) $this->producto->stock);
    }

    // ── Ajustes ─────────────────────────────────────────────────────────────

    public function test_el_ajuste_exige_tienda(): void
    {
        $this->post('/admin/inventory/adjust', [
            'product_id' => $this->producto->id,
            'new_stock'  => 10,
            'reason'     => 'Conteo físico',
        ])->assertSessionHasErrors('store_id');

        $this->assertSame(0, (int) $this->producto->refresh()->stock);
    }

    public function test_el_ajuste_exige_motivo(): void
    {
        $this->post('/admin/inventory/adjust', [
            'product_id' => $this->producto->id,
            'store_id'   => $this->sur->id,
            'new_stock'  => 10,
        ])->assertSessionHasErrors('reason');
    }

    public function test_el_ajuste_solo_toca_la_tienda_indicada(): void
    {
        $this->inventario()->adjust($this->producto, 10, 'Carga', $this->sur->id);
        $this->inventario()->adjust($this->producto, 10, 'Carga', $this->norte->id);

        $this->post('/admin/inventory/adjust', [
            'product_id' => $this->producto->id,
            'store_id'   => $this->sur->id,
            'new_stock'  => 3,
            'reason'     => 'Merma por rotura',
        ])->assertRedirect();

        $this->assertSame(3, (int) StoreStock::where('store_id', $this->sur->id)->value('stock'));
        $this->assertSame(10, (int) StoreStock::where('store_id', $this->norte->id)->value('stock'));
        $this->assertSame(13, (int) $this->producto->refresh()->stock);
    }

    /** El ajuste deja rastro: es la única forma de auditar una merma. */
    public function test_el_ajuste_queda_registrado_en_el_historial(): void
    {
        $this->inventario()->adjust($this->producto, 8, 'Conteo físico de agosto', $this->sur->id);

        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $this->producto->id,
            'store_id'   => $this->sur->id,
            'type'       => 'adjustment',
            'reason'     => 'Conteo físico de agosto',
            'stock_after' => 8,
        ]);
    }

    // ── Mínimo por tienda ───────────────────────────────────────────────────

    public function test_cada_tienda_puede_tener_su_propio_minimo(): void
    {
        $this->post('/admin/inventory/min-stock', [
            'product_id' => $this->producto->id,
            'store_id'   => $this->sur->id,
            'min_stock'  => 20,
        ])->assertRedirect();

        $sur = StoreStock::where('store_id', $this->sur->id)->firstOrFail();
        $this->assertSame(20, $sur->min_stock);
        $this->assertSame(20, $sur->minimoEfectivo());
    }

    /** Sin mínimo propio manda el del producto; así no hay que fijarlo en cada tienda. */
    public function test_sin_minimo_propio_rige_el_general_del_producto(): void
    {
        $this->inventario()->adjust($this->producto, 1, 'Carga', $this->norte->id);

        $norte = StoreStock::where('store_id', $this->norte->id)->firstOrFail();

        $this->assertNull($norte->min_stock);
        $this->assertSame(5, $norte->minimoEfectivo());
        $this->assertTrue($norte->estaBajoMinimo());
    }

    public function test_se_puede_volver_al_minimo_general(): void
    {
        $this->inventario()->setMinimoTienda($this->producto, $this->sur->id, 20);

        $this->post('/admin/inventory/min-stock', [
            'product_id' => $this->producto->id,
            'store_id'   => $this->sur->id,
            'min_stock'  => null,
        ])->assertRedirect();

        $this->assertNull(StoreStock::where('store_id', $this->sur->id)->value('min_stock'));
    }

    /**
     * El caso que motivó todo: con un único mínimo para la empresa, una sucursal
     * con 6 unidades y otra con 6 se juzgaban igual aunque una venda el triple.
     */
    public function test_el_stock_bajo_se_evalua_tienda_por_tienda(): void
    {
        $this->inventario()->adjust($this->producto, 6, 'Carga', $this->sur->id);
        $this->inventario()->adjust($this->producto, 6, 'Carga', $this->norte->id);

        // Sur vende más: su mínimo propio es 10; Norte se queda con el general (5).
        $this->inventario()->setMinimoTienda($this->producto, $this->sur->id, 10);

        $bajos = $this->get('/admin/inventory')->viewData('page')['props']['lowStock'];

        $this->assertCount(1, $bajos);
        $this->assertSame('Sucursal Sur', $bajos[0]['store_name']);
        $this->assertSame(10, $bajos[0]['min_stock']);
    }

    // ── Historial ───────────────────────────────────────────────────────────

    public function test_el_historial_se_filtra_por_tienda(): void
    {
        $this->inventario()->adjust($this->producto, 5, 'Carga Sur', $this->sur->id);
        $this->inventario()->adjust($this->producto, 9, 'Carga Norte', $this->norte->id);

        $resp = $this->get("/admin/inventory?store_id={$this->norte->id}");
        $resp->assertSessionHasNoErrors();
        $resp->assertOk();
        $props = $resp->viewData('page')['props'];

        $this->assertCount(1, $props['movements']['data']);
        $this->assertSame('Carga Norte', $props['movements']['data'][0]['reason']);
    }

    public function test_el_historial_se_filtra_por_tipo(): void
    {
        $this->inventario()->adjust($this->producto, 5, 'Carga', $this->sur->id);
        $this->inventario()->recordMovement(
            product: $this->producto, storeId: $this->sur->id, type: 'out', quantity: 2, reason: 'Venta',
        );

        $props = $this->get('/admin/inventory?type=out')->viewData('page')['props'];

        $this->assertCount(1, $props['movements']['data']);
        $this->assertSame('out', $props['movements']['data'][0]['type']);
    }
}
