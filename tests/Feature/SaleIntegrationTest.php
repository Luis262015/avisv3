<?php

namespace Tests\Feature;

use App\Models\CashRegister;
use App\Models\CashShift;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Sale;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\User;
use App\Services\PurchaseService;
use App\Services\ReturnService;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

/**
 * Integración del módulo de Ventas con Inventario, Caja, Devoluciones,
 * Promociones y SIAT.
 */
class SaleIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Store $store;
    private CashShift $shift;
    private Product $product;
    private SaleService $sales;
    private ReturnService $returns;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->store = Store::create(['name' => 'Tienda Central', 'is_active' => true]);

        $register = CashRegister::create([
            'store_id'  => $this->store->id,
            'name'      => 'Caja 1',
            'is_active' => true,
        ]);

        $this->shift = CashShift::create([
            'cash_register_id' => $register->id,
            'user_id'          => $this->user->id,
            'opening_amount'   => 100,
            'opened_at'        => now(),
            'status'           => 'open',
        ]);

        $this->product = Product::create([
            'name'            => 'Aceite 1L',
            'slug'            => 'aceite-1l',
            'sku'             => 'ACE-001',
            'barcode'         => '7501234567890',
            'price'           => 20,
            'cost'            => 10,
            'stock'           => 0,
            'status'          => 'active',
            'track_inventory' => true,
        ]);

        $this->sales   = app(SaleService::class);
        $this->returns = app(ReturnService::class);

        // Stock inicial vía una compra recibida, como en el flujo real.
        $purchase = app(PurchaseService::class)->create([
            'store_id' => $this->store->id,
            'date'     => now()->toDateString(),
            'tax'      => 0,
        ], [
            ['product_id' => $this->product->id, 'quantity' => 100, 'cost' => 10],
        ]);
        app(PurchaseService::class)->receive($purchase);
    }

    private function sell(float $qty = 10, array $overrides = []): Sale
    {
        return $this->sales->create($this->shift, array_merge([
            'amount_paid'    => 1000,
            'payment_method' => 'cash',
            'tax'            => 0,
        ], $overrides), [
            ['product_id' => $this->product->id, 'quantity' => $qty, 'price' => 20, 'discount' => 0],
        ]);
    }

    private function storeStock(): int
    {
        return (int) StoreStock::where('store_id', $this->store->id)
            ->where('product_id', $this->product->id)
            ->value('stock');
    }

    // ── Ventas ↔ Inventario ─────────────────────────────────────────────────

    public function test_a_sale_discounts_stock_from_the_shift_store(): void
    {
        $this->sell(qty: 10);

        $this->assertSame(90, $this->storeStock());
        $this->assertSame(90, $this->product->fresh()->stock);
    }

    public function test_it_sums_repeated_lines_of_the_same_product_before_checking_stock(): void
    {
        // 60 + 60 = 120 sobre 100 disponibles: validar línea por línea lo dejaba pasar.
        $this->expectException(ValidationException::class);

        $this->sales->create($this->shift, [
            'amount_paid'    => 5000,
            'payment_method' => 'cash',
        ], [
            ['product_id' => $this->product->id, 'quantity' => 60, 'price' => 20],
            ['product_id' => $this->product->id, 'quantity' => 60, 'price' => 20],
        ]);
    }

    // ── Ventas ↔ Devoluciones ───────────────────────────────────────────────

    public function test_it_rejects_returning_more_units_than_remain_across_returns(): void
    {
        $sale     = $this->sell(qty: 10);
        $saleItem = $sale->items->first();

        $first = $this->returns->create($sale, ['restock' => true], [
            ['sale_item_id' => $saleItem->id, 'product_id' => $this->product->id, 'quantity' => 6],
        ]);
        $this->returns->complete($first);

        $this->assertSame(96, $this->storeStock());

        // Quedan 4: pedir 6 más debe rechazarse.
        $this->expectException(ValidationException::class);

        $this->returns->create($sale->fresh(), ['restock' => true], [
            ['sale_item_id' => $saleItem->id, 'product_id' => $this->product->id, 'quantity' => 6],
        ]);
    }

    public function test_cancelling_does_not_restock_units_already_returned(): void
    {
        $sale     = $this->sell(qty: 10);
        $saleItem = $sale->items->first();

        $return = $this->returns->create($sale, ['restock' => true], [
            ['sale_item_id' => $saleItem->id, 'product_id' => $this->product->id, 'quantity' => 4],
        ]);
        $this->returns->complete($return);

        $this->assertSame(94, $this->storeStock());

        $this->sales->cancel($sale->fresh(), 'Prueba', $this->user->id);

        // Solo se reponen las 6 unidades que quedaban, no las 10 vendidas.
        $this->assertSame(100, $this->storeStock());
    }

    public function test_it_refuses_returns_against_a_cancelled_sale(): void
    {
        $sale     = $this->sell(qty: 10);
        $saleItem = $sale->items->first();

        $this->sales->cancel($sale, 'Prueba', $this->user->id);

        $this->expectException(ValidationException::class);

        $this->returns->create($sale->fresh(), ['restock' => true], [
            ['sale_item_id' => $saleItem->id, 'product_id' => $this->product->id, 'quantity' => 1],
        ]);
    }

    // ── Ventas ↔ Promociones ────────────────────────────────────────────────

    public function test_cancelling_releases_the_promotion_usage(): void
    {
        $promotion = Promotion::create([
            'name'       => '10% de descuento',
            'type'       => 'percentage',
            'value'      => 10,
            'scope'      => 'all',
            'starts_at'  => now()->subDay(),
            'ends_at'    => now()->addDay(),
            'is_active'  => true,
            'used_count' => 0,
        ]);

        $sale = $this->sell(qty: 10, overrides: ['promotion_id' => $promotion->id]);

        $this->assertSame(1, (int) $promotion->fresh()->used_count);

        $this->sales->cancel($sale->fresh(), 'Prueba', $this->user->id);

        $this->assertSame(0, (int) $promotion->fresh()->used_count);
    }

    // ── Ventas ↔ Caja ───────────────────────────────────────────────────────

    public function test_a_sale_in_a_closed_shift_can_no_longer_be_edited(): void
    {
        $sale = $this->sell(qty: 10);

        $this->shift->update(['status' => 'closed', 'closed_at' => now()]);

        $this->expectException(ValidationException::class);

        $this->sales->update($sale->fresh(), [
            'amount_paid'    => 1000,
            'payment_method' => 'cash',
        ], [
            ['product_id' => $this->product->id, 'quantity' => 5, 'price' => 20],
        ]);
    }

    public function test_a_sale_with_returns_can_no_longer_be_edited(): void
    {
        $sale     = $this->sell(qty: 10);
        $saleItem = $sale->items->first();

        $this->returns->create($sale, ['restock' => true], [
            ['sale_item_id' => $saleItem->id, 'product_id' => $this->product->id, 'quantity' => 2],
        ]);

        $this->expectException(ValidationException::class);

        $this->sales->update($sale->fresh(), [
            'amount_paid'    => 1000,
            'payment_method' => 'cash',
        ], [
            ['product_id' => $this->product->id, 'quantity' => 5, 'price' => 20],
        ]);
    }

    // ── Folios ──────────────────────────────────────────────────────────────

    public function test_sale_folios_are_sequential(): void
    {
        $first  = $this->sell(qty: 1);
        $second = $this->sell(qty: 1);

        $this->assertSame('V-000001', $first->folio);
        $this->assertSame('V-000002', $second->folio);
    }
}
