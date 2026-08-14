<?php

namespace App\Services;

use App\Models\Purchase;
use App\Models\PurchaseItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Informes de compras.
 *
 * **Todo lo que sale de aquí va convertido a número.** Un `SUM()` sobre una
 * columna DECIMAL vuelve de la base como cadena —PDO no la convierte y Eloquent
 * no castea los agregados, que no son columnas del modelo—, así que el JSON
 * llevaba `"200.00"` donde la interfaz esperaba `200`. En el navegador eso
 * reventaba al formatear (`.toFixed` no existe en una cadena) y tumbaba la
 * página entera: el informe no se abría.
 */
class PurchaseReportService
{
    public function summary(array $filters): array
    {
        $query = $this->baseQuery($filters);

        return [
            'total_purchases' => (int) (clone $query)->count(),
            'total_amount'    => (float) (clone $query)->sum('total'),
            'avg_amount'      => (float) ((clone $query)->avg('total') ?? 0),
            'total_tax'       => (float) (clone $query)->sum('tax'),
            'unpaid_amount'   => (float) (clone $query)->where('payment_status', 'unpaid')->sum('total'),
            'partial_amount'  => (float) (clone $query)->where('payment_status', 'partial')->sum('total'),
        ];
    }

    public function bySupplier(array $filters): Collection
    {
        return Purchase::query()
            ->select('supplier_id', DB::raw('COUNT(*) as count'), DB::raw('SUM(total) as total_amount'))
            ->with('supplier:id,name,avg_rating')
            ->whereIn('status', ['received', 'partial'])
            ->when($filters['from'] ?? null, fn($q, $v) => $q->whereDate('date', '>=', $v))
            ->when($filters['to'] ?? null, fn($q, $v) => $q->whereDate('date', '<=', $v))
            ->when($filters['store_id'] ?? null, fn($q, $v) => $q->where('store_id', $v))
            ->groupBy('supplier_id')
            ->orderByDesc('total_amount')
            ->get()
            ->map(fn (Purchase $fila): array => [
                'supplier_id'  => $fila->supplier_id === null ? null : (int) $fila->supplier_id,
                'count'        => (int) $fila->getAttribute('count'),
                'total_amount' => (float) $fila->getAttribute('total_amount'),
                'supplier'     => $fila->supplier === null ? null : [
                    'name'       => (string) $fila->supplier->name,
                    'avg_rating' => $fila->supplier->avg_rating,
                ],
            ])
            ->values();
    }

    public function byProduct(array $filters): Collection
    {
        return PurchaseItem::query()
            ->select(
                'product_id',
                DB::raw('SUM(quantity) as total_quantity'),
                DB::raw('SUM(subtotal) as total_amount'),
                DB::raw('AVG(cost) as avg_cost')
            )
            ->with('product:id,name,sku')
            ->whereHas('purchase', function ($q) use ($filters) {
                $q->whereIn('status', ['received', 'partial'])
                    ->when($filters['from'] ?? null, fn($q2, $v) => $q2->whereDate('date', '>=', $v))
                    ->when($filters['to'] ?? null, fn($q2, $v) => $q2->whereDate('date', '<=', $v))
                    ->when($filters['supplier_id'] ?? null, fn($q2, $v) => $q2->where('supplier_id', $v))
                    ->when($filters['store_id'] ?? null, fn($q2, $v) => $q2->where('store_id', $v));
            })
            ->groupBy('product_id')
            ->orderByDesc('total_amount')
            ->limit(50)
            ->get()
            ->map(fn (PurchaseItem $fila): array => [
                'product_id'     => (int) $fila->product_id,
                'total_quantity' => (float) $fila->getAttribute('total_quantity'),
                'total_amount'   => (float) $fila->getAttribute('total_amount'),
                'avg_cost'       => (float) $fila->getAttribute('avg_cost'),
                'product'        => $fila->product === null ? null : [
                    'name' => (string) $fila->product->name,
                    'sku'  => $fila->product->sku,
                ],
            ])
            ->values();
    }

