<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CashRegister;
use App\Models\Department;
use App\Models\Employee;
use App\Models\SiatSetting;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Datos de operación para probar el ciclo de caja en varias tiendas.
 *
 * Crea dos sucursales además de la casa matriz, sus cajas, y una plantilla de
 * empleados con usuario propio para que cada turno tenga un responsable distinto.
 *
 * **Sobre el NIT compartido:** las tres tiendas facturan con el mismo NIT, que es
 * lo normal en una empresa con sucursales. Lo que las distingue ante el SIN es
 * `codigo_sucursal` —0 es la casa matriz—, y de él dependen el CUIS, el CUFD y el
 * correlativo. Por eso cada sucursal nueva lleva su propio código y **no** se le
 * copia el CUIS de la matriz: usar el mismo produciría facturas que el SIN
 * rechaza o, peor, que acepta contra la sucursal equivocada. El CUIS de cada una
 * hay que pedirlo al SIN, y para eso la sucursal debe existir en su padrón.
 *
 * Es idempotente: se puede volver a ejecutar sin duplicar nada.
 */
final class OperacionDemoSeeder extends Seeder
{
    /** Marca los registros creados aquí para poder distinguirlos y borrarlos. */
    private const DOMINIO = 'demo.avisv3.test';

    public function run(): void
    {
        $matriz = SiatSetting::where('codigo_sucursal', 0)->first();

        if ($matriz === null) {
            $this->command?->warn(
                'No hay configuración SIAT de casa matriz; las sucursales se crean sin datos del SIN.'
            );
        }

        $tiendas = $this->crearSucursales($matriz);
        $this->crearCajas($tiendas);
        $this->crearEmpleados();

        $this->command?->info('Listo: ' . Store::count() . ' tiendas, '
            . CashRegister::count() . ' cajas, ' . Employee::count() . ' empleados.');
    }

    /**
     * @return list<Store>
     */
    private function crearSucursales(?SiatSetting $matriz): array
    {
        $definiciones = [
            ['nombre' => 'Sucursal Sur',   'sucursal' => 1, 'direccion' => 'Av. Buenos Aires Nro. 1200, Zona Sur'],
            ['nombre' => 'Sucursal Norte', 'sucursal' => 2, 'direccion' => 'Av. Camacho Nro. 450, Zona Norte'],
        ];

        $tiendas = [];

        foreach ($definiciones as $def) {
            $tienda = Store::firstOrCreate(
                ['name' => $def['nombre']],
                [
                    'address'   => $def['direccion'],
                    'phone'     => '2' . random_int(1000000, 2999999),
                    'is_active' => true,
                ]
            );

            $tiendas[] = $tienda;

            if ($matriz === null) {
                continue;
            }

            SiatSetting::firstOrCreate(
                ['store_id' => $tienda->id],
                [
                    // Mismo contribuyente que la matriz: NIT, razón social, sistema
                    // y token son de la empresa, no del local.
                    'nit'                   => $matriz->nit,
                    'codigo_sistema'        => $matriz->codigo_sistema,
                    'razon_social'          => $matriz->razon_social,
                    'municipio'             => $matriz->municipio,
                    'actividad_economica'   => $matriz->actividad_economica,
                    'actividad_descripcion' => $matriz->actividad_descripcion,
                    'token_api'             => $matriz->token_api,
                    'modalidad'             => $matriz->modalidad,
                    'ambiente'              => $matriz->ambiente,
                    'tipo_factura_default'  => $matriz->tipo_factura_default,
                    // Lo propio de cada local.
                    'direccion'             => $def['direccion'],
                    'codigo_sucursal'       => $def['sucursal'],
                    'codigo_punto_venta'    => 0,
                    'nombre_punto_venta'    => $def['nombre'],
                    // El CUIS es por sucursal y hay que pedirlo al SIN.
                    'cuis'                  => null,
                    'is_active'             => true,
                ]
            );
        }

        return $tiendas;
    }

    /** @param list<Store> $nuevas */
    private function crearCajas(array $nuevas): void
    {
        // La matriz opera con dos cajas: sin una segunda no se puede probar que dos
        // turnos convivan en la misma tienda.
        $matriz = Store::orderBy('id')->first();

        if ($matriz !== null) {
            CashRegister::firstOrCreate(
                ['store_id' => $matriz->id, 'name' => 'Caja 2'],
                ['is_active' => true]
            );
        }

        foreach ($nuevas as $tienda) {
            foreach (['Caja 1', 'Caja 2'] as $nombre) {
                CashRegister::firstOrCreate(
                    ['store_id' => $tienda->id, 'name' => $nombre],
                    ['is_active' => true]
                );
            }
        }
    }

    private function crearEmpleados(): void
    {
        $ventas = Department::firstOrCreate(
            ['code' => 'VEN'],
            ['name' => 'Ventas', 'description' => 'Atención en piso y caja', 'is_active' => true]
        );

        $admin = Department::firstOrCreate(
            ['code' => 'ADM'],
            ['name' => 'Administración', 'description' => 'Contabilidad y compras', 'is_active' => true]
        );

        $plantilla = [
            ['Carla',   'Mamani Quispe',    'VEN', 'Cajera',              'vendedor', 3200],
            ['Rodrigo', 'Chávez Ledezma',   'VEN', 'Cajero',              'vendedor', 3200],
            ['Ximena',  'Torrez Blanco',    'VEN', 'Vendedora',           'vendedor', 3400],
            ['Marco',   'Villarroel Ríos',  'VEN', 'Encargado de tienda', 'operador', 5200],
            ['Daniela', 'Ergueta Salinas',  'ADM', 'Administradora',      'operador', 6000],
            ['Javier',  'Peñaranda Ortiz',  'ADM', 'Contador',            'operador', 6500],
        ];

        foreach ($plantilla as $i => [$nombre, $apellido, $area, $cargo, $rol, $salario]) {
            $correo = strtolower($nombre[0] . explode(' ', $apellido)[0]) . '@' . self::DOMINIO;

            $user = User::firstOrCreate(
                ['email' => $correo],
                ['name' => "{$nombre} {$apellido}", 'password' => Hash::make('password')]
            );

            if (Role::where('name', $rol)->exists() && ! $user->hasRole($rol)) {
                $user->assignRole($rol);
            }

            Employee::firstOrCreate(
                ['employee_code' => sprintf('EMP-%03d', $i + 1)],
                [
                    'user_id'         => $user->id,
                    'department_id'   => $area === 'VEN' ? $ventas->id : $admin->id,
                    'first_name'      => $nombre,
                    'last_name'       => $apellido,
                    'document_type'   => 'ci',
                    'document_number' => (string) random_int(3000000, 9999999),
                    'email'           => $correo,
                    'phone'           => '7' . random_int(1000000, 9999999),
                    'position'        => $cargo,
                    'hire_date'       => now()->subMonths(random_int(3, 48))->toDateString(),
                    'contract_type'   => 'indefinite',
                    'status'          => 'active',
                    'base_salary'     => $salario,
                ]
            );
        }
    }
}
