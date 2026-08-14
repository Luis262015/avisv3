<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CashRegister;
use App\Models\CashShift;
use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Store;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\PromotionService;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Auditoría de las promociones: cálculo del descuento, vigencia, cupo de usos y
 * qué ocurre cuando la venta se edita o se anula.
 */
class PromotionAuditTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Store $store;
    private CashShift $shift;
    private SaleService $sales;
    private PromotionService $promos;

    protected function setUp(): void
    {
        parent::setUp();

        config(['siat.timezone' => 'America/La_Paz']);

        // Con rol: las rutas de promociones viven tras `role:admin|operador` y sin
        // él la petición muere en un 403 antes de llegar a la validación.
        $this->user = User::factory()->create();
        $this->user->assignRole(Role::findOrCreate('admin', 'web'));
        $this->actingAs($this->user);

        $this->store = Store::create(['name' => 'Tienda Central', 'is_active' => true]);

        $register = CashRegister::create([
            'store_id' => $this->store->id, 'name' => 'Caja 1', 'is_active' => true,
        ]);

        $this->shift = CashShift::create([
            'cash_register_id' => $register->id, 'user_id' => $this->user->id,
            'opening_amount' => 0, 'opened_at' => now(), 'status' => 'open',
        ]);

        $this->sales  = app(SaleService::class);
        $this->promos = app(PromotionService::class);
    }

    // ── Ayudas ──────────────────────────────────────────────────────────────

    private function producto(string $nombre, float $precio, int $stock = 100, ?Category $cat = null): Product
    {
        $p = Product::create([
            'name' => $nombre, 'sku' => strtoupper(str_replace(' ', '-', $nombre)),
            'price' => $precio, 'cost' => $precio / 2, 'stock' => 0, 'min_stock' => 0,
            'unit' => 'pza', 'status' => 'active', 'track_inventory' => true,
            'category_id' => $cat?->id,
        ]);

        app(InventoryService::class)->adjust($p, $stock, 'Carga de prueba', $this->store->id);

        return $p;
    }

    /** @param array<int, array{product_id:int, quantity:float, price:float}> $items */
    private function vender(array $items, ?int $promotionId = null, float $descuentoManual = 0)
    {
        $total = collect($items)->sum(fn ($i) => $i['quantity'] * $i['price']);

        return $this->sales->create($this->shift, [
            'payment_method' => 'cash',
            'amount_paid'    => $total + 10000,
            'promotion_id'   => $promotionId,
            'discount'       => $descuentoManual,
        ], $items);
    }

    private function linea(Product $p, float $qty): array
    {
        return ['product_id' => $p->id, 'quantity' => $qty, 'price' => (float) $p->price, 'discount' => 0];
    }

    // ── Cálculo del descuento ───────────────────────────────────────────────

    public function test_el_porcentaje_se_aplica_sobre_el_subtotal(): void
    {
        $p = $this->producto('Mouse', 100);

        $promo = Promotion::create([
            'name' => '10% de descuento', 'type' => 'percentage', 'value' => 10,
            'scope' => 'all', 'is_active' => true,
        ]);

        $venta = $this->vender([$this->linea($p, 3)], $promo->id);

        $this->assertSame('300.00', $venta->subtotal);
        $this->assertSame('30.00', $venta->discount);
        $this->assertSame('270.00', $venta->total);
    }

    public function test_el_monto_fijo_nunca_supera_el_subtotal(): void
    {
        $p = $this->producto('Cable', 20);

        $promo = Promotion::create([
            'name' => 'Bs 50 menos', 'type' => 'fixed', 'value' => 50,
            'scope' => 'all', 'is_active' => true,
        ]);

        // Carrito de 40: el descuento fijo de 50 no puede dejar el total negativo.
        $venta = $this->vender([$this->linea($p, 2)], $promo->id);

        $this->assertSame('40.00', $venta->discount);
        $this->assertSame('0.00', $venta->total);
    }

    public function test_el_alcance_por_producto_solo_descuenta_esos_productos(): void
    {
        $incluido = $this->producto('Teclado', 200);
        $excluido = $this->producto('Monitor', 900);

        $promo = Promotion::create([
            'name' => '10% en teclados', 'type' => 'percentage', 'value' => 10,
            'scope' => 'product', 'is_active' => true,
        ]);
        $promo->products()->sync([$incluido->id]);

        $venta = $this->vender([$this->linea($incluido, 1), $this->linea($excluido, 1)], $promo->id);

        $this->assertSame('1100.00', $venta->subtotal);
        $this->assertSame('20.00', $venta->discount, 'Solo el 10% de los 200 del teclado.');
    }

    public function test_el_alcance_por_categoria_solo_descuenta_esa_categoria(): void
    {
        $cat = Category::create(['name' => 'Periféricos', 'slug' => 'perifericos']);
        $dentro = $this->producto('Mouse', 100, cat: $cat);
        $fuera  = $this->producto('Laptop', 5000);

        $promo = Promotion::create([
            'name' => '20% periféricos', 'type' => 'percentage', 'value' => 20,
            'scope' => 'category', 'is_active' => true,
        ]);
        $promo->categories()->sync([$cat->id]);

        $venta = $this->vender([$this->linea($dentro, 2), $this->linea($fuera, 1)], $promo->id);

        $this->assertSame('40.00', $venta->discount);
    }

    public function test_se_rechaza_si_no_se_alcanza_la_compra_minima(): void
    {
        $p = $this->producto('Mouse', 100);

        $promo = Promotion::create([
            'name' => 'Desde Bs 500', 'type' => 'percentage', 'value' => 10,
            'scope' => 'all', 'min_purchase' => 500, 'is_active' => true,
        ]);

        $this->expectException(ValidationException::class);
        $this->vender([$this->linea($p, 2)], $promo->id);
    }

    /**
     * En un 2x1 el regalo debería valer lo que la unidad más barata; repartir el
     * precio medio hace que el cliente se lleve de más cuando mezcla productos de
     * precios distintos.
     */
    public function test_en_un_2x1_lo_gratis_es_la_unidad_mas_barata(): void
    {
        $caro   = $this->producto('Monitor', 900);
        $barato = $this->producto('Mouse', 100);

        $promo = Promotion::create([
            'name' => '2x1', 'type' => 'buy_x_get_y', 'value' => 0,
            'buy_qty' => 1, 'get_qty' => 1, 'scope' => 'all', 'is_active' => true,
        ]);

        $venta = $this->vender([$this->linea($caro, 1), $this->linea($barato, 1)], $promo->id);

        $this->assertSame('100.00', $venta->discount, 'Lo gratis debe ser el mouse, no el promedio.');
    }

    // ── Vigencia y cupo ─────────────────────────────────────────────────────

    public function test_una_promocion_inactiva_se_rechaza(): void
    {
        $p = $this->producto('Mouse', 100);

        $promo = Promotion::create([
            'name' => 'Apagada', 'type' => 'percentage', 'value' => 10,
            'scope' => 'all', 'is_active' => false,
        ]);

        $this->expectException(ValidationException::class);
        $this->vender([$this->linea($p, 1)], $promo->id);
    }

    public function test_una_promocion_caducada_se_rechaza(): void
    {
        $p = $this->producto('Mouse', 100);

        $promo = Promotion::create([
            'name' => 'Vencida', 'type' => 'percentage', 'value' => 10,
            'scope' => 'all', 'is_active' => true,
            'ends_at' => now()->subDays(3)->toDateString(),
        ]);

        $this->expectException(ValidationException::class);
        $this->vender([$this->linea($p, 1)], $promo->id);
    }

    /**
     * La aplicación corre en UTC y el negocio en Bolivia (UTC-4). Una promoción
     * que termina hoy sigue siendo válida hasta la medianoche **de Bolivia**; a
     * las 21:00 locales el día en UTC ya es el siguiente y la promoción se
     * apagaría cuatro horas antes de tiempo, en plena tarde de ventas.
     */
    public function test_una_promocion_que_termina_hoy_vale_hasta_la_medianoche_de_bolivia(): void
    {
        // 01:00 UTC del 14 = 21:00 del 13 en Bolivia.
        Carbon::setTestNow(Carbon::parse('2026-08-14 01:00:00', 'UTC'));

        $p = $this->producto('Mouse', 100);

        $promo = Promotion::create([
            'name' => 'Último día', 'type' => 'percentage', 'value' => 10,
            'scope' => 'all', 'is_active' => true,
            'ends_at' => '2026-08-13',
        ]);

        $venta = $this->vender([$this->linea($p, 1)], $promo->id);

        $this->assertSame('10.00', $venta->discount);

        Carbon::setTestNow();
    }

    public function test_se_respeta_el_limite_de_usos(): void
    {
        $p = $this->producto('Mouse', 100);

        $promo = Promotion::create([
            'name' => 'Solo una vez', 'type' => 'percentage', 'value' => 10,
            'scope' => 'all', 'is_active' => true, 'usage_limit' => 1,
        ]);

        $this->vender([$this->linea($p, 1)], $promo->id);
        $this->assertSame(1, (int) $promo->fresh()->used_count);

        $this->expectException(ValidationException::class);
        $this->vender([$this->linea($p, 1)], $promo->id);
    }

    public function test_anular_la_venta_libera_el_cupo(): void
    {
        $p = $this->producto('Mouse', 100);

        $promo = Promotion::create([
            'name' => 'Con cupo', 'type' => 'percentage', 'value' => 10,
            'scope' => 'all', 'is_active' => true, 'usage_limit' => 5,
        ]);

        $venta = $this->vender([$this->linea($p, 1)], $promo->id);
        $this->assertSame(1, (int) $promo->fresh()->used_count);

        $this->sales->cancel($venta, 'Prueba');

        $this->assertSame(0, (int) $promo->fresh()->used_count);
    }

    // ── Edición de la venta ─────────────────────────────────────────────────

    /**
     * Al editar se recalcula el descuento, pero **no** se vuelven a comprobar las
     * condiciones. Si el carrito baja por debajo de la compra mínima, la
     * promoción tendría que dejar de aplicarse.
     */
    public function test_al_editar_por_debajo_del_minimo_la_promocion_deja_de_aplicar(): void
    {
        $p = $this->producto('Mouse', 100);

        $promo = Promotion::create([
            'name' => 'Desde Bs 500', 'type' => 'percentage', 'value' => 10,
            'scope' => 'all', 'min_purchase' => 500, 'is_active' => true,
        ]);

        $venta = $this->vender([$this->linea($p, 6)], $promo->id); // 600, aplica
        $this->assertSame('60.00', $venta->discount);

        // Se corrige la venta a 1 unidad: 100, muy por debajo del mínimo.
        $this->sales->update($venta, [
            'payment_method' => 'cash', 'amount_paid' => 1000, 'tax' => 0,
        ], [$this->linea($p, 1)]);

        $venta->refresh();

        $this->assertSame('100.00', $venta->subtotal);
        $this->assertSame('0.00', $venta->discount, 'Ya no se cumple la compra mínima.');
    }

    // ── Validación del formulario ───────────────────────────────────────────

    // ── Combos ──────────────────────────────────────────────────────────────

    /**
     * @param array<int, array{0: Product, 1: float}> $lineas
     */
    private function combo(string $nombre, float $precio, array $lineas, ?int $limite = null): Promotion
    {
        $combo = Promotion::create([
            'name' => $nombre, 'type' => 'combo', 'value' => 0, 'combo_price' => $precio,
            'scope' => 'all', 'is_active' => true, 'usage_limit' => $limite,
        ]);

        foreach ($lineas as [$producto, $cantidad]) {
            $combo->comboItems()->create(['product_id' => $producto->id, 'quantity' => $cantidad]);
        }

        return $combo->load('comboItems');
    }

    /**
     * El punto de venta expande el combo en líneas de producto, así que sin
     * registrarlo la venta no dejaba rastro y `used_count` no subía nunca: el
     * `usage_limit` de un combo no limitaba nada.
     */
    public function test_aplicar_un_combo_consume_su_cupo(): void
    {
        $teclado = $this->producto('Teclado', 200);
        $mouse   = $this->producto('Mouse', 100);

        $combo = $this->combo('Kit oficina', 250, [[$teclado, 1], [$mouse, 1]]);

        $venta = $this->sales->create($this->shift, [
            'payment_method' => 'cash', 'amount_paid' => 1000,
            'combos' => [['promotion_id' => $combo->id, 'quantity' => 1]],
        ], [$this->linea($teclado, 1), $this->linea($mouse, 1)]);

        $this->assertSame(1, (int) $combo->fresh()->used_count);
        $this->assertSame(1, $venta->combos()->count());
        $this->assertSame(250.0, (float) $venta->combos()->first()->pivot->combo_price);
    }

    public function test_anular_la_venta_libera_el_cupo_del_combo(): void
    {
        $teclado = $this->producto('Teclado', 200);
        $mouse   = $this->producto('Mouse', 100);

        $combo = $this->combo('Kit oficina', 250, [[$teclado, 1], [$mouse, 1]]);

        $venta = $this->sales->create($this->shift, [
            'payment_method' => 'cash', 'amount_paid' => 1000,
            'combos' => [['promotion_id' => $combo->id, 'quantity' => 2]],
        ], [$this->linea($teclado, 2), $this->linea($mouse, 2)]);

        $this->assertSame(2, (int) $combo->fresh()->used_count);

        $this->sales->cancel($venta, 'Prueba');

        $this->assertSame(0, (int) $combo->fresh()->used_count);
    }

    public function test_se_respeta_el_limite_de_usos_de_un_combo(): void
    {
        $teclado = $this->producto('Teclado', 200);
        $mouse   = $this->producto('Mouse', 100);

        $combo = $this->combo('Kit limitado', 250, [[$teclado, 1], [$mouse, 1]], limite: 1);

        $this->sales->create($this->shift, [
            'payment_method' => 'cash', 'amount_paid' => 1000,
            'combos' => [['promotion_id' => $combo->id, 'quantity' => 1]],
        ], [$this->linea($teclado, 1), $this->linea($mouse, 1)]);

        $this->expectException(ValidationException::class);

        $this->sales->create($this->shift, [
            'payment_method' => 'cash', 'amount_paid' => 1000,
            'combos' => [['promotion_id' => $combo->id, 'quantity' => 1]],
        ], [$this->linea($teclado, 1), $this->linea($mouse, 1)]);
    }

    /**
     * La lista de combos llega del navegador: si no se contrastara con el carrito,
     * bastaría con declarar un combo que no se llevó para gastarle el cupo.
     */
    public function test_no_se_registra_un_combo_que_no_esta_en_el_carrito(): void
    {
        $teclado = $this->producto('Teclado', 200);
        $mouse   = $this->producto('Mouse', 100);

        $combo = $this->combo('Kit oficina', 250, [[$teclado, 1], [$mouse, 1]]);

        try {
            $this->sales->create($this->shift, [
                'payment_method' => 'cash', 'amount_paid' => 1000,
                'combos' => [['promotion_id' => $combo->id, 'quantity' => 1]],
            ], [$this->linea($teclado, 1)]); // falta el mouse

            $this->fail('Debería haber rechazado el combo.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('no contiene los productos', implode(' ', $e->errors()['combos']));
        }

        $this->assertSame(0, (int) $combo->fresh()->used_count);
    }

    public function test_no_se_puede_declarar_mas_veces_de_las_que_alcanza_el_carrito(): void
    {
        $teclado = $this->producto('Teclado', 200);
        $mouse   = $this->producto('Mouse', 100);

        $combo = $this->combo('Kit oficina', 250, [[$teclado, 1], [$mouse, 1]]);

        $this->expectException(ValidationException::class);

        // Dice llevar dos combos, pero solo hay una unidad de cada producto.
        $this->sales->create($this->shift, [
            'payment_method' => 'cash', 'amount_paid' => 1000,
            'combos' => [['promotion_id' => $combo->id, 'quantity' => 2]],
        ], [$this->linea($teclado, 1), $this->linea($mouse, 1)]);
    }

    /** Un combo no es un descuento sobre el carrito y el mensaje debe decirlo. */
    public function test_un_combo_no_se_puede_elegir_como_promocion_de_descuento(): void
    {
        $teclado = $this->producto('Teclado', 200);
        $combo   = $this->combo('Kit', 150, [[$teclado, 1]]);

        try {
            $this->vender([$this->linea($teclado, 1)], $combo->id);
            $this->fail('Debería haberlo rechazado.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('combos se agregan al carrito', implode(' ', $e->errors()['promotion_id']));
        }
    }

    // ── Regresión del fallo crítico ─────────────────────────────────────────

    /**
     * Las tablas pivote se llaman `promotion_product` y `promotion_category`,
     * pero la convención de Eloquent es alfabética. Cuando el nombre no se
     * declara, leer la relación revienta y con ella se caen el listado de
     * promociones **y el punto de venta**, que es lo grave: la caja deja de
     * abrirse en cuanto existe una promoción.
     */
    public function test_el_punto_de_venta_abre_con_promociones_cargadas(): void
    {
        $cat = Category::create(['name' => 'Periféricos', 'slug' => 'perifericos']);
        $p   = $this->producto('Mouse', 100, cat: $cat);

        $porProducto = Promotion::create([
            'name' => 'Por producto', 'type' => 'percentage', 'value' => 10,
            'scope' => 'product', 'is_active' => true,
        ]);
        $porProducto->products()->sync([$p->id]);

        $porCategoria = Promotion::create([
            'name' => 'Por categoría', 'type' => 'percentage', 'value' => 5,
            'scope' => 'category', 'is_active' => true,
        ]);
        $porCategoria->categories()->sync([$cat->id]);

        $this->get('/admin/sales/create')->assertOk();
        $this->get('/admin/promotions')->assertOk();
    }

    /** Crear una promoción por producto pasaba por la relación rota. */
    public function test_se_crea_una_promocion_con_alcance_por_producto(): void
    {
        $p = $this->producto('Teclado', 200);

        $this->post('/admin/promotions', [
            'name'        => 'Teclados al 15%',
            'type'        => 'percentage',
            'value'       => 15,
            'scope'       => 'product',
            'product_ids' => [$p->id],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $promo = Promotion::where('name', 'Teclados al 15%')->firstOrFail();

        $this->assertSame([$p->id], $promo->products()->pluck('products.id')->all());
    }

    /** Un 150% dejaría la venta en cero por un simple error de tecleo. */
    public function test_no_se_admite_un_porcentaje_mayor_a_cien(): void
    {
        $this->post('/admin/promotions', [
            'name'  => 'Error de dedo',
            'type'  => 'percentage',
            'value' => 150,
            'scope' => 'all',
        ])->assertSessionHasErrors('value');
    }
}
