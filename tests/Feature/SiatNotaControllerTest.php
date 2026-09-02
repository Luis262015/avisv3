<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CashRegister;
use App\Models\CashShift;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\SiatCufdCode;
use App\Models\SiatInvoice;
use App\Models\SiatNota;
use App\Models\SiatSetting;
use App\Models\Store;
use App\Models\User;
use App\Services\Siat\SiatDocumentoAjusteService;
use App\Services\Siat\SiatException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Las pantallas de la Nota de Crédito-Débito.
 *
 * La emisión cuelga de la devolución, que es lo que la origina; el resto del
 * ciclo va sobre la nota. Lo que importa comprobar es que un rechazo del SIN no
 * se pierda por el camino y que la devolución sepa si ya tiene nota.
 */
class SiatNotaControllerTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;
    private CashShift $shift;
    private Product $product;
    private SiatCufdCode $cufd;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin', 'web');

        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);

        $this->store = Store::create(['name' => 'Tienda Central', 'is_active' => true]);

        $register = CashRegister::create([
            'store_id' => $this->store->id, 'name' => 'Caja 1', 'is_active' => true,
        ]);

        $this->shift = CashShift::create([
            'cash_register_id' => $register->id, 'user_id' => $user->id,
            'opening_amount' => 0, 'opened_at' => now(), 'status' => 'open',
        ]);

        SiatSetting::create([
            'store_id' => $this->store->id, 'nit' => '1234567890',
            'codigo_sistema' => 'SISTEMA-DE-PRUEBA', 'razon_social' => 'EMPRESA DE PRUEBA SRL',
            'municipio' => 'LA PAZ', 'direccion' => 'AV. SIEMPRE VIVA 123',
            'actividad_economica' => '4741100', 'leyenda' => 'Ley N° 453: Leyenda de prueba.',
            'ambiente' => 'piloto', 'modalidad' => 2, 'cuis' => 'CUIS-DE-PRUEBA',
            'tipo_factura_default' => 1, 'is_active' => true,
        ]);

        $this->cufd = SiatCufdCode::create([
            'store_id' => $this->store->id, 'codigo' => 'CUFD-DE-PRUEBA',
            'codigo_control' => '23E26C80881BF74', 'fecha_vigencia' => now()->addDay(),
            'consecutivo' => 0, 'estado' => 'activo',
        ]);

        $this->product = Product::create([
            'name' => 'Laptop de prueba', 'slug' => 'laptop-de-prueba', 'sku' => 'LAP-1',
            'price' => 100, 'cost' => 70, 'stock' => 10, 'status' => 'active',
            'codigo_producto_sin' => 1001967, 'unidad_medida_sin' => 57,
        ]);
    }

    public function test_emite_la_nota_desde_la_devolucion(): void
    {
        $this->sinEnvio();
        $devolucion = $this->devolucion();

        $this->post("/admin/siat/returns/{$devolucion->id}/emit-nota")
            ->assertRedirect();

        $nota = SiatNota::firstOrFail();

        $this->assertSame($devolucion->id, $nota->sale_return_id);
        $this->assertSame('enviada', $nota->estado);
        $this->assertSame(SiatNota::SECTOR_NOTA, $nota->documento_sector);
    }

    /** La homologación pide emitir en los dos sectores, se dé o no el descuento. */
    public function test_se_puede_forzar_el_documento_sector(): void
    {
        $this->sinEnvio();
        $devolucion = $this->devolucion();

        $this->post("/admin/siat/returns/{$devolucion->id}/emit-nota", ['documento_sector' => 47])
            ->assertRedirect();

        $this->assertSame(SiatNota::SECTOR_NOTA_DESCUENTO, SiatNota::firstOrFail()->documento_sector);
    }

    public function test_rechaza_un_documento_sector_que_no_es_de_nota(): void
    {
        $this->sinEnvio();
        $devolucion = $this->devolucion();

        $this->post("/admin/siat/returns/{$devolucion->id}/emit-nota", ['documento_sector' => 1])
            ->assertSessionHasErrors('documento_sector');

        $this->assertSame(0, SiatNota::count());
    }

    /** Un rechazo tiene que llegar a la pantalla, no perderse en un redirect. */
    public function test_un_rechazo_del_sin_llega_a_la_pantalla(): void
    {
        $this->mock(SiatDocumentoAjusteService::class, function ($mock): void {
            $mock->shouldReceive('recepcionDocumentoAjuste')
                ->andThrow(new SiatException('El SIN rechazó la operación: 1000 ALGO'));
        });

        $devolucion = $this->devolucion();

        $this->post("/admin/siat/returns/{$devolucion->id}/emit-nota")
            ->assertSessionHasErrors('siat');

        $this->assertSame('rechazada', SiatNota::firstOrFail()->estado);
    }

    public function test_sin_factura_original_el_error_se_muestra_sin_crear_nada(): void
    {
        $devolucion = $this->devolucion(conFactura: false);

        $this->post("/admin/siat/returns/{$devolucion->id}/emit-nota")
            ->assertSessionHasErrors('siat');

        $this->assertSame(0, SiatNota::count());
    }

    public function test_la_devolucion_sabe_si_ya_tiene_nota(): void
    {
        $this->sinEnvio();
        $devolucion = $this->devolucion();

        $this->post("/admin/siat/returns/{$devolucion->id}/emit-nota");

        $this->get("/admin/returns/{$devolucion->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/returns/show')
                ->where('return.siat_nota.numero_nota', 1));
    }

    public function test_el_listado_y_el_detalle_responden(): void
    {
        $this->sinEnvio();
        $devolucion = $this->devolucion();

        $this->post("/admin/siat/returns/{$devolucion->id}/emit-nota");
        $nota = SiatNota::firstOrFail();

        $this->get('/admin/siat/notas')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('admin/siat/notas/index'));

        $this->get("/admin/siat/notas/{$nota->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/siat/notas/show')
                ->where('nota.estado_label', 'Enviada al SIN')
                ->where('nota.sector_label', 'NOTA DE CRÉDITO-DÉBITO'));
    }

    public function test_no_se_reenvia_una_nota_anulada(): void
    {
        $this->sinEnvio();
        $devolucion = $this->devolucion();
        $this->post("/admin/siat/returns/{$devolucion->id}/emit-nota");

        $nota = SiatNota::firstOrFail();
        $nota->update(['estado' => 'anulada', 'codigo_recepcion' => 'REC-1']);

        $this->post("/admin/siat/notas/{$nota->id}/resend")
            ->assertSessionHasErrors('siat');
    }

    private function sinEnvio(): void
    {
        $this->mock(SiatDocumentoAjusteService::class, function ($mock): void {
            $mock->shouldReceive('recepcionDocumentoAjuste')->andReturn([
                'codigoRecepcion' => 'REC-1', 'codigoEstado' => 908,
                'codigoDescripcion' => 'VALIDADA', 'mensajes' => [], 'respuesta' => [],
            ]);
        });
    }

    private function devolucion(bool $conFactura = true): SaleReturn
    {
        $sale = Sale::create([
            'cash_shift_id' => $this->shift->id, 'user_id' => $this->shift->user_id,
            'folio' => 'V-' . uniqid(), 'subtotal' => 100, 'total' => 100,
            'amount_paid' => 100, 'payment_method' => 'cash', 'status' => 'completed',
        ]);

        $item = SaleItem::create([
            'sale_id' => $sale->id, 'product_id' => $this->product->id,
            'quantity' => 1, 'price' => 100, 'discount' => 0, 'subtotal' => 100,
        ]);

        if ($conFactura) {
            SiatInvoice::create([
                'sale_id' => $sale->id, 'store_id' => $this->store->id,
                'cufd_code_id' => $this->cufd->id, 'numero_factura' => $sale->id,
                'fecha_emision' => now()->subHour(), 'cuf' => 'CUF-' . $sale->id,
                'cufd' => $this->cufd->codigo, 'nit_ci' => '9876543',
                'tipo_doc_identidad' => 1, 'nombre_razon_social' => 'CLIENTE DE PRUEBA',
                'importe_total' => 100, 'importe_base_cf' => 100, 'descuento' => 0,
                'tipo_factura' => 1, 'tipo_emision' => 1, 'metodo_pago' => 1,
                'estado' => 'validada',
            ]);
        }

        $devolucion = SaleReturn::create([
            'sale_id' => $sale->id, 'user_id' => $this->shift->user_id,
            'folio' => 'DEV-' . uniqid(), 'date' => now(), 'reason' => 'Producto defectuoso',
            'refund_method' => 'cash', 'refund_amount' => 100,
            'status' => 'completed', 'restock' => false,
        ]);

        SaleReturnItem::create([
            'sale_return_id' => $devolucion->id, 'sale_item_id' => $item->id,
            'product_id' => $this->product->id, 'quantity' => 1,
            'unit_price' => 100, 'subtotal' => 100,
        ]);

        return $devolucion;
    }
}
