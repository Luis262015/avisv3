<?php

namespace Tests\Feature;

use App\Models\CashRegister;
use App\Models\CashShift;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Receivable;
use App\Models\Store;
use App\Models\User;
use App\Services\FinancialReportService;
use App\Services\PurchaseService;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Integración del módulo de Finanzas con Caja, Ventas, Compras y Clientes.
 */
class FinanceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Store $store;
    private CashShift $shift;
    private Product $product;

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
            'name' => 'Aceite 1L', 'slug' => 'aceite-1l', 'sku' => 'ACE-001',
            'price' => 20, 'cost' => 10, 'stock' => 0, 'status' => 'active',
            'track_inventory' => true,
        ]);

        $purchase = app(PurchaseService::class)->create([
            'store_id' => $this->store->id,
            'date'     => now()->toDateString(),
            'tax'      => 0,
        ], [
            ['product_id' => $this->product->id, 'quantity' => 100, 'cost' => 10],
        ]);
        app(PurchaseService::class)->receive($purchase);
    }

    // ── Finanzas ↔ Caja ─────────────────────────────────────────────────────

    public function test_a_cash_expense_cannot_be_registered_against_a_closed_shift(): void
    {
        $this->shift->update(['status' => 'closed', 'closed_at' => now()]);

        $this->post('/admin/expenses', [
            'cash_shift_id'  => $this->shift->id,
            'category'       => 'Servicios',
            'description'    => 'Luz',
            'amount'         => 50,
            'payment_method' => 'cash',
            'date'           => now()->toDateString(),
        ])->assertSessionHasErrors('cash_shift_id');

        $this->assertDatabaseCount('expenses', 0);
    }

    public function test_a_cash_expense_requires_a_shift(): void
    {
        $this->post('/admin/expenses', [
            'category'       => 'Servicios',
            'description'    => 'Luz',
            'amount'         => 50,
            'payment_method' => 'cash',
            'date'           => now()->toDateString(),
        ])->assertSessionHasErrors('cash_shift_id');
    }

    public function test_a_transfer_expense_does_not_require_a_shift_but_does_require_a_store(): void
    {
        $this->post('/admin/expenses', [
            'store_id'       => $this->store->id,
            'category'       => 'Servicios',
            'description'    => 'Internet',
            'amount'         => 50,
            'payment_method' => 'transfer',
            'date'           => now()->toDateString(),
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('expenses', [
            'description'   => 'Internet',
            'store_id'      => $this->store->id,
            'cash_shift_id' => null,
        ]);
    }

    public function test_an_expense_always_requires_a_store(): void
    {
        $this->post('/admin/expenses', [
            'category'       => 'Servicios',
            'description'    => 'Internet',
            'amount'         => 50,
            'payment_method' => 'transfer',
            'date'           => now()->toDateString(),
        ])->assertSessionHasErrors('store_id');

        $this->assertDatabaseCount('expenses', 0);
    }

    public function test_the_shift_store_overrides_whatever_store_was_submitted(): void
    {
        $otraTienda = Store::create(['name' => 'Sucursal Norte', 'is_active' => true]);

        $this->post('/admin/expenses', [
            'cash_shift_id'  => $this->shift->id,
            'store_id'       => $otraTienda->id, // contradice la caja del turno
            'category'       => 'Insumos',
            'description'    => 'Papel',
            'amount'         => 30,
            'payment_method' => 'cash',
            'date'           => now()->toDateString(),
        ])->assertSessionHasNoErrors();

        // Manda la tienda de la caja del turno, no la enviada por el formulario.
        $this->assertDatabaseHas('expenses', [
            'description' => 'Papel',
            'store_id'    => $this->store->id,
        ]);
    }

    public function test_expenses_without_a_shift_still_appear_when_filtering_by_store(): void
    {
        $otraTienda = Store::create(['name' => 'Sucursal Norte', 'is_active' => true]);

        // Pagado por transferencia: sin turno, pero atribuido a la tienda central.
        $this->post('/admin/expenses', [
            'store_id'       => $this->store->id,
            'category'       => 'Servicios',
            'description'    => 'Internet',
            'amount'         => 250,
            'payment_method' => 'transfer',
            'date'           => now()->toDateString(),
        ])->assertSessionHasNoErrors();

        $reports = app(FinancialReportService::class);

        $propia = $reports->summary(['period' => 'month', 'store_id' => $this->store->id]);
        $ajena  = $reports->summary(['period' => 'month', 'store_id' => $otraTienda->id]);

        // Antes se perdía al filtrar, porque la tienda solo se alcanzaba por el turno.
        $this->assertSame(250.0, $propia['expenses']);
        $this->assertSame(0.0, $ajena['expenses']);
    }

    public function test_incomes_are_also_scoped_by_their_own_store(): void
    {
        $otraTienda = Store::create(['name' => 'Sucursal Norte', 'is_active' => true]);

        $this->post('/admin/incomes', [
            'store_id'       => $otraTienda->id,
            'category'       => 'Préstamo',
            'description'    => 'Aporte socio',
            'amount'         => 500,
            'payment_method' => 'transfer',
            'date'           => now()->toDateString(),
        ])->assertSessionHasNoErrors();

        $reports = app(FinancialReportService::class);

        $this->assertSame(0.0, $reports->summary(['period' => 'month', 'store_id' => $this->store->id])['incomes']);
        $this->assertSame(500.0, $reports->summary(['period' => 'month', 'store_id' => $otraTienda->id])['incomes']);
    }

    public function test_a_withdrawal_always_requires_an_open_shift(): void
    {
        $this->post('/admin/withdrawals', [
            'amount' => 50,
            'reason' => 'Depósito bancario',
            'date'   => now()->toDateString(),
        ])->assertSessionHasErrors('cash_shift_id');
    }

    // ── Finanzas ↔ Ventas ───────────────────────────────────────────────────

    public function test_cancelling_a_sale_cancels_its_receivable(): void
    {
        $sale = app(SaleService::class)->create($this->shift, [
            'amount_paid'    => 0,
            'payment_method' => 'cash',
        ], [
            ['product_id' => $this->product->id, 'quantity' => 5, 'price' => 20],
        ]);

        $receivable = Receivable::create([
            'sale_id'       => $sale->id,
            'user_id'       => $this->user->id,
            'customer_name' => 'Juan Pérez',
            'description'   => 'Venta a crédito',
            'amount'        => 100,
            'amount_paid'   => 0,
            'balance'       => 100,
            'due_date'      => now()->addDays(30),
            'status'        => 'pending',
        ]);

        app(SaleService::class)->cancel($sale->fresh(), 'Prueba', $this->user->id);

        $receivable->refresh();
        $this->assertSame('cancelled', $receivable->status);
        $this->assertSame(0.0, (float) $receivable->balance);
    }

    public function test_a_receivable_with_payments_survives_the_sale_cancellation(): void
    {
        $sale = app(SaleService::class)->create($this->shift, [
            'amount_paid'    => 0,
            'payment_method' => 'cash',
        ], [
            ['product_id' => $this->product->id, 'quantity' => 5, 'price' => 20],
        ]);

        $receivable = Receivable::create([
            'sale_id'       => $sale->id,
            'user_id'       => $this->user->id,
            'customer_name' => 'Juan Pérez',
            'description'   => 'Venta a crédito',
            'amount'        => 100,
            'amount_paid'   => 40,
            'balance'       => 60,
            'due_date'      => now()->addDays(30),
            'status'        => 'partial',
        ]);

        app(SaleService::class)->cancel($sale->fresh(), 'Prueba', $this->user->id);

        // Ese dinero sí entró: la cuenta no se toca, se resuelve como devolución.
        $this->assertSame('partial', $receivable->fresh()->status);
    }

    // ── Finanzas ↔ Clientes ─────────────────────────────────────────────────

    public function test_a_customer_reports_its_outstanding_balance_across_receivables(): void
    {
        $customer = Customer::create(['name' => 'Comercial Andina', 'is_active' => true]);

        foreach ([['pending', 100.0], ['partial', 40.0], ['paid', 0.0], ['cancelled', 0.0]] as [$status, $balance]) {
            Receivable::create([
                'customer_id'   => $customer->id,
                'user_id'       => $this->user->id,
                'customer_name' => $customer->name,
                'description'   => "Cuenta {$status}",
                'amount'        => 100,
                'amount_paid'   => 100 - $balance,
                'balance'       => $balance,
                'due_date'      => now()->addDays(30),
                'status'        => $status,
            ]);
        }

        // Solo cuentan las cuentas vivas: 100 + 40.
        $this->assertSame(140.0, $customer->outstandingBalance());
    }

    // ── Reportes ────────────────────────────────────────────────────────────

    public function test_the_store_filter_reaches_receivables_and_payables(): void
    {
        $otraTienda = Store::create(['name' => 'Sucursal Norte', 'is_active' => true]);

        $sale = app(SaleService::class)->create($this->shift, [
            'amount_paid'    => 0,
            'payment_method' => 'cash',
        ], [
            ['product_id' => $this->product->id, 'quantity' => 5, 'price' => 20],
        ]);

        Receivable::create([
            'sale_id'       => $sale->id,
            'user_id'       => $this->user->id,
            'customer_name' => 'Juan Pérez',
            'description'   => 'Venta a crédito',
            'amount'        => 100,
            'amount_paid'   => 0,
            'balance'       => 100,
            'due_date'      => now()->addDays(30),
            'status'        => 'pending',
        ]);

        $reports = app(FinancialReportService::class);

        // La tienda del turno sí la ve; la otra no.
        $propia = $reports->receivables(['period' => 'month', 'store_id' => $this->store->id]);
        $ajena  = $reports->receivables(['period' => 'month', 'store_id' => $otraTienda->id]);

        $this->assertSame(100.0, $propia['outstanding_balance']);
        $this->assertSame(0.0, $ajena['outstanding_balance']);
    }

    public function test_purchases_reach_the_payables_report_scoped_by_store(): void
    {
        $otraTienda = Store::create(['name' => 'Sucursal Norte', 'is_active' => true]);
        $reports    = app(FinancialReportService::class);

        // El setUp ya dejó una compra recibida de 1000 en la tienda central,
        // con su cuenta por pagar creada automáticamente.
        $propia = $reports->payables(['period' => 'month', 'store_id' => $this->store->id]);
        $ajena  = $reports->payables(['period' => 'month', 'store_id' => $otraTienda->id]);

        $this->assertSame(1000.0, $propia['outstanding_balance']);
        $this->assertSame(0.0, $ajena['outstanding_balance']);
    }
}
