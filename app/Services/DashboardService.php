<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CashShift;
use App\Models\Payable;
use App\Models\PurchaseOrder;
use App\Models\Receivable;
use App\Models\Sale;
use App\Models\SiatInvoice;
use App\Models\StoreStock;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Los datos del panel operativo: qué pasó hoy y qué está pidiendo atención.
 *
 * Cada bloque se calcula por separado porque el controlador solo pide los que el
 * usuario tiene permiso de ver; un vendedor no debería costar las consultas de
 * inventario ni de finanzas.
 *
 * **Sobre el día:** la aplicación corre en UTC y el negocio en hora de Bolivia
 * (`siat.timezone`). Preguntar por `whereDate(created_at, today())` mandaría las
 * ventas desde las 20:00 al día siguiente, que es justo el cierre de una tienda.
 * Por eso el corte del día se calcula en hora local y se traduce a límites UTC
 * antes de consultar.
 */
final class DashboardService
{
    /** Cuántos días cubre la serie del gráfico. */
    private const DIAS_SERIE = 14;

    /**
     * Ventas de hoy, comparadas con ayer, y la serie de los últimos días.
     *
     * @return array{
     *     hoy_total: float, hoy_cantidad: int, ayer_total: float,
     *     variacion: ?float, ticket_promedio: float,
     *     serie: list<array{fecha: string, total: float, cantidad: int}>
     * }
     */
    public function ventas(?int $storeId): array
    {
        [$desdeHoy, $hastaHoy] = $this->rangoDelDia(0);
        [$desdeAyer, $hastaAyer] = $this->rangoDelDia(-1);

        $hoy = $this->ventasCompletadas($storeId)
            ->whereBetween('sales.created_at', [$desdeHoy, $hastaHoy])
            ->selectRaw('COUNT(*) as cantidad, COALESCE(SUM(total), 0) as total')
            ->first();

        $ayerTotal = (float) $this->ventasCompletadas($storeId)
            ->whereBetween('sales.created_at', [$desdeAyer, $hastaAyer])
            ->sum('total');

        $hoyTotal    = (float) ($hoy->total ?? 0);
        $hoyCantidad = (int) ($hoy->cantidad ?? 0);

        return [
            'hoy_total'       => $hoyTotal,
            'hoy_cantidad'    => $hoyCantidad,
            'ayer_total'      => $ayerTotal,
            // Sin ventas ayer no hay variación que calcular: un salto desde cero
            // es infinito, no un 100%.
            'variacion'       => $ayerTotal > 0 ? round(($hoyTotal - $ayerTotal) / $ayerTotal * 100, 1) : null,
            'ticket_promedio' => $hoyCantidad > 0 ? round($hoyTotal / $hoyCantidad, 2) : 0.0,
            'serie'           => $this->serieDeVentas($storeId),
        ];
    }

    /**
     * El turno de caja abierto, si lo hay, con lo que lleva vendido.
     *
     * @return array{abierto: bool, turno: ?array<string, mixed>, sin_abrir_hoy: bool}
     */
    public function caja(?int $storeId): array
    {
        $turno = CashShift::query()
            ->where('status', 'open')
            ->when($storeId, fn (Builder $q, int $id) => $q->whereHas(
                'cashRegister',
                fn (Builder $q2) => $q2->where('store_id', $id)
            ))
            ->with(['cashRegister.store:id,name', 'user:id,name'])
            ->latest('opened_at')
            ->first();

        if ($turno === null) {
            return ['abierto' => false, 'turno' => null, 'sin_abrir_hoy' => true];
        }

        return [
            'abierto'       => true,
            'sin_abrir_hoy' => false,
            'turno'         => [
                'id'             => $turno->id,
                'abierto_desde'  => $turno->opened_at?->toIso8601String(),
                'caja'           => $turno->cashRegister?->name,
                'tienda'         => $turno->cashRegister?->store?->name,
                'responsable'    => $turno->user?->name,
                'monto_apertura' => (float) $turno->opening_amount,
                'vendido'        => (float) $turno->sales()->where('status', 'completed')->sum('total'),
            ],
        ];
    }

    /**
     * Existencias agotadas o por debajo del mínimo, contadas por tienda.
     *
     * Un mismo producto puede aparecer dos veces si está bajo en dos sucursales:
     * son dos reposiciones distintas, no una.
     *
     * @return array{bajo: int, agotados: int, productos: list<array<string, mixed>>}
     */
    public function inventario(?int $storeId): array
    {
        $productos = $this->stockBajoPorTienda($storeId);

        return [
            'bajo'      => $productos->count(),
            'agotados'  => $productos->where('stock', '<=', 0)->count(),
            'productos' => $productos->take(8)->values()->all(),
        ];
    }

