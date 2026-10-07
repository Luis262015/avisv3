<?php

namespace Database\Seeders;

use App\Models\Central\Plan;
use App\Models\Central\PlatformAdmin;
use App\Models\Central\PlatformSetting;
use Illuminate\Database\Seeder;

/**
 * Lo mínimo para que la plataforma arranque: planes que ofrecer y, en
 * desarrollo, alguien que pueda entrar al panel.
 *
 * Los precios son una propuesta de partida, no una tarifa decidida: se
 * cambian desde el panel sin tocar código.
 */
class PlatformSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'slug' => 'gratis',
                'name' => 'Gratis',
                'tagline' => 'Para empezar a vender y ordenar tu inventario.',
                'price_monthly' => 0,
                'price_yearly' => 0,
                'trial_days' => 0,
                'max_stores' => 1,
                'max_users' => 2,
                'max_products' => 100,
                'max_invoices_month' => null,
                'modules' => [],
                'is_featured' => false,
                'sort_order' => 1,
            ],
            [
                'slug' => 'negocio',
                'name' => 'Negocio',
                'tagline' => 'Factura ante el SIN y lleva el control completo del negocio.',
                'price_monthly' => 199,
                'price_yearly' => 1990,
                'trial_days' => 14,
                'max_stores' => 3,
                'max_users' => 8,
                'max_products' => 3000,
                'max_invoices_month' => 1500,
                'modules' => ['facturacion', 'comercial', 'finanzas', 'multitienda', 'reportes'],
                'is_featured' => true,
                'sort_order' => 2,
            ],
            [
                'slug' => 'empresa',
                'name' => 'Empresa',
                'tagline' => 'Sin topes, con recursos humanos y todas las sucursales que necesites.',
                'price_monthly' => 449,
                'price_yearly' => 4490,
                'trial_days' => 14,
                'max_stores' => null,
                'max_users' => null,
                'max_products' => null,
                'max_invoices_month' => null,
                'modules' => array_keys(config('plans.modules')),
                'is_featured' => false,
                'sort_order' => 3,
            ],
        ];

        foreach ($plans as $plan) {
            // Solo se crean: lo que ya se haya editado en el panel no se pisa.
            Plan::firstOrCreate(['slug' => $plan['slug']], $plan);
        }

        if (PlatformSetting::query()->doesntExist()) {
            PlatformSetting::put(['brand_name' => 'AVIS']);
        }

        if (app()->environment('local') && PlatformAdmin::query()->doesntExist()) {
            PlatformAdmin::create([
                'name' => 'Administrador',
                'email' => 'plataforma@avis.test',
                'password' => 'password',
            ]);
        }
    }
}
