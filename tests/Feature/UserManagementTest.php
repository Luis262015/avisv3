<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Los usuarios del sistema los crea el administrador de la empresa: ya no hay
 * registro abierto.
 */
class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        (new RolesPermissionsSeeder)->seedRolesAndPermissions();

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    private function datos(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Carla Cajera',
            'email' => 'carla@tienda.test',
            'role' => 'vendedor',
            'password' => 'secreto-seguro-123',
            'password_confirmation' => 'secreto-seguro-123',
        ], $overrides);
    }

    public function test_el_registro_abierto_no_existe(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_el_administrador_crea_un_usuario_con_su_rol(): void
    {
        $this->actingAs($this->admin)->post('/admin/users', $this->datos())->assertRedirect(route('admin.users.index'));

        $carla = User::where('email', 'carla@tienda.test')->firstOrFail();
        $this->assertTrue($carla->hasRole('vendedor'));
        $this->assertNotNull($carla->email_verified_at);
    }

    public function test_solo_el_administrador_gestiona_usuarios(): void
    {
        $operador = User::factory()->create();
        $operador->assignRole('operador');

        $this->actingAs($operador)->get('/admin/users')->assertForbidden();
        $this->actingAs($operador)->post('/admin/users', $this->datos())->assertForbidden();
    }

    public function test_editar_sin_escribir_contrasena_conserva_la_anterior(): void
    {
        $carla = User::factory()->create(['password' => 'la-de-siempre-123']);
        $carla->assignRole('vendedor');
        $hash = $carla->password;

        $this->actingAs($this->admin)->put("/admin/users/{$carla->id}", [
            'name' => 'Carla Operadora', 'email' => $carla->email, 'role' => 'operador', 'password' => '', 'password_confirmation' => '',
        ])->assertRedirect(route('admin.users.index'));

        $carla->refresh();
        $this->assertSame($hash, $carla->password);
        $this->assertTrue($carla->hasRole('operador'));
        $this->assertFalse($carla->hasRole('vendedor'));
    }

    public function test_la_empresa_no_se_queda_sin_administrador(): void
    {
        $this->actingAs($this->admin)->put("/admin/users/{$this->admin->id}", [
            'name' => $this->admin->name, 'email' => $this->admin->email, 'role' => 'vendedor',
        ])->assertSessionHas('error');
        $this->assertTrue($this->admin->fresh()->hasRole('admin'));

        $this->actingAs($this->admin)->delete("/admin/users/{$this->admin->id}")->assertSessionHas('error');
        $this->assertNotNull($this->admin->fresh());
    }

    public function test_elimina_a_quien_no_tiene_historial(): void
    {
        $carla = User::factory()->create();
        $carla->assignRole('vendedor');

        $this->actingAs($this->admin)->delete("/admin/users/{$carla->id}")->assertSessionHas('success');
        $this->assertNull($carla->fresh());
    }
}