    /**
     * Órdenes de compra confirmadas que siguen sin recibirse.
     *
     * @return array{pendientes: int, monto: float, atrasadas: int, ordenes: list<array<string, mixed>>}
     */
    public function compras(?int $storeId): array
    {
        $ordenes = PurchaseOrder::query()
            ->whereIn('status', ['confirmed', 'sent', 'partial'])
            ->when($storeId, fn (Builder $q, int $id) => $q->where('store_id', $id))
            ->with('supplier:id,name')
            ->orderBy('expected_date')
            ->get();

        // Se comparan fechas como texto y no como instantes: `expected_date` es una
        // columna `date` y su cast la deja a medianoche UTC, mientras que "hoy" es
        // medianoche en Bolivia. Comparar los dos objetos marcaría como atrasada
        // una orden que vence justamente hoy.
        $hoy = $this->hoyLocal()->toDateString();
        $atrasada = static fn (PurchaseOrder $o): bool => $o->expected_date !== null
            && $o->expected_date->toDateString() < $hoy;

        return [
            'pendientes' => $ordenes->count(),
            'monto'      => (float) $ordenes->sum('total'),
            'atrasadas'  => $ordenes->filter($atrasada)->count(),
            'ordenes'    => $ordenes->take(5)->map(fn (PurchaseOrder $o): array => [
                'id'             => $o->id,
                'folio'          => $o->folio,
                'proveedor'      => $o->supplier?->name,
                'total'          => (float) $o->total,
                'estado'         => $o->status,
                'fecha_esperada' => $o->expected_date?->toDateString(),
                'atrasada'       => $atrasada($o),
            ])->values()->all(),
        ];
    }

    /**
     * Cobros y pagos vencidos.
     *
     * No se filtran por tienda: una cuenta por cobrar puede no venir de una venta
     * —las hay cargadas a mano— y las cuentas por pagar cuelgan del proveedor, no
     * de un local. Son deuda de la empresa, no de la sucursal.
     *
     * @return array{
     *     por_cobrar_vencidas: int, por_cobrar_monto: float,
     *     por_pagar_vencidas: int, por_pagar_monto: float,
     *     vencen_pronto: int
     * }
     */
    public function finanzas(): array
    {
        $hoy = $this->hoyLocal();

        $cobrarVencidas = Receivable::query()->outstanding()->whereDate('due_date', '<', $hoy);
        $pagarVencidas  = Payable::query()
            ->whereIn('status', ['pending', 'partial'])
            ->whereDate('due_date', '<', $hoy);

        return [
            'por_cobrar_vencidas' => (clone $cobrarVencidas)->count(),
            'por_cobrar_monto'    => (float) (clone $cobrarVencidas)->sum('balance'),
            'por_pagar_vencidas'  => (clone $pagarVencidas)->count(),
            'por_pagar_monto'     => (float) (clone $pagarVencidas)->sum('balance'),
            // Lo que vence dentro de la semana: avisa antes de que sea un problema.
            'vencen_pronto'       => Receivable::query()->outstanding()
                ->whereDate('due_date', '>=', $hoy)
                ->whereDate('due_date', '<=', $hoy->addDays(7))
                ->count(),
        ];
    }

    /**
     * Estado de la facturación electrónica.
     *
     * «Rechazada» no es un estado de la tabla: una factura que el SIN no aceptó se
     * queda en `pendiente` con el motivo en `mensaje_error`. Esa es la que hay que
     * mirar, porque la venta ya ocurrió y la factura no existe ante Impuestos.
     *
     * @return array{
     *     rechazadas: int, pendientes: int, enviadas_hoy: int,
     *     en_contingencia: int, ultimas_rechazadas: list<array<string, mixed>>
     * }
     */
    public function siat(?int $storeId): array
    {
        [$desdeHoy, $hastaHoy] = $this->rangoDelDia(0);

        $base = fn (): Builder => SiatInvoice::query()
            ->when($storeId, fn (Builder $q, int $id) => $q->where('store_id', $id));

        $rechazadas = $base()->where('estado', 'pendiente')->whereNotNull('mensaje_error');

        return [
            'rechazadas'      => (clone $rechazadas)->count(),
            'pendientes'      => $base()->where('estado', 'pendiente')->whereNull('mensaje_error')->count(),
            'enviadas_hoy'    => $base()->where('estado', 'enviada')
                ->whereBetween('enviado_at', [$desdeHoy, $hastaHoy])->count(),
            'en_contingencia' => $base()->where('estado', 'contingencia')->count(),
            'ultimas_rechazadas' => (clone $rechazadas)
                ->latest('id')
                ->limit(5)
                ->get(['id', 'numero_factura', 'nombre_razon_social', 'importe_total', 'mensaje_error'])
                ->map(fn (SiatInvoice $f): array => [
                    'id'      => $f->id,
                    'numero'  => $f->numero_factura,
                    'cliente' => $f->nombre_razon_social,
                    'importe' => (float) $f->importe_total,
                    'error'   => $f->mensaje_error,
                ])->all(),
        ];
    }

    // ── Internos ────────────────────────────────────────────────────────────