    public function costEvolution(array $filters): Collection
    {
        $dateGroup = DB::getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', date)"
            : "DATE_FORMAT(date, '%Y-%m')";

        return Purchase::query()
            ->selectRaw("{$dateGroup} as month, SUM(total) as total_amount, COUNT(*) as count, SUM(tax) as total_tax")
            ->whereIn('status', ['received', 'partial'])
            ->when($filters['from'] ?? null, fn($q, $v) => $q->whereDate('date', '>=', $v))
            ->when($filters['to'] ?? null, fn($q, $v) => $q->whereDate('date', '<=', $v))
            ->when($filters['supplier_id'] ?? null, fn($q, $v) => $q->where('supplier_id', $v))
            ->when($filters['store_id'] ?? null, fn($q, $v) => $q->where('store_id', $v))
            ->groupByRaw($dateGroup)
            ->orderBy('month')
            ->get()
            ->map(fn (Purchase $fila): array => [
                'month'        => (string) $fila->getAttribute('month'),
                'total_amount' => (float) $fila->getAttribute('total_amount'),
                'count'        => (int) $fila->getAttribute('count'),
                'total_tax'    => (float) $fila->getAttribute('total_tax'),
            ])
            ->values();
    }

    public function supplierComplianceReport(array $filters): Collection
    {
        return Purchase::query()
            ->select(
                'purchases.supplier_id',
                DB::raw('COUNT(*) as total_orders'),
                DB::raw("SUM(CASE WHEN purchases.status = 'received' THEN 1 ELSE 0 END) as completed_orders"),
                DB::raw("SUM(CASE WHEN purchases.payment_status = 'paid' THEN 1 ELSE 0 END) as paid_orders"),
                DB::raw('SUM(purchases.total) as total_amount'),
                DB::raw("SUM(CASE WHEN purchases.payment_status = 'unpaid' THEN purchases.total ELSE 0 END) as unpaid_amount"),
                // Entregas puntuales: recibidas en o antes de la fecha esperada de la OC.
                DB::raw('SUM(CASE WHEN purchase_orders.expected_date IS NOT NULL
                                   AND purchases.received_at IS NOT NULL THEN 1 ELSE 0 END) as measurable_deliveries'),
                DB::raw('SUM(CASE WHEN purchase_orders.expected_date IS NOT NULL
                                   AND purchases.received_at IS NOT NULL
                                   AND purchases.received_at <= purchase_orders.expected_date
                              THEN 1 ELSE 0 END) as on_time_deliveries')
            )
            ->leftJoin('purchase_orders', 'purchase_orders.id', '=', 'purchases.purchase_order_id')
            ->with('supplier:id,name,avg_rating,payment_terms,lead_time_days')
            // Una compra cancelada no dice nada del desempeño del proveedor.
            ->where('purchases.status', '!=', 'cancelled')
            ->when($filters['from'] ?? null, fn($q, $v) => $q->whereDate('purchases.date', '>=', $v))
            ->when($filters['to'] ?? null, fn($q, $v) => $q->whereDate('purchases.date', '<=', $v))
            ->when($filters['store_id'] ?? null, fn($q, $v) => $q->where('purchases.store_id', $v))
            ->whereNotNull('purchases.supplier_id')
            ->groupBy('purchases.supplier_id')
            ->orderByDesc('total_amount')
            ->get()
            ->map(fn (Purchase $fila): array => [
                'supplier_id'           => (int) $fila->supplier_id,
                'total_orders'          => (int) $fila->getAttribute('total_orders'),
                'completed_orders'      => (int) $fila->getAttribute('completed_orders'),
                'paid_orders'           => (int) $fila->getAttribute('paid_orders'),
                'total_amount'          => (float) $fila->getAttribute('total_amount'),
                'unpaid_amount'         => (float) $fila->getAttribute('unpaid_amount'),
                'measurable_deliveries' => (int) $fila->getAttribute('measurable_deliveries'),
                'on_time_deliveries'    => (int) $fila->getAttribute('on_time_deliveries'),
                'supplier'              => $fila->supplier === null ? null : [
                    'name'           => (string) $fila->supplier->name,
                    'avg_rating'     => $fila->supplier->avg_rating,
                    'payment_terms'  => $fila->supplier->payment_terms,
                    'lead_time_days' => $fila->supplier->lead_time_days,
                ],
            ])
            ->values();
    }

    private function baseQuery(array $filters)
    {
        return Purchase::query()
            ->whereIn('status', ['received', 'partial'])
            ->when($filters['from'] ?? null, fn($q, $v) => $q->whereDate('date', '>=', $v))
            ->when($filters['to'] ?? null, fn($q, $v) => $q->whereDate('date', '<=', $v))
            ->when($filters['supplier_id'] ?? null, fn($q, $v) => $q->where('supplier_id', $v))
            ->when($filters['store_id'] ?? null, fn($q, $v) => $q->where('store_id', $v));
    }
}
