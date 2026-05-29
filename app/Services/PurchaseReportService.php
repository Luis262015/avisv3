<?php

namespace App\Services;

use App\Models\Purchase;
use App\Models\PurchaseItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PurchaseReportService
{
    public function summary(array $filters): array
    {
        $query = $this->baseQuery($filters);

        return [
            'total_purchases' => (clone $query)->count(),
            'total_amount'    => (clone $query)->sum('total'),
            'avg_amount'      => (clone $query)->avg('total') ?? 0,
            'total_tax'       => (clone $query)->sum('tax'),
            'unpaid_amount'   => (clone $query)->where('payment_status', 'unpaid')->sum('total'),
            'partial_amount'  => (clone $query)->where('payment_status', 'partial')->sum('total'),
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
            ->get();
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
            ->get();
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
            ->get();
    }

    public function supplierComplianceReport(array $filters): Collection
    {
        return Purchase::query()
            ->select(
                'supplier_id',
                DB::raw('COUNT(*) as total_orders'),
                DB::raw("SUM(CASE WHEN status = 'received' THEN 1 ELSE 0 END) as completed_orders"),
                DB::raw("SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_orders"),
                DB::raw('SUM(total) as total_amount'),
                DB::raw("SUM(CASE WHEN payment_status = 'unpaid' THEN total ELSE 0 END) as unpaid_amount")
            )
            ->with('supplier:id,name,avg_rating,payment_terms,lead_time_days')
            ->when($filters['from'] ?? null, fn($q, $v) => $q->whereDate('date', '>=', $v))
            ->when($filters['to'] ?? null, fn($q, $v) => $q->whereDate('date', '<=', $v))
            ->whereNotNull('supplier_id')
            ->groupBy('supplier_id')
            ->orderByDesc('total_amount')
            ->get();
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