    /** Ventas completadas, acotadas a una tienda si se pidió. */
    private function ventasCompletadas(?int $storeId): Builder
    {
        // `sales` no guarda la tienda: se llega a ella por el turno y la caja, que
        // es el mismo camino que usan los reportes de ventas.
        return Sale::query()
            ->where('status', 'completed')
            ->when($storeId, fn (Builder $q, int $id) => $q->whereHas(
                'cashShift.cashRegister',
                fn (Builder $q2) => $q2->where('store_id', $id)
            ));
    }

    /**
     * Total vendido por día durante los últimos {@see self::DIAS_SERIE} días,
     * incluidos los días sin ventas.
     *
     * @return list<array{fecha: string, total: float, cantidad: int}>
     */
    private function serieDeVentas(?int $storeId): array
    {
        $desde = $this->inicioDelDiaLocal()->subDays(self::DIAS_SERIE - 1);

        // Indexado por fecha para poder rellenar los huecos: un día sin ventas no
        // aparece en el GROUP BY y el gráfico lo dibujaría como si no existiera.
        $porFecha = $this->ventasCompletadas($storeId)
            ->where('sales.created_at', '>=', $desde->utc())
            ->selectRaw($this->expresionFechaLocal() . ' as fecha')
            ->selectRaw('COALESCE(SUM(total), 0) as total, COUNT(*) as cantidad')
            ->groupByRaw($this->expresionFechaLocal())
            ->get()
            ->keyBy('fecha');

        $serie = [];

        for ($i = 0; $i < self::DIAS_SERIE; $i++) {
            $fecha = $desde->addDays($i)->toDateString();
            $fila  = $porFecha->get($fecha);

            $serie[] = [
                'fecha'    => $fecha,
                'total'    => (float) ($fila->total ?? 0),
                'cantidad' => (int) ($fila->cantidad ?? 0),
            ];
        }

        return $serie;
    }

    /**
     * Expresión SQL que convierte `sales.created_at` a la fecha local.
     *
     * Bolivia no aplica horario de verano, así que un desplazamiento fijo es
     * exacto; si algún día `siat.timezone` apunta a una zona que sí lo aplica,
     * esto habría que hacerlo con la tabla de zonas del motor.
     */
    private function expresionFechaLocal(): string
    {
        $horas = intdiv(CarbonImmutable::now($this->zona())->utcOffset(), 60);

        return DB::getDriverName() === 'sqlite'
            ? sprintf("date(sales.created_at, '%+d hours')", $horas)
            : sprintf('DATE(DATE_ADD(sales.created_at, INTERVAL %d HOUR))', $horas);
    }

    /**
     * Límites UTC de un día local. `$desplazamiento` 0 es hoy, -1 ayer.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function rangoDelDia(int $desplazamiento): array
    {
        $dia = $this->inicioDelDiaLocal()->addDays($desplazamiento);

        return [$dia->utc(), $dia->endOfDay()->utc()];
    }

    private function inicioDelDiaLocal(): CarbonImmutable
    {
        return CarbonImmutable::now($this->zona())->startOfDay();
    }

    /** La fecha de hoy en hora local, sin hora, para comparar contra columnas `date`. */
    private function hoyLocal(): CarbonImmutable
    {
        return CarbonImmutable::now($this->zona())->startOfDay();
    }

    private function zona(): string
    {
        return (string) config('siat.timezone', 'America/La_Paz');
    }

    /**
     * Existencias bajo mínimo, evaluadas siempre **por tienda**.
     *
     * Sin filtro devuelve las de todas las tiendas, no el total de la empresa
     * contra el mínimo del producto: ese cálculo daba por sano un producto con 40
     * unidades repartidas cuando una sucursal estaba en cero.
     *
     * El mínimo que rige es el propio de la tienda si lo tiene y el general del
     * producto si no, según {@see StoreStock::minimoEfectivoSql()}.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function stockBajoPorTienda(?int $storeId)
    {
        return StoreStock::query()
            ->join('products', 'products.id', '=', 'store_product_stocks.product_id')
            ->join('stores', 'stores.id', '=', 'store_product_stocks.store_id')
            ->when($storeId, fn ($q, $v) => $q->where('store_product_stocks.store_id', $v))
            ->where('products.status', 'active')
            ->where('products.track_inventory', true)
            ->where('stores.is_active', true)
            ->whereRaw(StoreStock::minimoEfectivoSql() . ' > 0')
            ->whereRaw('store_product_stocks.stock <= ' . StoreStock::minimoEfectivoSql())
            ->orderBy('store_product_stocks.stock')
            ->get([
                'products.id as id',
                'products.name as nombre',
                'products.sku as sku',
                'stores.name as tienda',
                'store_product_stocks.stock as stock',
                'store_product_stocks.min_stock as min_propio',
                'products.min_stock as min_general',
            ])
            ->map(fn ($fila): array => [
                'id'        => (int) $fila->id,
                'nombre'    => (string) $fila->nombre,
                'sku'       => $fila->sku,
                'tienda'    => (string) $fila->tienda,
                'stock'     => (float) $fila->stock,
                'min_stock' => (float) ($fila->min_propio ?? $fila->min_general),
            ]);
    }
}
