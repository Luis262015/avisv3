<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Models\SiatSetting;
use App\Models\Store;
use App\Models\User;
use App\Services\Siat\SiatException;
use App\Services\Siat\SiatSincronizacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Homologación de productos con las paramétricas del SIN.
 *
 * Las paramétricas se piden por SOAP, así que el servicio de sincronización va
 * doblado: lo que se comprueba aquí es el filtrado por actividad económica, la
 * validación de los códigos y que la pantalla siga en pie cuando el SIN no está.
 */
class SiatHomologationTest extends TestCase
{
    use RefreshDatabase;

    private SiatSetting $setting;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin', 'web');

        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);

        $store = Store::create(['name' => 'Tienda Central', 'is_active' => true]);

        $this->setting = SiatSetting::create([
            'store_id'            => $store->id,
            'nit'                 => '1234567890',
            'razon_social'        => 'EMPRESA DE PRUEBA SRL',
            'municipio'           => 'LA PAZ',
            'actividad_economica' => '4741100',
            'ambiente'            => 'piloto',
            'cuis'                => 'CUIS-DE-PRUEBA',
            'is_active'           => true,
        ]);

        $this->product = Product::create([
            'name'   => 'Coca Cola 2L',
            'slug'   => 'coca-cola-2l',
            'sku'    => 'CC-2L',
            'price'  => 12,
            'cost'   => 8,
            'stock'  => 5,
            'status' => 'active',
        ]);
    }

    /**
     * Pide la respuesta como Inertia (JSON) y no como HTML.
     *
     * Renderizar la plantilla obligaría a tener el bundle de Vite compilado, y una
     * página recién añadida no está en el manifest hasta el siguiente build: el
     * test fallaría por eso y no por la lógica que comprueba. Por eso las
     * aserciones van sobre `props.*` en vez de con `assertInertia()`.
     */
    private function visitar(string $url): \Illuminate\Testing\TestResponse
    {
        // La versión la fija el middleware durante la petición a partir del
        // manifest, así que hay que calcularla igual o Inertia responde 409.
        $version = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());

        return $this->get($url, [
            'X-Inertia'         => 'true',
            'X-Inertia-Version' => (string) $version,
        ]);
    }

    /**
     * El servicio de sincronización habla SOAP con el SIN; en las pruebas se
     * sustituye por uno que devuelve las mismas estructuras.
     */
    private function fakeCatalogo(): void
    {
        $this->mock(SiatSincronizacionService::class, function ($mock): void {
            $mock->shouldReceive('productosServicios')->andReturn([
                ['actividad' => '4741100', 'codigo' => 1001966, 'descripcion' => 'ORDENADORES DE ESCRITORIO'],
                ['actividad' => '4741100', 'codigo' => 1001967, 'descripcion' => 'COMPUTADORAS PORTATILES'],
                // De otra actividad del mismo NIT: no debe ofrecerse aquí.
                ['actividad' => '620000',  'codigo' => 99123,   'descripcion' => 'SERVICIOS DE PROGRAMACION'],
            ]);

            $mock->shouldReceive('unidadesMedida')->andReturn([
                57 => 'UNIDAD (BIENES)',
                62 => 'LITRO',
            ]);

            $mock->shouldReceive('olvidarCache')->andReturnNull();
        });
    }

    public function test_it_only_offers_products_of_the_configured_economic_activity(): void
    {
        $this->fakeCatalogo();

        $this->visitar('/admin/siat/homologation')
            ->assertOk()
            ->assertJsonPath('component', 'admin/siat/homologation/index')
            ->assertJsonPath('props.catalogo.error', null)
            ->assertJsonCount(2, 'props.catalogo.productos')
            // Ordenados por descripción: "COMPUTADORAS" antes que "ORDENADORES".
            ->assertJsonPath('props.catalogo.productos.0.codigo', 1001967)
            ->assertJsonCount(2, 'props.catalogo.unidades');
    }

    public function test_it_reports_progress_so_the_pending_products_are_visible(): void
    {
        $this->fakeCatalogo();

        Product::create([
            'name' => 'Ya homologado', 'slug' => 'ya-homologado', 'price' => 1, 'cost' => 1,
            'stock' => 0, 'status' => 'active', 'codigo_producto_sin' => 1001966, 'unidad_medida_sin' => 57,
        ]);

        $this->visitar('/admin/siat/homologation')
            ->assertJsonPath('props.stats.total', 2)
            ->assertJsonPath('props.stats.homologados', 1);
    }

    /** Sin catálogo la pantalla tiene que abrirse igual y explicar el motivo. */
    public function test_it_still_renders_when_the_sin_is_unreachable(): void
    {
        $this->mock(SiatSincronizacionService::class, function ($mock): void {
            $mock->shouldReceive('productosServicios')
                ->andThrow(new SiatException('No hay conexión con el servidor del SIN.'));
        });

        $this->visitar('/admin/siat/homologation')
            ->assertOk()
            ->assertJsonCount(0, 'props.catalogo.productos')
            ->assertJsonPath('props.catalogo.error', 'No hay conexión con el servidor del SIN.');
    }

    /** Una actividad sin productos delata que la actividad no es la del NIT. */
    public function test_it_warns_when_the_activity_has_no_homologated_products(): void
    {
        $this->mock(SiatSincronizacionService::class, function ($mock): void {
            $mock->shouldReceive('productosServicios')->andReturn([
                ['actividad' => '620000', 'codigo' => 99123, 'descripcion' => 'SERVICIOS DE PROGRAMACION'],
            ]);
            $mock->shouldReceive('unidadesMedida')->andReturn([57 => 'UNIDAD (BIENES)']);
        });

        $respuesta = $this->visitar('/admin/siat/homologation')
            ->assertJsonCount(0, 'props.catalogo.productos')
            ->assertJsonCount(1, 'props.catalogo.unidades');

        $this->assertStringContainsString('4741100', (string) $respuesta->json('props.catalogo.error'));
    }

    public function test_it_homologates_a_product(): void
    {
        $this->fakeCatalogo();

        $this->put("/admin/siat/homologation/{$this->product->id}", [
            'setting_id'          => $this->setting->id,
            'codigo_producto_sin' => 1001966,
            'unidad_medida_sin'   => 57,
        ])->assertSessionHasNoErrors();

        $this->assertSame(1001966, $this->product->fresh()->codigo_producto_sin);
        $this->assertSame(57, $this->product->fresh()->unidad_medida_sin);
    }

    public function test_it_rejects_a_code_outside_the_activity_catalog(): void
    {
        $this->fakeCatalogo();

        // 99123 existe en el SIN pero pertenece a otra actividad económica: el SIN
        // rechazaría la factura completa.
        $this->put("/admin/siat/homologation/{$this->product->id}", [
            'setting_id'          => $this->setting->id,
            'codigo_producto_sin' => 99123,
            'unidad_medida_sin'   => 57,
        ])->assertSessionHasErrors('codigo_producto_sin');

        $this->assertNull($this->product->fresh()->codigo_producto_sin);
    }

    public function test_it_rejects_a_unit_that_is_not_in_the_parametric(): void
    {
        $this->fakeCatalogo();

        $this->put("/admin/siat/homologation/{$this->product->id}", [
            'setting_id'          => $this->setting->id,
            'codigo_producto_sin' => 1001966,
            'unidad_medida_sin'   => 999,
        ])->assertSessionHasErrors('unidad_medida_sin');
    }

    /** Sin catálogo no se puede validar, pero tampoco se debe bloquear el alta. */
    public function test_it_accepts_a_code_typed_by_hand_when_the_catalog_is_unavailable(): void
    {
        $this->mock(SiatSincronizacionService::class, function ($mock): void {
            $mock->shouldReceive('productosServicios')->andThrow(new SiatException('SIN caído.'));
        });

        $this->put("/admin/siat/homologation/{$this->product->id}", [
            'setting_id'          => $this->setting->id,
            'codigo_producto_sin' => 1001966,
            'unidad_medida_sin'   => 57,
        ])->assertSessionHasNoErrors();

        $this->assertSame(1001966, $this->product->fresh()->codigo_producto_sin);
    }

    public function test_it_homologates_several_products_at_once(): void
    {
        $this->fakeCatalogo();

        $otro = Product::create([
            'name' => 'Fanta 2L', 'slug' => 'fanta-2l', 'price' => 10, 'cost' => 6,
            'stock' => 3, 'status' => 'active',
        ]);

        $this->post('/admin/siat/homologation/bulk', [
            'setting_id'          => $this->setting->id,
            'product_ids'         => [$this->product->id, $otro->id],
            'codigo_producto_sin' => 1001967,
            'unidad_medida_sin'   => 62,
        ])->assertSessionHasNoErrors();

        $this->assertSame(1001967, $this->product->fresh()->codigo_producto_sin);
        $this->assertSame(1001967, $otro->fresh()->codigo_producto_sin);
        $this->assertSame(62, $otro->fresh()->unidad_medida_sin);
    }

    public function test_it_can_discard_the_cached_parametrics(): void
    {
        $this->fakeCatalogo();

        $this->post('/admin/siat/homologation/refresh', ['setting_id' => $this->setting->id])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');
    }

    public function test_it_filters_products_pending_homologation(): void
    {
        $this->fakeCatalogo();

        Product::create([
            'name' => 'Ya homologado', 'slug' => 'ya-homologado', 'price' => 1, 'cost' => 1,
            'stock' => 0, 'status' => 'active', 'codigo_producto_sin' => 1001966, 'unidad_medida_sin' => 57,
        ]);

        $this->visitar('/admin/siat/homologation?estado=pendientes')
            ->assertJsonCount(1, 'props.products.data')
            ->assertJsonPath('props.products.data.0.name', 'Coca Cola 2L');
    }
}
