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
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        // ── Admin: acceso total ─────────────────────────────────────────────
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->syncPermissions($permissions);

        // ── Operador: gestión operativa sin config de tiendas ───────────────
        $operador = Role::firstOrCreate(['name' => 'operador']);
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
        $vendedor = Role::firstOrCreate(['name' => 'vendedor']);
        $vendedor->syncPermissions([
            'cash-shifts.view', 'cash-shifts.create', 'cash-shifts.close',
            'products.view',
            'sales.view', 'sales.create',
        ]);

        // ── Usuario admin por defecto ───────────────────────────────────────
        $user = User::firstOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'name'     => 'Administrador',
                'password' => bcrypt('password'),
            ]
        );
        $user->assignRole('admin');
    }
}
