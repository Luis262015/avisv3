<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedRolesAndPermissions();

        // ── Usuario admin por defecto ───────────────────────────────────────
        // Solo para la instalación de una sola empresa. Las que se dan de alta
        // por el sitio traen su propio administrador y jamás reciben esta
        // cuenta de contraseña conocida.
        $user = User::firstOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'name'     => 'Administrador',
                'password' => bcrypt('password'),
            ]
        );
        $user->assignRole('admin');
    }

    /** Roles y permisos, sin crear ningún usuario. */
    public function seedRolesAndPermissions(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Tiendas
            'stores.view', 'stores.create', 'stores.edit', 'stores.delete',
            // Cajas
            'cash-registers.view', 'cash-registers.create', 'cash-registers.edit', 'cash-registers.delete',
            // Turnos
            'cash-shifts.view', 'cash-shifts.create', 'cash-shifts.close',
            // Productos
            'products.view', 'products.create', 'products.edit', 'products.delete',
            // Categorías, Marcas, Etiquetas
            'categories.manage', 'brands.manage', 'tags.manage',
            // Proveedores
            'suppliers.manage',
            // Compras
            'purchases.view', 'purchases.create', 'purchases.receive', 'purchases.cancel',
            // Ventas
            'sales.view', 'sales.create', 'sales.cancel',
            // Inventario
            'inventory.view', 'inventory.adjust',
            // Usuarios del sistema
            'users.manage',
            // Recursos Humanos
            'employees.manage', 'payroll.manage', 'attendance.manage', 'training.manage', 'hr.reports',
        ];

        foreach ($permissions as $perm) {
            // El guard va escrito: al crear una empresa desde el panel de la
            // plataforma el guard en curso es el suyo, y los roles nacerían
            // con él en vez de con el de los usuarios de la empresa.
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // ── Admin: acceso total ─────────────────────────────────────────────
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions($permissions);

        // ── Operador: gestión operativa sin config de tiendas ───────────────
        $operador = Role::firstOrCreate(['name' => 'operador', 'guard_name' => 'web']);
        $operador->syncPermissions([
            'cash-shifts.view', 'cash-shifts.create', 'cash-shifts.close',
            'products.view', 'products.create', 'products.edit',
            'categories.manage', 'brands.manage', 'tags.manage',
            'suppliers.manage',
            'purchases.view', 'purchases.create', 'purchases.receive', 'purchases.cancel',
            'sales.view', 'sales.create', 'sales.cancel',
            'inventory.view', 'inventory.adjust',
        ]);

        // ── Vendedor: solo ventas y turno de caja ───────────────────────────
        $vendedor = Role::firstOrCreate(['name' => 'vendedor', 'guard_name' => 'web']);
        $vendedor->syncPermissions([
            'cash-shifts.view', 'cash-shifts.create', 'cash-shifts.close',
            'products.view',
            'sales.view', 'sales.create',
        ]);
    }
}
