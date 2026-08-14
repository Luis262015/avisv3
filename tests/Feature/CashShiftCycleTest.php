<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CashRegister;
use App\Models\CashShift;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * El ciclo de caja de punta a punta: abrir, mover dinero y cuadrar al cerrar,
 * con varias tiendas y varios responsables a la vez.
 *
 * Lo que se protege aquí es la exclusividad —un turno por persona y uno por
 * caja— y la aritmética del arqueo, que es lo que decide si a alguien le falta
 * dinero al final del día.
 */
class CashShiftCycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin', 'web');
    }

    // ── Ayudas ──────────────────────────────────────────────────────────────

    private function usuario(string $nombre): User
    {
        $user = User::factory()->create(['name' => $nombre]);
        $user->assignRole('admin');

        return $user;
    }

    private function tienda(string $nombre, int $cajas = 1): Store
    {
        $store = Store::create(['name' => $nombre, 'is_active' => true]);

        for ($i = 1; $i <= $cajas; $i++) {
            CashRegister::create(['store_id' => $store->id, 'name' => "Caja {$i}", 'is_active' => true]);
        }

        return $store;
    }

    private function caja(Store $store, string $nombre): CashRegister
    {
        return $store->cashRegisters()->where('name', $nombre)->firstOrFail();
    }

    private function abrir(User $user, CashRegister $caja, float $apertura)
    {
        return $this->actingAs($user)->post('/admin/cash-shifts', [
            'cash_register_id' => $caja->id,
            'opening_amount'   => $apertura,
        ]);
    }

    private function cerrar(User $user, CashShift $turno, float $contado, string $notas = '')
    {
        return $this->actingAs($user)->patch("/admin/cash-shifts/{$turno->id}/close", [
            'closing_amount' => $contado,
            'notes'          => $notas,
        ]);
    }

    private function venta(CashShift $turno, User $user, float $total, string $metodo = 'cash'): Sale
    {
        return Sale::create([
            'cash_shift_id'  => $turno->id,
            'user_id'        => $user->id,
            'folio'          => 'V-' . fake()->unique()->numerify('######'),
            'subtotal'       => $total,
            'total'          => $total,
            'amount_paid'    => $total,
            'payment_method' => $metodo,
            'status'         => 'completed',
        ]);
    }

    // ── Apertura ────────────────────────────────────────────────────────────

    public function test_se_abre_un_turno_con_su_monto_inicial(): void
    {
        $user   = $this->usuario('Carla');
        $tienda = $this->tienda('Sucursal Sur');

        $this->abrir($user, $this->caja($tienda, 'Caja 1'), 500)
            ->assertRedirect();

        $turno = CashShift::firstOrFail();

        $this->assertSame('open', $turno->status);
        $this->assertSame($user->id, $turno->user_id);
        $this->assertSame('500.00', $turno->opening_amount);
        $this->assertNotNull($turno->opened_at);
    }

    /** Dos turnos abiertos por la misma persona harían imposible saber a cuál va cada venta. */
    public function test_una_persona_no_puede_tener_dos_turnos_abiertos(): void
    {
        $user   = $this->usuario('Carla');
        $tienda = $this->tienda('Sucursal Sur', cajas: 2);

        $this->abrir($user, $this->caja($tienda, 'Caja 1'), 500);
        $this->abrir($user, $this->caja($tienda, 'Caja 2'), 300)
            ->assertSessionHasErrors('cash_register_id');

        $this->assertSame(1, CashShift::where('status', 'open')->count());
    }

    /** Una caja con dos turnos abiertos tendría dos arqueos sobre el mismo cajón. */
    public function test_una_caja_no_admite_dos_turnos_abiertos(): void
    {
        $tienda = $this->tienda('Sucursal Sur');
        $caja   = $this->caja($tienda, 'Caja 1');

        $this->abrir($this->usuario('Carla'), $caja, 500);
        $this->abrir($this->usuario('Rodrigo'), $caja, 300)
            ->assertSessionHasErrors('cash_register_id');

        $this->assertSame(1, CashShift::where('status', 'open')->count());
    }

    public function test_dos_cajeros_pueden_operar_a_la_vez_en_la_misma_tienda(): void
    {
        $tienda = $this->tienda('Sucursal Sur', cajas: 2);

        $this->abrir($this->usuario('Carla'), $this->caja($tienda, 'Caja 1'), 500)->assertRedirect();
        $this->abrir($this->usuario('Rodrigo'), $this->caja($tienda, 'Caja 2'), 300)->assertRedirect();

        $this->assertSame(2, CashShift::where('status', 'open')->count());
    }

    public function test_los_turnos_de_tiendas_distintas_son_independientes(): void
    {
        $sur   = $this->tienda('Sucursal Sur');
        $norte = $this->tienda('Sucursal Norte');

        $this->abrir($this->usuario('Carla'), $this->caja($sur, 'Caja 1'), 500)->assertRedirect();
        $this->abrir($this->usuario('Ximena'), $this->caja($norte, 'Caja 1'), 700)->assertRedirect();

        $this->assertSame(2, CashShift::where('status', 'open')->count());
        $this->assertSame(
            [500.0, 700.0],
            CashShift::orderBy('id')->pluck('opening_amount')->map(fn ($m) => (float) $m)->all()
        );
    }

    // ── Cierre y arqueo ─────────────────────────────────────────────────────

    /**
     * El arqueo es apertura + ventas + ingresos - gastos - retiros. Si esta cuenta
     * se desvía, a alguien le van a descontar un faltante que no existe.
     */
    public function test_el_cierre_calcula_el_esperado_con_todos_los_movimientos(): void
    {
        $user   = $this->usuario('Carla');
        $tienda = $this->tienda('Sucursal Sur');

        $this->abrir($user, $this->caja($tienda, 'Caja 1'), 500);
        $turno = CashShift::firstOrFail();

        $this->venta($turno, $user, 300);
        $this->venta($turno, $user, 150);

        Income::create([
            'cash_shift_id' => $turno->id, 'store_id' => $tienda->id, 'user_id' => $user->id,
            'category' => 'otros', 'description' => 'Reposición de fondo', 'amount' => 100,
            'payment_method' => 'cash', 'date' => now()->toDateString(),
        ]);

        Expense::create([
            'cash_shift_id' => $turno->id, 'store_id' => $tienda->id, 'user_id' => $user->id,
            'category' => 'servicios', 'description' => 'Movilidad', 'amount' => 80,
            'payment_method' => 'cash', 'date' => now()->toDateString(),
        ]);

        Withdrawal::create([
            'cash_shift_id' => $turno->id, 'user_id' => $user->id,
            'amount' => 200, 'reason' => 'Depósito bancario', 'date' => now()->toDateString(),
        ]);

        // 500 + 450 + 100 - 80 - 200 = 770
        $this->cerrar($user, $turno, 770)->assertRedirect();

        $turno->refresh();

        $this->assertSame('closed', $turno->status);
        $this->assertSame('770.00', $turno->expected_amount);
        $this->assertSame('0.00', $turno->difference);
        $this->assertNotNull($turno->closed_at);
    }

    /**
     * El fallo que motivó todo esto: sumar las ventas con tarjeta al esperado le
     * imputaba al cajero un faltante por dinero que nunca pasó por el cajón.
     */
    public function test_las_ventas_con_tarjeta_no_cuentan_como_efectivo(): void
    {
        $user   = $this->usuario('Marco');
        $tienda = $this->tienda('Sucursal Norte');

        $this->abrir($user, $this->caja($tienda, 'Caja 1'), 1000);
        $turno = CashShift::firstOrFail();

        $this->venta($turno, $user, 400, 'cash');
        $this->venta($turno, $user, 1200, 'card');
        $this->venta($turno, $user, 250, 'transfer');

        // En el cajón solo están los 1000 de apertura y los 400 en efectivo.
        $this->cerrar($user, $turno, 1400);

        $turno->refresh();
        $this->assertSame('1400.00', $turno->expected_amount);
        $this->assertSame('0.00', $turno->difference);
    }

    /** Un gasto pagado con tarjeta no sale del cajón y no puede descontarse de él. */
    public function test_los_movimientos_que_no_son_en_efectivo_no_alteran_el_arqueo(): void
    {
        $user   = $this->usuario('Carla');
        $tienda = $this->tienda('Sucursal Sur');

        $this->abrir($user, $this->caja($tienda, 'Caja 1'), 500);
        $turno = CashShift::firstOrFail();

        foreach (['card', 'transfer'] as $metodo) {
            Expense::create([
                'cash_shift_id' => $turno->id, 'store_id' => $tienda->id, 'user_id' => $user->id,
                'category' => 'servicios', 'description' => "Gasto {$metodo}", 'amount' => 100,
                'payment_method' => $metodo, 'date' => now()->toDateString(),
            ]);

            Income::create([
                'cash_shift_id' => $turno->id, 'store_id' => $tienda->id, 'user_id' => $user->id,
                'category' => 'otros', 'description' => "Ingreso {$metodo}", 'amount' => 70,
                'payment_method' => $metodo, 'date' => now()->toDateString(),
            ]);
        }

        $this->cerrar($user, $turno, 500);

        $turno->refresh();
        $this->assertSame('500.00', $turno->expected_amount);
        $this->assertSame('0.00', $turno->difference);
    }

    /**
     * Una venta mixta no guarda cuánto se cobró en efectivo, así que no puede
     * entrar en el esperado automático; se informa aparte para resolverla a mano.
     */
    public function test_las_ventas_mixtas_quedan_fuera_del_esperado_y_se_informan(): void
    {
        $user   = $this->usuario('Ximena');
        $tienda = $this->tienda('Sucursal Sur');

        $this->abrir($user, $this->caja($tienda, 'Caja 1'), 300);
        $turno = CashShift::firstOrFail();

        $this->venta($turno, $user, 200, 'cash');
        $this->venta($turno, $user, 500, 'mixed');

        $arqueo = $turno->arqueo();

        $this->assertSame(500.0, $arqueo['ventas_mixtas']);
        $this->assertSame(500.0, $arqueo['esperado'], '300 de apertura + 200 en efectivo.');
    }

    /** El desglose tiene que cuadrar con el esperado, o nadie puede auditarlo. */
    public function test_el_desglose_del_arqueo_explica_el_esperado(): void
    {
        $user   = $this->usuario('Carla');
        $tienda = $this->tienda('Sucursal Sur');

        $this->abrir($user, $this->caja($tienda, 'Caja 1'), 500);
        $turno = CashShift::firstOrFail();

        $this->venta($turno, $user, 300, 'cash');
        $this->venta($turno, $user, 900, 'card');

        Income::create([
            'cash_shift_id' => $turno->id, 'store_id' => $tienda->id, 'user_id' => $user->id,
            'category' => 'otros', 'description' => 'Fondo', 'amount' => 100,
            'payment_method' => 'cash', 'date' => now()->toDateString(),
        ]);

        Withdrawal::create([
            'cash_shift_id' => $turno->id, 'user_id' => $user->id,
            'amount' => 150, 'reason' => 'Depósito', 'date' => now()->toDateString(),
        ]);

        $a = $turno->arqueo();

        $this->assertSame(300.0, $a['ventas_efectivo']);
        $this->assertSame(900.0, $a['ventas_otros']);
        $this->assertSame(100.0, $a['ingresos_efectivo']);
        $this->assertSame(150.0, $a['retiros']);

        $this->assertSame(
            round(500 + $a['ventas_efectivo'] + $a['ingresos_efectivo'] - $a['gastos_efectivo'] - $a['retiros'], 2),
            $a['esperado'],
        );
    }

    public function test_un_faltante_queda_registrado_como_diferencia_negativa(): void
    {
        $user   = $this->usuario('Carla');
        $tienda = $this->tienda('Sucursal Sur');

        $this->abrir($user, $this->caja($tienda, 'Caja 1'), 500);
        $turno = CashShift::firstOrFail();
        $this->venta($turno, $user, 200);

        // Esperado 700, en el cajón hay 680.
        $this->cerrar($user, $turno, 680);

        $turno->refresh();
        $this->assertSame('700.00', $turno->expected_amount);
        $this->assertSame('-20.00', $turno->difference);
    }

    public function test_un_sobrante_queda_registrado_como_diferencia_positiva(): void
    {
        $user   = $this->usuario('Carla');
        $tienda = $this->tienda('Sucursal Sur');

        $this->abrir($user, $this->caja($tienda, 'Caja 1'), 500);
        $turno = CashShift::firstOrFail();
        $this->venta($turno, $user, 200);

        $this->cerrar($user, $turno, 715);

        $turno->refresh();
        $this->assertSame('15.00', $turno->difference);
    }

    /** Una venta anulada no entró al cajón y no puede exigirse en el arqueo. */
    public function test_las_ventas_anuladas_no_cuentan_en_el_arqueo(): void
    {
        $user   = $this->usuario('Carla');
        $tienda = $this->tienda('Sucursal Sur');

        $this->abrir($user, $this->caja($tienda, 'Caja 1'), 500);
        $turno = CashShift::firstOrFail();

        $this->venta($turno, $user, 200);
        $anulada = $this->venta($turno, $user, 900);
        $anulada->update(['status' => 'cancelled']);

        $this->cerrar($user, $turno, 700);

        $turno->refresh();
        $this->assertSame('700.00', $turno->expected_amount);
        $this->assertSame('0.00', $turno->difference);
    }

    /** Recerrar recalcularía el arqueo y borraría el que ya se firmó. */
    public function test_un_turno_cerrado_no_puede_volver_a_cerrarse(): void
    {
        $user   = $this->usuario('Carla');
        $tienda = $this->tienda('Sucursal Sur');

        $this->abrir($user, $this->caja($tienda, 'Caja 1'), 500);
        $turno = CashShift::firstOrFail();

        $this->cerrar($user, $turno, 500)->assertRedirect();
        $this->cerrar($user, $turno, 9999)->assertSessionHasErrors('shift');

        $turno->refresh();
        $this->assertSame('500.00', $turno->closing_amount);
    }

    public function test_cerrar_libera_la_caja_y_a_la_persona_para_un_turno_nuevo(): void
    {
        $user   = $this->usuario('Carla');
        $tienda = $this->tienda('Sucursal Sur');
        $caja   = $this->caja($tienda, 'Caja 1');

        $this->abrir($user, $caja, 500);
        $this->cerrar($user, CashShift::firstOrFail(), 500);

        $this->abrir($user, $caja, 250)->assertRedirect();

        $this->assertSame(2, CashShift::count());
        $this->assertSame(1, CashShift::where('status', 'open')->count());
    }

    public function test_el_monto_contado_es_obligatorio_y_no_puede_ser_negativo(): void
    {
        $user   = $this->usuario('Carla');
        $tienda = $this->tienda('Sucursal Sur');

        $this->abrir($user, $this->caja($tienda, 'Caja 1'), 500);
        $turno = CashShift::firstOrFail();

        $this->cerrar($user, $turno, -50)->assertSessionHasErrors('closing_amount');

        $this->actingAs($user)
            ->patch("/admin/cash-shifts/{$turno->id}/close", [])
            ->assertSessionHasErrors('closing_amount');

        $this->assertTrue($turno->refresh()->isOpen());
    }

    /**
     * Cada turno responde solo por sus propios movimientos: si los de un turno se
     * colaran en otro, el arqueo del segundo saldría descuadrado sin motivo.
     */
    public function test_el_arqueo_no_mezcla_movimientos_de_otros_turnos(): void
    {
        $tienda = $this->tienda('Sucursal Sur', cajas: 2);

        $carla   = $this->usuario('Carla');
        $rodrigo = $this->usuario('Rodrigo');

        $this->abrir($carla, $this->caja($tienda, 'Caja 1'), 500);
        $this->abrir($rodrigo, $this->caja($tienda, 'Caja 2'), 400);

        $turnoCarla   = CashShift::where('user_id', $carla->id)->firstOrFail();
        $turnoRodrigo = CashShift::where('user_id', $rodrigo->id)->firstOrFail();

        $this->venta($turnoCarla, $carla, 300);
        $this->venta($turnoRodrigo, $rodrigo, 1000);

        $this->cerrar($carla, $turnoCarla, 800);
        $this->cerrar($rodrigo, $turnoRodrigo, 1400);

        $this->assertSame('800.00', $turnoCarla->refresh()->expected_amount);
        $this->assertSame('1400.00', $turnoRodrigo->refresh()->expected_amount);
        $this->assertSame('0.00', $turnoCarla->difference);
        $this->assertSame('0.00', $turnoRodrigo->difference);
    }
}
