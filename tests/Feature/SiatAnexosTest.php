<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CashRegister;
use App\Models\CashShift;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SiatAnexo;
use App\Models\SiatCufdCode;
use App\Models\SiatInvoice;
use App\Models\SiatSetting;
use App\Models\Store;
use App\Models\User;
use App\Services\Siat\SiatException;
use App\Services\Siat\SiatFacturacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Anexos de factura: los números de serie e IMEI que el SIN pide aparte, por
 * `recepcionAnexos`, citando el CUF de una factura ya recibida.
 *
 * Lo que se protege aquí es sobre todo que no salga una lista incompleta: el
 * envío es único e irrepetible, y una vez declarada la factura no admite
 * añadidos.
 */
class SiatAnexosTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;
    private CashShift $shift;
    private Product $laptop;
    private Product $cable;

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

        // Lleva número de serie; es lo que obliga al anexo.
        $this->laptop = Product::create([
            'name' => 'Portátil 14"', 'slug' => 'portatil-14', 'sku' => 'LAP-1',
            'price' => 100, 'cost' => 70, 'stock' => 10, 'status' => 'active',
            'codigo_producto_sin' => 1001967, 'unidad_medida_sin' => 57,
            'tipo_codigo_anexo'   => SiatAnexo::TIPO_SERIE,
        ]);

        // No lleva: la inmensa mayoría del catálogo es así.
        $this->cable = Product::create([
            'name' => 'Cable HDMI', 'slug' => 'cable-hdmi', 'sku' => 'CAB-1',
            'price' => 20, 'cost' => 10, 'stock' => 50, 'status' => 'active',
            'codigo_producto_sin' => 1001967, 'unidad_medida_sin' => 57,
        ]);
    }

    private function setting(string $ambiente = 'piloto'): SiatSetting
    {
        return SiatSetting::create([
            'store_id'            => $this->store->id,
            'nit'                 => '1234567890',
            'razon_social'        => 'EMPRESA DE PRUEBA SRL',
            'municipio'           => 'LA PAZ',
            'actividad_economica' => '4741100',
            'ambiente'            => $ambiente,
            'cuis'                => 'CUIS-DE-PRUEBA',
            'is_active'           => true,
        ]);
    }

    /**
     * @param  array<int, array{producto: Product, cantidad: int}>  $lineas
     */
    private function factura(array $lineas, array $atributos = []): SiatInvoice
    {
        $sale = Sale::create([
            'cash_shift_id' => $this->shift->id, 'user_id' => $this->shift->user_id,
            'folio' => 'V-' . uniqid(), 'subtotal' => 100, 'total' => 100,
            'amount_paid' => 100, 'payment_method' => 'cash', 'status' => 'completed',
        ]);

        foreach ($lineas as $linea) {
            SaleItem::create([
                'sale_id'    => $sale->id,
                'product_id' => $linea['producto']->id,
                'quantity'   => $linea['cantidad'],
                'price'      => $linea['producto']->price,
                'discount'   => 0,
                'subtotal'   => $linea['cantidad'] * (float) $linea['producto']->price,
            ]);
        }

        $cufd = SiatCufdCode::create([
            'store_id'       => $this->store->id,
            'codigo'         => 'CUFD-DE-PRUEBA',
            'codigo_control' => '23E26C80881BF74',
            'fecha_vigencia' => now()->addDay(),
            'consecutivo'    => 1,
            'estado'         => 'activo',
        ]);

        return SiatInvoice::create(array_merge([
            'sale_id'         => $sale->id,
            'store_id'        => $this->store->id,
            'cufd_code_id'    => $cufd->id,
            'numero_factura'  => $cufd->id,
            'fecha_emision'   => now(),
            // El CUF es único por factura: dos con el mismo chocan con el índice.
            'cuf'             => '1D9B8B69B433E44A906A66F0556EC4162' . str_pad((string) $cufd->id, 25, '0', STR_PAD_LEFT),
            'cufd'            => $cufd->codigo,
            'importe_total'   => 100,
            'importe_base_cf' => 100,
            'tipo_factura'    => 1,
            'estado'          => 'enviada',
            'anexos_estado'   => 'pendiente',
        ], $atributos));
    }

    // ─── Cuántos códigos hacen falta ─────────────────────────────────────────

    /** Uno por unidad física, no uno por línea. */
    public function test_cada_unidad_vendida_necesita_su_propio_codigo(): void
    {
        $invoice = $this->factura([
            ['producto' => $this->laptop, 'cantidad' => 3],
            ['producto' => $this->cable,  'cantidad' => 5],
        ]);

        $this->assertSame(3, $invoice->anexosRequeridos());
    }

    public function test_una_factura_sin_productos_con_serie_no_requiere_anexos(): void
    {
        $invoice = $this->factura([['producto' => $this->cable, 'cantidad' => 2]]);

        $this->assertSame(0, $invoice->anexosRequeridos());
    }

    // ─── Guardado ────────────────────────────────────────────────────────────

    public function test_guarda_los_codigos_y_toma_el_tipo_del_catalogo(): void
    {
        $invoice = $this->factura([['producto' => $this->laptop, 'cantidad' => 2]]);
        $item    = $invoice->sale->items->first();

        $this->put("/admin/siat/invoices/{$invoice->id}/anexos", [
            'anexos' => [
                ['sale_item_id' => $item->id, 'codigo' => 'SN-0001'],
                ['sale_item_id' => $item->id, 'codigo' => 'SN-0002'],
            ],
        ])->assertSessionHasNoErrors();

        $anexos = $invoice->fresh()->anexos;

        $this->assertCount(2, $anexos);
        // El tipo no lo manda el formulario: sale del producto.
        $this->assertSame([SiatAnexo::TIPO_SERIE, SiatAnexo::TIPO_SERIE], $anexos->pluck('tipo_codigo')->all());
        $this->assertSame('pendiente', $invoice->fresh()->anexos_estado);
    }

    /** La lista se completa a lo largo del día: guardar a medias tiene que valer. */
    public function test_admite_guardar_la_lista_incompleta(): void
    {
        $invoice = $this->factura([['producto' => $this->laptop, 'cantidad' => 3]]);
        $item    = $invoice->sale->items->first();

        $this->put("/admin/siat/invoices/{$invoice->id}/anexos", [
            'anexos' => [
                ['sale_item_id' => $item->id, 'codigo' => 'SN-0001'],
                // Los huecos del formulario llegan vacíos y se descartan.
                ['sale_item_id' => $item->id, 'codigo' => '  '],
                ['sale_item_id' => $item->id, 'codigo' => ''],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertCount(1, $invoice->fresh()->anexos);
        $this->assertStringContainsString('faltan 2', session('success'));
    }

    public function test_guardar_reemplaza_la_lista_anterior(): void
    {
        $invoice = $this->factura([['producto' => $this->laptop, 'cantidad' => 1]]);
        $item    = $invoice->sale->items->first();

        $this->put("/admin/siat/invoices/{$invoice->id}/anexos", [
            'anexos' => [['sale_item_id' => $item->id, 'codigo' => 'SN-MAL']],
        ]);

        $this->put("/admin/siat/invoices/{$invoice->id}/anexos", [
            'anexos' => [['sale_item_id' => $item->id, 'codigo' => 'SN-BIEN']],
        ])->assertSessionHasNoErrors();

        $anexos = $invoice->fresh()->anexos;

        $this->assertCount(1, $anexos);
        $this->assertSame('SN-BIEN', $anexos->first()->codigo);
    }

    /** El mismo número de serie dos veces es la misma unidad declarada dos veces. */
    public function test_rechaza_un_codigo_repetido(): void
    {
        $invoice = $this->factura([['producto' => $this->laptop, 'cantidad' => 2]]);
        $item    = $invoice->sale->items->first();

        $this->put("/admin/siat/invoices/{$invoice->id}/anexos", [
            'anexos' => [
                ['sale_item_id' => $item->id, 'codigo' => 'SN-0001'],
                ['sale_item_id' => $item->id, 'codigo' => 'SN-0001'],
            ],
        ])->assertSessionHasErrors('anexos.0.codigo');

        $this->assertCount(0, $invoice->fresh()->anexos);
    }

    public function test_rechaza_mas_codigos_que_unidades_vendidas(): void
    {
        $invoice = $this->factura([['producto' => $this->laptop, 'cantidad' => 1]]);
        $item    = $invoice->sale->items->first();

        $this->put("/admin/siat/invoices/{$invoice->id}/anexos", [
            'anexos' => [
                ['sale_item_id' => $item->id, 'codigo' => 'SN-0001'],
                ['sale_item_id' => $item->id, 'codigo' => 'SN-0002'],
            ],
        ])->assertSessionHasErrors('siat');

        $this->assertCount(0, $invoice->fresh()->anexos);
    }

    public function test_rechaza_un_codigo_para_un_producto_que_no_lleva_anexo(): void
    {
        $invoice = $this->factura([
            ['producto' => $this->laptop, 'cantidad' => 1],
            ['producto' => $this->cable,  'cantidad' => 1],
        ]);

        $cableItem = $invoice->sale->items->firstWhere('product_id', $this->cable->id);

        $this->put("/admin/siat/invoices/{$invoice->id}/anexos", [
            'anexos' => [['sale_item_id' => $cableItem->id, 'codigo' => 'SN-0001']],
        ])->assertSessionHasErrors('siat');
    }

    public function test_rechaza_un_codigo_de_una_linea_de_otra_factura(): void
    {
        $invoice = $this->factura([['producto' => $this->laptop, 'cantidad' => 1]]);
        $otra    = $this->factura([['producto' => $this->laptop, 'cantidad' => 1]]);

        $this->put("/admin/siat/invoices/{$invoice->id}/anexos", [
            'anexos' => [['sale_item_id' => $otra->sale->items->first()->id, 'codigo' => 'SN-0001']],
        ])->assertSessionHasErrors('siat');

        $this->assertCount(0, $invoice->fresh()->anexos);
    }

    /** Ya declarados al SIN, cambiarlos en local solo crearía una discrepancia. */
    public function test_no_se_tocan_los_anexos_ya_aceptados_por_el_sin(): void
    {
        $invoice = $this->factura(
            [['producto' => $this->laptop, 'cantidad' => 1]],
            ['anexos_estado' => 'enviado', 'anexos_enviado_at' => now()],
        );
        $item = $invoice->sale->items->first();

        $invoice->anexos()->create([
            'sale_item_id' => $item->id, 'codigo' => 'SN-ORIGINAL', 'tipo_codigo' => SiatAnexo::TIPO_SERIE,
        ]);

        $this->put("/admin/siat/invoices/{$invoice->id}/anexos", [
            'anexos' => [['sale_item_id' => $item->id, 'codigo' => 'SN-CAMBIADO']],
        ])->assertSessionHasErrors('siat');

        $this->assertSame('SN-ORIGINAL', $invoice->fresh()->anexos->first()->codigo);
    }

    // ─── Envío al SIN ────────────────────────────────────────────────────────

    public function test_declara_los_anexos_al_sin_y_registra_la_recepcion(): void
    {
        $this->setting();
        $invoice = $this->factura([['producto' => $this->laptop, 'cantidad' => 2]]);
        $item    = $invoice->sale->items->first();

        $invoice->anexos()->createMany([
            ['sale_item_id' => $item->id, 'codigo' => 'SN-0001', 'tipo_codigo' => SiatAnexo::TIPO_SERIE],
            ['sale_item_id' => $item->id, 'codigo' => 'SN-0002', 'tipo_codigo' => SiatAnexo::TIPO_SERIE],
        ]);

        $this->mock(SiatFacturacionService::class, function ($mock) use ($invoice): void {
            $mock->shouldReceive('recepcionAnexos')
                ->once()
                ->withArgs(function ($setting, $cuf, $anexos, $cufd, $tipoFactura) use ($invoice): bool {
                    return $cuf === $invoice->cuf
                        && count($anexos) === 2
                        // El código del producto y el homologado tienen que ser los
                        // mismos que declaró el XML de la factura.
                        && $anexos[0]['codigoProducto'] === 'LAP-1'
                        && $anexos[0]['codigoProductoSin'] === 1001967
                        && $anexos[0]['tipoCodigo'] === SiatAnexo::TIPO_SERIE
                        && $anexos[0]['codigo'] === 'SN-0001'
                        && $tipoFactura === 1;
                })
                ->andReturn([
                    'codigoRecepcion' => 'REC-ANEXO-1', 'codigoEstado' => 908,
                    'codigoDescripcion' => 'VALIDADA', 'mensajes' => [], 'respuesta' => [],
                ]);
        });

        $this->post("/admin/siat/invoices/{$invoice->id}/anexos/send")
            ->assertSessionHasNoErrors();

        $invoice->refresh();
        $this->assertSame('enviado', $invoice->anexos_estado);
        $this->assertSame('REC-ANEXO-1', $invoice->anexos_codigo_recepcion);
        $this->assertNotNull($invoice->anexos_enviado_at);
    }

    /**
     * Es el punto que más importa: el SIN no admite añadir códigos a una factura
     * ya declarada, así que enviar de menos deja la declaración mal para siempre.
     */
    public function test_no_envia_una_lista_incompleta(): void
    {
        $this->setting();
        $invoice = $this->factura([['producto' => $this->laptop, 'cantidad' => 3]]);
        $item    = $invoice->sale->items->first();

        $invoice->anexos()->create([
            'sale_item_id' => $item->id, 'codigo' => 'SN-0001', 'tipo_codigo' => SiatAnexo::TIPO_SERIE,
        ]);

        $this->mock(SiatFacturacionService::class, function ($mock): void {
            $mock->shouldNotReceive('recepcionAnexos');
        });

        $this->post("/admin/siat/invoices/{$invoice->id}/anexos/send")
            ->assertSessionHasErrors('siat');

        $this->assertSame('pendiente', $invoice->fresh()->anexos_estado);
    }

    /** El SIN los ata a una factura que ya tiene: antes de enviarla no existe. */
    public function test_no_envia_anexos_de_una_factura_todavia_pendiente(): void
    {
        $this->setting();
        $invoice = $this->factura([['producto' => $this->laptop, 'cantidad' => 1]], ['estado' => 'pendiente']);

        $invoice->anexos()->create([
            'sale_item_id' => $invoice->sale->items->first()->id,
            'codigo' => 'SN-0001', 'tipo_codigo' => SiatAnexo::TIPO_SERIE,
        ]);

        $this->mock(SiatFacturacionService::class, function ($mock): void {
            $mock->shouldNotReceive('recepcionAnexos');
        });

        $this->post("/admin/siat/invoices/{$invoice->id}/anexos/send")
            ->assertSessionHasErrors('siat');
    }

    public function test_no_envia_anexos_de_una_factura_anulada(): void
    {
        $this->setting();
        $invoice = $this->factura(
            [['producto' => $this->laptop, 'cantidad' => 1]],
            ['estado' => 'anulada', 'anulado_at' => now()],
        );

        $invoice->anexos()->create([
            'sale_item_id' => $invoice->sale->items->first()->id,
            'codigo' => 'SN-0001', 'tipo_codigo' => SiatAnexo::TIPO_SERIE,
        ]);

        $this->mock(SiatFacturacionService::class, function ($mock): void {
            $mock->shouldNotReceive('recepcionAnexos');
        });

        $this->post("/admin/siat/invoices/{$invoice->id}/anexos/send")
            ->assertSessionHasErrors('siat');
    }

    public function test_una_factura_sin_productos_con_serie_no_tiene_nada_que_declarar(): void
    {
        $this->setting();
        $invoice = $this->factura([['producto' => $this->cable, 'cantidad' => 1]], ['anexos_estado' => null]);

        $this->mock(SiatFacturacionService::class, function ($mock): void {
            $mock->shouldNotReceive('recepcionAnexos');
        });

        $this->post("/admin/siat/invoices/{$invoice->id}/anexos/send")
            ->assertSessionHasErrors('siat');
    }

    /** Un rechazo deja constancia y no se pierde: la lista sigue guardada. */
    public function test_un_rechazo_del_sin_queda_registrado_en_la_factura(): void
    {
        $this->setting();
        $invoice = $this->factura([['producto' => $this->laptop, 'cantidad' => 1]]);

        $invoice->anexos()->create([
            'sale_item_id' => $invoice->sale->items->first()->id,
            'codigo' => 'SN-0001', 'tipo_codigo' => SiatAnexo::TIPO_SERIE,
        ]);

        $this->mock(SiatFacturacionService::class, function ($mock): void {
            $mock->shouldReceive('recepcionAnexos')
                ->andThrow(new SiatException('El SIN rechazó la operación: 994 CUF INEXISTENTE'));
        });

        $this->post("/admin/siat/invoices/{$invoice->id}/anexos/send")
            ->assertSessionHasErrors('siat');

        $invoice->refresh();
        $this->assertSame('error', $invoice->anexos_estado);
        $this->assertStringContainsString('994', $invoice->anexos_mensaje_error);
        $this->assertCount(1, $invoice->anexos);
    }

    /** En simulado no hay a quién declarar nada, y hay que decirlo. */
    public function test_en_modo_simulado_no_se_finge_haber_llamado_al_sin(): void
    {
        $this->setting('simulado');
        $invoice = $this->factura([['producto' => $this->laptop, 'cantidad' => 1]]);

        $invoice->anexos()->create([
            'sale_item_id' => $invoice->sale->items->first()->id,
            'codigo' => 'SN-0001', 'tipo_codigo' => SiatAnexo::TIPO_SERIE,
        ]);

        $this->mock(SiatFacturacionService::class, function ($mock): void {
            $mock->shouldNotReceive('recepcionAnexos');
        });

        $this->post("/admin/siat/invoices/{$invoice->id}/anexos/send")
            ->assertSessionHasNoErrors();

        $this->assertSame('enviado', $invoice->fresh()->anexos_estado);
        $this->assertStringContainsString('simulado', session('success'));
    }

    public function test_declarar_anexos_necesita_una_configuracion_activa(): void
    {
        $invoice = $this->factura([['producto' => $this->laptop, 'cantidad' => 1]]);

        $this->post("/admin/siat/invoices/{$invoice->id}/anexos/send")
            ->assertSessionHasErrors('siat');
    }

    // ─── Homologación ────────────────────────────────────────────────────────

    public function test_se_marca_desde_la_homologacion_que_un_producto_lleva_imei(): void
    {
        $setting = $this->setting();

        $this->put("/admin/siat/homologation/{$this->cable->id}", [
            'setting_id'          => $setting->id,
            'codigo_producto_sin' => 1001967,
            'unidad_medida_sin'   => 57,
            'tipo_codigo_anexo'   => SiatAnexo::TIPO_IMEI,
        ])->assertSessionHasNoErrors();

        $this->assertSame(SiatAnexo::TIPO_IMEI, $this->cable->fresh()->tipo_codigo_anexo);
        $this->assertTrue($this->cable->fresh()->requiereAnexo());
    }

    public function test_se_puede_quitar_la_marca_de_anexo(): void
    {
        $setting = $this->setting();

        $this->put("/admin/siat/homologation/{$this->laptop->id}", [
            'setting_id'          => $setting->id,
            'codigo_producto_sin' => 1001967,
            'unidad_medida_sin'   => 57,
            'tipo_codigo_anexo'   => null,
        ])->assertSessionHasNoErrors();

        $this->assertNull($this->laptop->fresh()->tipo_codigo_anexo);
    }

    public function test_solo_admite_los_dos_tipos_de_la_parametrica(): void
    {
        $setting = $this->setting();

        $this->put("/admin/siat/homologation/{$this->cable->id}", [
            'setting_id'          => $setting->id,
            'codigo_producto_sin' => 1001967,
            'unidad_medida_sin'   => 57,
            'tipo_codigo_anexo'   => 9,
        ])->assertSessionHasErrors('tipo_codigo_anexo');
    }
}
