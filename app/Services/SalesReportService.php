<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SalesReportService
{
    public function summary(array $filters): array
    {
        $query = $this->baseQuery($filters);

        return [
            'total_sales'    => (clone $query)->count(),
            'total_amount'   => (clone $query)->sum('total'),
            'avg_ticket'     => (clone $query)->avg('total') ?? 0,
            'total_discount' => (clone $query)->sum('discount'),
            'total_tax'      => (clone $query)->sum('tax'),
            'cancelled'      => $this->cancelledQuery($filters)->count(),
        ];
    }

    public function byProduct(array $filters): Collection
    {
        return SaleItem::query()
            ->select(
                'product_id',
                DB::raw('SUM(quantity) as total_quantity'),
                DB::raw('SUM(subtotal) as total_amount')
            )
            ->with('product:id,name,sku')
            ->whereHas('sale', fn($q) => $this->applySaleFilters($q, $filters))
            ->groupBy('product_id')
            ->orderByDesc('total_amount')
            ->limit(50)
            ->get();
    }

    public function byCategory(array $filters): Collection
    {
        return SaleItem::query()
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->select(
                'categories.name as category',
                DB::raw('SUM(sale_items.quantity) as total_quantity'),
                DB::raw('SUM(sale_items.subtotal) as total_amount')
            )
            ->whereIn('sale_items.sale_id', $this->saleIds($filters))
            ->groupBy('categories.name')
            ->orderByDesc('total_amount')
            ->get();
    }

    public function bySeller(array $filters): Collection
    {
        return Sale::query()
            ->select('user_id', DB::raw('COUNT(*) as count'), DB::raw('SUM(total) as total_amount'))
            ->with('user:id,name')
            ->tap(fn($q) => $this->applySaleFilters($q, $filters))
            ->groupBy('user_id')
            ->orderByDesc('total_amount')
            ->get();
    }

    public function byPaymentMethod(array $filters): Collection
    {
        return Sale::query()
            ->select('payment_method', DB::raw('COUNT(*) as count'), DB::raw('SUM(total) as total_amount'))
            ->tap(fn($q) => $this->applySaleFilters($q, $filters))
            ->groupBy('payment_method')
            ->orderByDesc('total_amount')
            ->get();
    }

    public function salesEvolution(array $filters): Collection
    {
        $dateGroup = DB::getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', sales.created_at)"
            : "DATE_FORMAT(sales.created_at, '%Y-%m')";

        return Sale::query()
            ->selectRaw("{$dateGroup} as month, SUM(total) as total_amount, COUNT(*) as count")
            ->tap(fn($q) => $this->applySaleFilters($q, $filters))
            ->groupByRaw($dateGroup)
            ->orderBy('month')
            ->get();
    }

    public function topCustomers(array $filters): Collection
    {
        return Sale::query()
            ->select('customer_id', DB::raw('COUNT(*) as count'), DB::raw('SUM(total) as total_amount'))
            ->with('customer:id,name')
            ->whereNotNull('customer_id')
            ->tap(fn($q) => $this->applySaleFilters($q, $filters))
            ->groupBy('customer_id')
            ->orderByDesc('total_amount')
            ->limit(20)
            ->get();
    }

    private function baseQuery(array $filters): Builder
    {
        return Sale::query()->where('status', 'completed')
            ->tap(fn($q) => $this->applyDateAndScope($q, $filters));
    }

    private function cancelledQuery(array $filters): Builder
    {
        return Sale::query()->where('status', 'cancelled')
            ->tap(fn($q) => $this->applyDateAndScope($q, $filters));
    }

    /** Apply the full filter set (status completed + dates + store + seller). */
    private function applySaleFilters(Builder $query, array $filters): Builder
    {
        return $query->where('status', 'completed')
            ->tap(fn($q) => $this->applyDateAndScope($q, $filters));
    }

    private function applyDateAndScope(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['from'] ?? null, fn($q, $v) => $q->whereDate('sales.created_at', '>=', $v))
            ->when($filters['to'] ?? null, fn($q, $v) => $q->whereDate('sales.created_at', '<=', $v))
            ->when($filters['user_id'] ?? null, fn($q, $v) => $q->where('user_id', $v))
            ->when($filters['store_id'] ?? null, fn($q, $v) => $q->whereHas(
                'cashShift.cashRegister',
                fn($q2) => $q2->where('store_id', $v)
            ));
    }

    /** @return Collection<int> */
    private function saleIds(array $filters): Collection
    {
        return $this->baseQuery($filters)->pluck('sales.id');
    }
}
