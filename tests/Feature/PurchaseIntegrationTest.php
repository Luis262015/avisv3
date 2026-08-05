<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PurchaseOrderService;
use App\Services\PurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

/**
 * Integración del módulo de Compras con Inventario, Finanzas (CxP),
 * Proveedores y Órdenes de compra.
 */
class PurchaseIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Store $store;
    private Supplier $supplier;
    private Product $product;
    private PurchaseService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->store    = Store::create(['name' => 'Tienda Central', 'is_active' => true]);
        $this->supplier = Supplier::create([
            'name'           => 'Distribuidora Sur',
            'payment_terms'  => '15 días',
            'lead_time_days' => 5,
            'is_active'      => true,
        ]);
        $this->product = Product::create([
            'name'   => 'Aceite 1L',
            'slug'   => 'aceite-1l',
            'sku'    => 'ACE-001',
            'price'  => 20,
            'cost'   => 10,
            'stock'  => 0,
            'status' => 'active',
        ]);

        $this->service = app(PurchaseService::class);
    }

    private function makePurchase(float $qty = 10, float $cost = 12): Purchase
    {
        return $this->service->create([
            'supplier_id' => $this->supplier->id,
            'store_id'    => $this->store->id,
            'date'        => now()->toDateString(),
            'tax'         => 0,
        ], [
            ['product_id' => $this->product->id, 'quantity' => $qty, 'cost' => $cost],
        ]);
    }

    private function storeStock(): int
    {
        return (int) StoreStock::where('store_id', $this->store->id)
            ->where('product_id', $this->product->id)
            ->value('stock');
    }

    // ── Compras ↔ Inventario ────────────────────────────────────────────────

    public function test_receiving_loads_stock_into_the_purchase_store_and_syncs_global_stock(): void
    {
        $purchase = $this->makePurchase(qty: 10);

        $this->service->receive($purchase);

        $this->assertSame(10, $this->storeStock());
        $this->assertSame(10, $this->product->fresh()->stock);
        $this->assertEquals('12.00', $this->product->fresh()->cost);
    }

    public function test_receiving_recalculates_the_product_cost_as_a_weighted_average(): void
    {
        // Primera entrada sin existencias previas: el costo es el de la compra.
        $this->service->receive($this->makePurchase(qty: 10, cost: 10));
        $this->assertEquals('10.00', $this->product->fresh()->cost);

        // Segunda entrada: 10 @ 10 ya en stock + 10 @ 20 → (100 + 200) / 20 = 15.
        $this->service->receive($this->makePurchase(qty: 10, cost: 20));

        $this->assertEquals('15.00', $this->product->fresh()->cost);
        $this->assertSame(20, $this->product->fresh()->stock);
    }

    public function test_partial_receipts_average_only_the_quantity_actually_received(): void
    {
        $this->service->receive($this->makePurchase(qty: 10, cost: 10));

        // Se piden 10 @ 20 pero solo llegan 5: promedia contra 5, no contra 10.
        $purchase = $this->makePurchase(qty: 10, cost: 20);
        $this->service->receivePartial($purchase, [
            ['id' => $purchase->items->first()->id, 'received_quantity' => 5],
        ]);

        // (10 × 10 + 5 × 20) / 15 = 13.33
        $this->assertEquals('13.33', $this->product->fresh()->cost);
    }

    public function test_it_averages_two_lots_of_the_same_product_in_one_purchase(): void
    {
        // Mismo producto en dos líneas, costos distintos (lotes distintos).
        $purchase = $this->service->create([
            'supplier_id' => $this->supplier->id,
            'store_id'    => $this->store->id,
            'date'        => now()->toDateString(),
            'tax'         => 0,
        ], [
            ['product_id' => $this->product->id, 'quantity' => 10, 'cost' => 10],
            ['product_id' => $this->product->id, 'quantity' => 10, 'cost' => 20],
        ]);

        $this->service->receive($purchase);

        // Línea 1 sin stock previo → 10. Línea 2: (10 × 10 + 10 × 20) / 20 = 15.
        $this->assertEquals('15.00', $this->product->fresh()->cost);
        $this->assertSame(20, $this->storeStock());
    }

    public function test_it_rejects_receiving_more_than_the_pending_quantity(): void
    {
        $purchase = $this->makePurchase(qty: 10);

        $this->service->receivePartial($purchase, [
            ['id' => $purchase->items->first()->id, 'received_quantity' => 4],
        ]);

        $this->assertSame('partial', $purchase->fresh()->status);
        $this->assertSame(4, $this->storeStock());

        $this->expectException(ValidationException::class);

        $this->service->receivePartial($purchase->fresh(), [
            ['id' => $purchase->items()->first()->id, 'received_quantity' => 7],
        ]);
    }

    public function test_it_refuses_to_receive_a_purchase_without_a_store(): void
    {
        $purchase = $this->makePurchase();
        $purchase->update(['store_id' => null]);

        $this->expectException(RuntimeException::class);

        $this->service->receive($purchase->fresh());
    }

    public function test_cancelling_reverses_only_what_was_actually_received(): void
    {
        $purchase = $this->makePurchase(qty: 10);

        $this->service->receivePartial($purchase, [
            ['id' => $purchase->items->first()->id, 'received_quantity' => 6],
        ]);
        $this->assertSame(6, $this->storeStock());

        $this->service->cancel($purchase->fresh());

        $this->assertSame(0, $this->storeStock());
        $this->assertSame('cancelled', $purchase->fresh()->status);
    }

    // ── Compras ↔ Finanzas (CxP) ────────────────────────────────────────────

    public function test_payable_due_date_comes_from_the_supplier_payment_terms(): void
    {
        $purchase = $this->makePurchase(qty: 10, cost: 12);

        $this->service->receive($purchase);
        $payable = $purchase->fresh()->payable;

        $this->assertNotNull($payable);
        $this->assertSame(120.0, (float) $payable->amount);
        // "15 días" en las condiciones del proveedor, no los 30 fijos de antes.
        $this->assertSame(
            now()->addDays(15)->toDateString(),
            $payable->due_date->toDateString()
        );
    }

    public function test_settling_the_payable_updates_the_purchase_payment_status(): void
    {
        $purchase = $this->makePurchase(qty: 10, cost: 12);
        $this->service->receive($purchase);

        $payable = $purchase->fresh()->payable;

        // Mismo orden que PayableController::storePayment: pago primero, estado después.
        $payable->payments()->create([
            'user_id'        => $this->user->id,
            'amount'         => 50,
            'payment_method' => 'cash',
            'date'           => now()->toDateString(),
        ]);
        $payable->update(['amount_paid' => 50, 'balance' => 70, 'status' => 'partial']);

        $this->assertSame('partial', $purchase->fresh()->payment_status);

        $payable->update(['amount_paid' => 120, 'balance' => 0, 'status' => 'paid']);

        $this->assertSame('paid', $purchase->fresh()->payment_status);
    }

    public function test_it_blocks_cancelling_a_purchase_that_already_has_payments(): void
    {
        $purchase = $this->makePurchase();
        $this->service->receive($purchase);

        $purchase->fresh()->payable->update([
            'amount_paid' => 50, 'balance' => 70, 'status' => 'partial',
        ]);

        $this->expectException(RuntimeException::class);

        $this->service->cancel($purchase->fresh());
    }

    // ── Compras ↔ Órdenes de compra ─────────────────────────────────────────

    public function test_the_order_stays_in_progress_until_the_purchase_is_really_received(): void
    {
        $orderService = app(PurchaseOrderService::class);

        $order = $orderService->create([
            'supplier_id'   => $this->supplier->id,
            'store_id'      => $this->store->id,
            'date'          => now()->toDateString(),
            'expected_date' => now()->addDays(5)->toDateString(),
        ], [
            ['product_id' => $this->product->id, 'quantity' => 10, 'cost' => 12],
        ]);

        $orderService->confirm($order);
        $purchase = $orderService->convertToPurchase($order->fresh());

        // Convertida ≠ recibida.
        $this->assertSame('partial', $order->fresh()->status);
        $this->assertSame(0.0, (float) $order->fresh()->items->first()->quantity_received);

        $this->service->receive($purchase);

        $this->assertSame('received', $order->fresh()->status);
        $this->assertSame(10.0, (float) $order->fresh()->items->first()->quantity_received);
    }

    public function test_an_order_cannot_be_converted_twice(): void
    {
        $orderService = app(PurchaseOrderService::class);

        $order = $orderService->create([
            'supplier_id' => $this->supplier->id,
            'store_id'    => $this->store->id,
            'date'        => now()->toDateString(),
        ], [
            ['product_id' => $this->product->id, 'quantity' => 10, 'cost' => 12],
        ]);

        $orderService->confirm($order);
        $orderService->convertToPurchase($order->fresh());

        $this->expectException(RuntimeException::class);

        $orderService->convertToPurchase($order->fresh());
    }

    // ── Integridad de la compra ─────────────────────────────────────────────

    public function test_a_received_purchase_can_no_longer_be_edited(): void
    {
        $purchase = $this->makePurchase();
        $this->service->receive($purchase);

        $this->expectException(RuntimeException::class);

        $this->service->update($purchase->fresh(), [
            'supplier_id' => $this->supplier->id,
            'store_id'    => $this->store->id,
            'date'        => now()->toDateString(),
        ], [
            ['product_id' => $this->product->id, 'quantity' => 99, 'cost' => 1],
        ]);
    }
}
