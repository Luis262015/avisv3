<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CashRegister;
use App\Models\CashShift;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SiatCufdCode;
use App\Models\SiatInvoice;
use App\Models\Store;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * El panel operativo: qué cuenta como "hoy", qué ve cada rol y qué se considera
 * digno de atención.
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;
    private CashRegister $register;

    protected function setUp(): void
    {
        parent::setUp();

        config(['siat.timezone' => 'America/La_Paz']);

        $this->store = Store::create(['name' => 'Tienda Central', 'is_active' => true]);
        $this->register = CashRegister::create([
            'store_id' => $this->store->id, 'name' => 'Caja 1', 'is_active' => true,
        ]);
    }

    // ── Ayudas ──────────────────────────────────────────────────────────────

    private function admin(): User
    {
        foreach (['sales.view', 'cash-shifts.view', 'inventory.view', 'purchases.view'] as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }

        $rol = Role::findOrCreate('admin', 'web');
        $rol->syncPermissions(['sales.view', 'cash-shifts.view', 'inventory.view', 'purchases.view']);

        $user = User::factory()->create();
        $user->assignRole($rol);

        return $user;
    }

    private function vendedor(): User
    {
        Permission::findOrCreate('sales.view', 'web');
        Permission::findOrCreate('cash-shifts.view', 'web');

        $rol = Role::findOrCreate('vendedor', 'web');
        $rol->syncPermissions(['sales.view', 'cash-shifts.view']);

        $user = User::factory()->create();
        $user->assignRole($rol);

        return $user;
    }

    private function turno(User $user, ?CashRegister $register = null, string $status = 'open'): CashShift
    {
        return CashShift::create([
            'cash_register_id' => ($register ?? $this->register)->id,
            'user_id'          => $user->id,
            'opening_amount'   => 0,
            'opened_at'        => now(),
            'status'           => $status,
        ]);
    }

    private function venta(CashShift $turno, User $user, float $total, ?Carbon $cuando = null): Sale
    {
        $venta = Sale::create([
            'cash_shift_id' => $turno->id,
            'user_id'       => $user->id,
            'folio'         => 'V-' . fake()->unique()->numerify('#####'),
            'subtotal'      => $total,
            'total'         => $total,
            'amount_paid'   => $total,
            'payment_method' => 'cash',
            'status'        => 'completed',
        ]);

        if ($cuando !== null) {
            // `created_at` la pone Eloquent; para situar la venta en el pasado hay
            // que reescribirla sin tocar los timestamps.
            $venta->newQuery()->whereKey($venta->id)->update(['created_at' => $cuando]);
            $venta->refresh();
        }

        return $venta;
    }

    // ── El día es el de Bolivia, no el de UTC ───────────────────────────────

    /**
     * El caso que rompía: la aplicación corre en UTC y el negocio en UTC-4, así
     * que entre las 20:00 y la medianoche de Bolivia el "hoy" de UTC ya es el día
     * siguiente. Una venta de la mañana quedaría fuera del panel justo cuando el
     * encargado hace el cierre.
     */
    public function test_las_ventas_de_hoy_se_cuentan_en_hora_de_bolivia(): void
    {
        // 02:00 UTC = 22:00 del día anterior en Bolivia.
        Carbon::setTestNow(Carbon::parse('2026-08-14 02:00:00', 'UTC'));

        $user  = $this->admin();
        $turno = $this->turno($user);

        // Bolivia 06:00 del 13 — es hoy, aunque en UTC el día ya sea el 14.
        $this->venta($turno, $user, 100, Carbon::parse('2026-08-13 10:00:00', 'UTC'));
        // Bolivia 22:30 del 13 — también hoy.
        $this->venta($turno, $user, 50, Carbon::parse('2026-08-14 02:30:00', 'UTC'));
        // Bolivia 23:00 del 12 — ayer, no debe contar.
        $this->venta($turno, $user, 999, Carbon::parse('2026-08-13 03:00:00', 'UTC'));

        $ventas = $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->viewData('page')['props']['ventas'];

        $this->assertSame(150.0, $ventas['hoy_total']);
        $this->assertSame(2, $ventas['hoy_cantidad']);
        $this->assertSame(75.0, $ventas['ticket_promedio']);

        Carbon::setTestNow();
    }

    public function test_la_serie_cubre_catorce_dias_e_incluye_los_vacios(): void
    {
        $user = $this->admin();

        $ventas = $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->viewData('page')['props']['ventas'];

        $this->assertCount(14, $ventas['serie']);
        $this->assertSame(0.0, $ventas['serie'][0]['total']);
    }

    /** Sin ventas ayer no hay porcentaje: dividir entre cero no es un 100%. */
    public function test_sin_ventas_ayer_no_se_calcula_variacion(): void
    {
        $user  = $this->admin();
        $turno = $this->turno($user);
        $this->venta($turno, $user, 100);

        $ventas = $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->viewData('page')['props']['ventas'];

        $this->assertNull($ventas['variacion']);
    }

    // ── Permisos ────────────────────────────────────────────────────────────

    /**
     * Lo que no se puede ver llega como null y no como cero: un cero se leería
     * como "no hay nada pendiente", que es una respuesta distinta.
     */
    public function test_un_vendedor_no_recibe_los_bloques_que_no_le_corresponden(): void
    {
        $props = $this->actingAs($this->vendedor())->get('/dashboard')
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertNotNull($props['ventas']);
        $this->assertNotNull($props['caja']);
        $this->assertNull($props['inventario']);
        $this->assertNull($props['compras']);
        $this->assertNull($props['finanzas']);
        $this->assertNull($props['siat']);

        $this->assertFalse($props['puede']['siat']);
        $this->assertTrue($props['puede']['ventas']);
    }

    public function test_el_administrador_recibe_todos_los_bloques(): void
    {
        $props = $this->actingAs($this->admin())->get('/dashboard')
            ->assertOk()
            ->viewData('page')['props'];

        foreach (['ventas', 'caja', 'inventario', 'compras', 'finanzas', 'siat'] as $bloque) {
            $this->assertNotNull($props[$bloque], "Falta el bloque {$bloque}");
        }
    }

    public function test_el_panel_exige_autenticacion(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    // ── Filtro por tienda ───────────────────────────────────────────────────

    /**
     * `sales` no guarda la tienda: se llega por el turno y la caja. Si ese camino
     * se rompe el filtro devuelve todo y nadie lo nota.
     */
    public function test_el_filtro_de_tienda_acota_las_ventas(): void
    {
        $user = $this->admin();

        $otra = Store::create(['name' => 'Sucursal Norte', 'is_active' => true]);
        $cajaOtra = CashRegister::create([
            'store_id' => $otra->id, 'name' => 'Caja Norte', 'is_active' => true,
        ]);

        $this->venta($this->turno($user), $user, 100);
        $this->venta($this->turno($user, $cajaOtra), $user, 700);

        $todas = $this->actingAs($user)->get('/dashboard')
            ->viewData('page')['props']['ventas'];
        $this->assertSame(800.0, $todas['hoy_total']);

        $central = $this->actingAs($user)->get("/dashboard?store_id={$this->store->id}")
            ->viewData('page')['props']['ventas'];
        $this->assertSame(100.0, $central['hoy_total']);
    }

    public function test_una_tienda_inexistente_es_rechazada(): void
    {
        $this->actingAs($this->admin())
            ->get('/dashboard?store_id=99999')
            ->assertSessionHasErrors('store_id');
    }

    // ── Caja ────────────────────────────────────────────────────────────────

    public function test_muestra_el_turno_abierto_con_lo_vendido(): void
    {
        $user  = $this->admin();
        $turno = $this->turno($user);
        $this->venta($turno, $user, 250);

        $caja = $this->actingAs($user)->get('/dashboard')
            ->viewData('page')['props']['caja'];

        $this->assertTrue($caja['abierto']);
        $this->assertSame('Caja 1', $caja['turno']['caja']);
        $this->assertSame('Tienda Central', $caja['turno']['tienda']);
        $this->assertSame(250.0, $caja['turno']['vendido']);
    }

    public function test_avisa_cuando_no_hay_ningun_turno_abierto(): void
    {
        $user = $this->admin();
        $this->turno($user, null, 'closed');

        $caja = $this->actingAs($user)->get('/dashboard')
            ->viewData('page')['props']['caja'];

        $this->assertFalse($caja['abierto']);
        $this->assertNull($caja['turno']);
    }

    // ── SIAT ────────────────────────────────────────────────────────────────

    /**
     * «Rechazada» no es un estado de la tabla: la factura que el SIN no aceptó se
     * queda en `pendiente` con el motivo en `mensaje_error`. Contarlas juntas
     * escondería justo las que urgen.
     */
    public function test_distingue_las_facturas_rechazadas_de_las_pendientes(): void
    {
        $user  = $this->admin();
        $turno = $this->turno($user);

        $cufd = SiatCufdCode::create([
            'store_id' => $this->store->id, 'codigo' => 'CUFD-DE-PRUEBA',
            'codigo_control' => '23E26C80881BF74', 'fecha_vigencia' => now()->addDay(),
            'consecutivo' => 1, 'estado' => 'activo',
        ]);

        $crear = function (int $numero, string $estado, ?string $error) use ($turno, $user, $cufd): SiatInvoice {
            return SiatInvoice::create([
                'sale_id'         => $this->venta($turno, $user, 100)->id,
                'store_id'        => $this->store->id,
                'cufd_code_id'    => $cufd->id,
                'numero_factura'  => $numero,
                'fecha_emision'   => now(),
                'cuf'             => 'CUF-DE-PRUEBA-' . $numero,
                'cufd'            => $cufd->codigo,
                'importe_total'   => 100,
                'importe_base_cf' => 100,
                'tipo_factura'    => 2,
                'estado'          => $estado,
                'mensaje_error'   => $error,
            ]);
        };

        $crear(1, 'pendiente', '1012 EL NUMERO DE TARJETA SOLO PUEDE SER ENVIADO...');
        $crear(2, 'pendiente', null);
        $crear(3, 'contingencia', null);

        $siat = $this->actingAs($user)->get('/dashboard')
            ->viewData('page')['props']['siat'];

        $this->assertSame(1, $siat['rechazadas']);
        $this->assertSame(1, $siat['pendientes']);
        $this->assertSame(1, $siat['en_contingencia']);
        $this->assertCount(1, $siat['ultimas_rechazadas']);
        $this->assertStringContainsString('1012', $siat['ultimas_rechazadas'][0]['error']);
    }

    // ── Inventario ──────────────────────────────────────────────────────────

    /**
     * El stock bajo se cuenta **por tienda**, no contra el total de la empresa:
     * las existencias viven en `store_product_stocks` y el total del producto es
     * solo su suma.
     */
    public function test_cuenta_las_existencias_bajo_el_minimo_y_las_agotadas(): void
    {
        // Autenticado antes de cargar existencias: el movimiento registra quién lo hizo.
        $user = $this->admin();
        $this->actingAs($user);

        $producto = function (string $nombre, int $stock, int $min) {
            $p = Product::create([
                'name' => $nombre, 'sku' => strtoupper($nombre), 'price' => 10, 'cost' => 5,
                'stock' => 0, 'min_stock' => $min, 'unit' => 'pza',
                'status' => 'active', 'track_inventory' => true,
            ]);

            app(InventoryService::class)->adjust($p, $stock, 'Carga de prueba', $this->store->id);

            return $p;
        };

        $producto('agotado', 0, 5);
        $producto('bajo', 3, 5);
        $producto('suficiente', 50, 5);
        // Sin mínimo definido no hay nada que comparar: no es una alerta.
        $producto('sin-minimo', 0, 0);

        $inventario = $this->actingAs($user)->get('/dashboard')
            ->viewData('page')['props']['inventario'];

        $this->assertSame(2, $inventario['bajo']);
        $this->assertSame(1, $inventario['agotados']);
    }

    /**
     * Un producto sano en el conjunto puede estar vacío en una sucursal. El total
     * lo daba por bueno; contarlo por tienda es justamente lo que lo destapa.
     */
    public function test_una_tienda_vacia_se_detecta_aunque_el_total_alcance(): void
    {
        $user = $this->admin();
        $this->actingAs($user);

        $otra = Store::create(['name' => 'Sucursal Norte', 'is_active' => true]);

        $p = Product::create([
            'name' => 'Monitor', 'sku' => 'MON-1', 'price' => 900, 'cost' => 600,
            'stock' => 0, 'min_stock' => 5, 'unit' => 'pza',
            'status' => 'active', 'track_inventory' => true,
        ]);

        // 40 en la matriz, 1 en la sucursal: el total (41) supera de sobra el mínimo.
        app(InventoryService::class)->adjust($p, 40, 'Carga', $this->store->id);
        app(InventoryService::class)->adjust($p, 1, 'Carga', $otra->id);

        $this->assertSame(41, (int) $p->refresh()->stock);

        $inventario = $this->actingAs($user)->get('/dashboard')
            ->viewData('page')['props']['inventario'];

        $this->assertSame(1, $inventario['bajo']);
        $this->assertSame('Sucursal Norte', $inventario['productos'][0]['tienda']);
    }
}
