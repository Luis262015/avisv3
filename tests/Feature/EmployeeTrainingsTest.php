<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * La relación entre empleados y capacitaciones.
 *
 * El fallo que motivó estos tests: abrir la ficha de un empleado reventaba con
 * «Table 'avisv3.employee_training' doesn't exist». La convención de Eloquent
 * deduce el nombre de la tabla pivote poniendo los dos modelos en orden
 * alfabético —`employee_training`— y la migración la creó como
 * `training_employee`. Ninguna de las dos relaciones nombraba la tabla.
 *
 * `->using(TrainingParticipant::class)` no lo salvaba, y ahí estaba la trampa:
 * fija la clase con la que se instancian las filas del pivote, no la tabla del
 * JOIN. A simple vista parecía que sí.
 *
 * Estaba roto por los dos lados, así que caían tanto la ficha del empleado como
 * el listado de capacitaciones. La suite pasaba en verde porque ninguna prueba
 * llegaba a tocar la relación.
 */
class EmployeeTrainingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin', 'web');

        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);
    }

    private function empleado(): Employee
    {
        return Employee::create([
            'employee_code' => 'EMP-001',
            'first_name'    => 'Daniela',
            'last_name'     => 'Ergueta',
            'hire_date'     => now()->subYear()->toDateString(),
            'position'      => 'Vendedora',
            'status'        => 'active',
        ]);
    }

    private function capacitacion(string $titulo = 'Atención al cliente'): Training
    {
        return Training::create([
            'title'      => $titulo,
            'start_date' => now()->subMonth(),
            'status'     => 'completed',
        ]);
    }

    /** La tabla se llama así en la migración; si alguien la renombra, esto avisa. */
    public function test_la_tabla_pivote_se_llama_training_employee(): void
    {
        $this->assertTrue(Schema::hasTable('training_employee'));
        $this->assertFalse(
            Schema::hasTable('employee_training'),
            'Si existiera, la convención de Eloquent la usaría y el nombre explícito sobraría.',
        );
    }

    /** Las dos relaciones tienen que apuntar a la tabla que existe de verdad. */
    public function test_ambos_lados_de_la_relacion_usan_la_tabla_correcta(): void
    {
        $this->assertSame('training_employee', (new Employee)->trainings()->getTable());
        $this->assertSame('training_employee', (new Training)->employees()->getTable());
    }

    public function test_un_empleado_lee_sus_capacitaciones_con_los_datos_del_pivote(): void
    {
        $empleado = $this->empleado();
        $curso    = $this->capacitacion();

        $curso->employees()->attach($empleado->id, [
            'status'       => 'completed',
            'score'        => 88.5,
            'completed_at' => now()->subDays(3)->toDateString(),
        ]);

        $leido = Employee::with('trainings')->findOrFail($empleado->id)->trainings;

        $this->assertCount(1, $leido);
        $this->assertSame('Atención al cliente', $leido->first()->title);
        $this->assertSame('completed', $leido->first()->pivot->status);
        $this->assertSame('88.50', (string) $leido->first()->pivot->score);
    }

    public function test_una_capacitacion_lee_sus_participantes(): void
    {
        $empleado = $this->empleado();
        $curso    = $this->capacitacion();

        $empleado->trainings()->attach($curso->id, ['status' => 'enrolled']);

        $this->assertCount(1, Training::with('employees')->findOrFail($curso->id)->employees);
        $this->assertSame(1, Training::withCount('employees')->findOrFail($curso->id)->employees_count);
    }

    // ─── Las pantallas que se caían ──────────────────────────────────────────

    /** El fallo tal cual lo vio el usuario: pulsar «Ver» en un empleado. */
    public function test_la_ficha_del_empleado_abre(): void
    {
        $empleado = $this->empleado();
        $this->capacitacion()->employees()->attach($empleado->id, ['status' => 'completed']);

        $this->get("/admin/employees/{$empleado->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/employees/show')
                ->where('stats.trainings', 1)
                ->has('employee.trainings', 1));
    }

    /** Caía por lo mismo, aunque nadie lo hubiera notado todavía. */
    public function test_el_listado_de_capacitaciones_abre(): void
    {
        $empleado = $this->empleado();
        $this->capacitacion()->employees()->attach($empleado->id, ['status' => 'enrolled']);

        $this->get('/admin/trainings')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('trainings.data.0.employees_count'));
    }

    public function test_se_inscribe_y_se_da_de_baja_a_un_participante(): void
    {
        $empleado = $this->empleado();
        $curso    = $this->capacitacion();

        $this->post("/admin/trainings/{$curso->id}/participants", ['employee_id' => $empleado->id])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $curso->fresh()->employees()->count());

        $this->delete("/admin/trainings/{$curso->id}/participants/{$empleado->id}")
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $curso->fresh()->employees()->count());
    }
}
