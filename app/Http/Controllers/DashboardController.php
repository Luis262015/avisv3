<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\DashboardRequest;
use App\Models\Store;
use App\Models\User;
use App\Services\DashboardService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Panel operativo: lo de hoy y lo que está pidiendo atención.
 *
 * Cada bloque se calcula solo si quien mira puede verlo. La comprobación decide
 * además si se ejecuta la consulta, de modo que un vendedor no paga el coste de
 * agregar inventario ni finanzas, y el frontend recibe `null` en lo que no le
 * toca en vez de ceros, que se leerían como "no hay nada" en lugar de "no te
 * corresponde".
 */
final class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard) {}

    public function __invoke(DashboardRequest $request): Response
    {
        /** @var User $user  Garantizado por el middleware `auth` y por el authorize() del request. */
        $user    = $request->user();
        $storeId = $request->storeId();

        $puede = [
            'ventas'     => $user->can('sales.view'),
            'caja'       => $user->can('cash-shifts.view'),
            'inventario' => $user->can('inventory.view'),
            'compras'    => $user->can('purchases.view'),
            // Finanzas y SIAT no tienen permiso propio en el padrón; se replica el
            // rol que ya exige cada módulo en routes/admin.php.
            'finanzas'   => $user->hasAnyRole(['admin', 'operador']),
            'siat'       => $user->hasRole('admin'),
        ];

        return Inertia::render('dashboard', [
            'puede'      => $puede,
            'filtros'    => ['store_id' => $storeId],
            'tiendas'    => Store::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'ventas'     => $puede['ventas'] ? $this->dashboard->ventas($storeId) : null,
            'caja'       => $puede['caja'] ? $this->dashboard->caja($storeId) : null,
            'inventario' => $puede['inventario'] ? $this->dashboard->inventario($storeId) : null,
            'compras'    => $puede['compras'] ? $this->dashboard->compras($storeId) : null,
            'finanzas'   => $puede['finanzas'] ? $this->dashboard->finanzas() : null,
            'siat'       => $puede['siat'] ? $this->dashboard->siat($storeId) : null,
        ]);
    }
}
