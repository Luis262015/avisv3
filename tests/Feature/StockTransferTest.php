<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\StockTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Transferencias de existencias entre tiendas.
 *
 * Una transferencia nace pendiente y no mueve nada; solo al completarla salen las
 * unidades de la tienda origen y entran en la destino. Ese paso en dos tiempos es
 * lo que permite preparar el envío y confirmarlo cuando llega, y lo que hay que
 * proteger: si el descuento y el alta no ocurren juntos, aparecen o desaparecen
 * unidades de la nada.
 */
class StockTransferTest extends TestCase
{
    use RefreshDatabase;

    private Store $origen;
    private Store $destino;
    private Product $producto;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['inventory.view', 'inventory.adjust'] as $p) {
            Permission::findOrCreate($p, 'web');
        }

        $rol = Role::findOrCreate('admin', 'web');
        $rol->syncPermissions(['inventory.view', 'inventory.adjust']);

        $user = User::factory()->create();
        $user->assignRole($rol);
        $this->actingAs($user);

        $this->origen  = Store::create(['name' => 'Almacén Central', 'is_active' => true]);
        $this->destino = Store::create(['name' => 'Sucursal Sur', 'is_active' => true]);

        $this->producto = Product::create([
            'name' => 'Monitor 24"', 'sku' => 'MON-24', 'price' => 900, 'cost' => 600,
            'stock' => 0, 'min_stock' => 2, 'unit' => 'pza',
            'status' => 'active', 'track_inventory' => true,
        ]);

        app(InventoryService::class)->adjust($this->producto, 20, 'Carga inicial', $this->origen->id);
    }

    private function servicio(): StockTransferService
    {
        return app(StockTransferService::class);
    }

    private function stock(Store $store): int
    {
        return (int) (StoreStock::where('store_id', $store->id)
            ->where('product_id', $this->producto->id)
            ->value('stock') ?? 0);
    }

    /** Solo los asientos de transferencia: la carga inicial del setUp también deja uno. */
    private function asientosDeTransferencia(): int
    {
        return InventoryMovement::whereIn('type', ['transfer_in', 'transfer_out'])->count();
    }

    private function transferencia(int $cantidad = 5): StockTransfer
    {
        return $this->servicio()->create(
            ['from_store_id' => $this->origen->id, 'to_store_id' => $this->destino->id],
            [['product_id' => $this->producto->id, 'quantity' => $cantidad]],
        );
    }

    // ── Creación ────────────────────────────────────────────────────────────

    /** Crear no mueve nada: la mercancía sigue en origen hasta que se confirma. */
    public function test_una_transferencia_nace_pendiente_y_no_mueve_existencias(): void
    {
        $t = $this->transferencia();

        $this->assertSame('pending', $t->status);
        $this->assertSame(20, $this->stock($this->origen));
        $this->assertSame(0, $this->stock($this->destino));
        $this->assertSame(0, $this->asientosDeTransferencia());
    }

    public function test_no_se_puede_transferir_a_la_misma_tienda(): void
    {
        $this->post('/admin/stock-transfers', [
            'from_store_id' => $this->origen->id,
            'to_store_id'   => $this->origen->id,
            'items'         => [['product_id' => $this->producto->id, 'quantity' => 3]],
        ])->assertSessionHasErrors('to_store_id');
    }

    public function test_una_transferencia_necesita_al_menos_una_linea(): void
    {
        $this->post('/admin/stock-transfers', [
            'from_store_id' => $this->origen->id,
            'to_store_id'   => $this->destino->id,
            'items'         => [],
        ])->assertSessionHasErrors('items');
    }

    // ── Completar ───────────────────────────────────────────────────────────

    public function test_completar_mueve_las_existencias_de_una_tienda_a_la_otra(): void
    {
        $t = $this->transferencia(5);

        $this->servicio()->complete($t);

        $this->assertSame(15, $this->stock($this->origen));
        $this->assertSame(5, $this->stock($this->destino));
        $this->assertSame('completed', $t->refresh()->status);
        $this->assertNotNull($t->completed_at);
    }

    /** Mover entre tiendas no crea ni destruye mercancía: el total no cambia. */
    public function test_el_total_de_la_empresa_no_cambia_al_transferir(): void
    {
        $antes = (int) $this->producto->refresh()->stock;

        $this->servicio()->complete($this->transferencia(8));

        $this->assertSame($antes, (int) $this->producto->refresh()->stock);
        $this->assertSame(20, $antes);
    }

    /**
     * El historial es el registro de la transferencia: dos asientos que se
     * refieren al mismo documento, uno en cada tienda.
     */
    public function test_completar_deja_dos_asientos_en_el_historial(): void
    {
        $t = $this->transferencia(5);
        $this->servicio()->complete($t);

        $this->assertDatabaseHas('inventory_movements', [
            'product_id'     => $this->producto->id,
            'store_id'       => $this->origen->id,
            'type'           => 'transfer_out',
            'quantity'       => 5,
            'stock_before'   => 20,
            'stock_after'    => 15,
            'reference_type' => StockTransfer::class,
            'reference_id'   => $t->id,
        ]);

        $this->assertDatabaseHas('inventory_movements', [
            'product_id'     => $this->producto->id,
            'store_id'       => $this->destino->id,
            'type'           => 'transfer_in',
            'quantity'       => 5,
            'stock_before'   => 0,
            'stock_after'    => 5,
            'reference_type' => StockTransfer::class,
            'reference_id'   => $t->id,
        ]);
    }

    /** Sin esta guarda, la tienda origen quedaría en negativo. */
    public function test_no_se_completa_si_el_origen_no_tiene_suficiente(): void
    {
        $t = $this->transferencia(50);

        try {
            $this->servicio()->complete($t);
            $this->fail('Debería haber rechazado la transferencia por falta de stock.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Stock insuficiente', implode(' ', $e->errors()['items']));
        }

        $this->assertSame(20, $this->stock($this->origen));
        $this->assertSame(0, $this->stock($this->destino));
        $this->assertSame('pending', $t->refresh()->status);
    }

    /**
     * La validación mira todas las líneas antes de mover ninguna: si la segunda no
     * cabe, la primera tampoco debe haberse movido.
     */
    public function test_una_linea_imposible_deja_toda_la_transferencia_sin_efecto(): void
    {
        $otro = Product::create([
            'name' => 'Teclado', 'sku' => 'TEC-9', 'price' => 100, 'cost' => 50,
            'stock' => 0, 'min_stock' => 1, 'unit' => 'pza',
            'status' => 'active', 'track_inventory' => true,
        ]);

        $t = $this->servicio()->create(
            ['from_store_id' => $this->origen->id, 'to_store_id' => $this->destino->id],
            [
                ['product_id' => $this->producto->id, 'quantity' => 5],
                ['product_id' => $otro->id, 'quantity' => 3], // no hay ninguno en origen
            ],
        );

        try {
            $this->servicio()->complete($t);
            $this->fail('Debería haber rechazado la transferencia.');
        } catch (ValidationException) {
            // esperado
        }

        $this->assertSame(20, $this->stock($this->origen), 'La primera línea no debía moverse.');
        $this->assertSame(0, $this->stock($this->destino));
        $this->assertSame(0, $this->asientosDeTransferencia());
    }

    public function test_no_se_completa_dos_veces(): void
    {
        $t = $this->transferencia(5);
        $this->servicio()->complete($t);

        $this->expectException(ValidationException::class);
        $this->servicio()->complete($t->refresh());
    }

    // ── Cancelar ────────────────────────────────────────────────────────────

    public function test_cancelar_una_pendiente_no_toca_las_existencias(): void
    {
        $t = $this->transferencia(5);

        $this->servicio()->cancel($t);

        $this->assertSame('cancelled', $t->refresh()->status);
        $this->assertNotNull($t->cancelled_at);
        $this->assertSame(20, $this->stock($this->origen));
        $this->assertSame(0, $this->asientosDeTransferencia());
    }

    /** Cancelar después de mover dejaría la mercancía en destino y el papel diciendo que no salió. */
    public function test_no_se_cancela_una_transferencia_ya_completada(): void
    {
        $t = $this->transferencia(5);
        $this->servicio()->complete($t);

        $this->expectException(ValidationException::class);
        $this->servicio()->cancel($t->refresh());
    }

    // ── A través de las rutas ───────────────────────────────────────────────

    public function test_el_ciclo_completo_por_http(): void
    {
        $this->post('/admin/stock-transfers', [
            'from_store_id' => $this->origen->id,
            'to_store_id'   => $this->destino->id,
            'notes'         => 'Reposición semanal',
            'items'         => [['product_id' => $this->producto->id, 'quantity' => 6]],
        ])->assertRedirect();

        $t = StockTransfer::firstOrFail();
        $this->assertSame('pending', $t->status);

        $this->patch("/admin/stock-transfers/{$t->id}/complete")->assertRedirect();

        $this->assertSame('completed', $t->refresh()->status);
        $this->assertSame(14, $this->stock($this->origen));
        $this->assertSame(6, $this->stock($this->destino));
    }

    /** El movimiento tiene que poder verse en el historial filtrando por tienda. */
    public function test_la_transferencia_aparece_en_el_historial_de_ambas_tiendas(): void
    {
        $this->servicio()->complete($this->transferencia(5));

        $salida = $this->get("/admin/inventory?store_id={$this->origen->id}")
            ->viewData('page')['props']['movements']['data'];
        $entrada = $this->get("/admin/inventory?store_id={$this->destino->id}")
            ->viewData('page')['props']['movements']['data'];

        $this->assertCount(2, $salida, 'La carga inicial y la salida por transferencia.');
        $this->assertSame('transfer_out', $salida[0]['type']);

        $this->assertCount(1, $entrada);
        $this->assertSame('transfer_in', $entrada[0]['type']);
    }
}
