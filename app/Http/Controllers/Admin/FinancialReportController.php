<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FinancialReportRequest;
use App\Models\Store;
use App\Services\FinancialReportService;
use Inertia\Inertia;
use Inertia\Response;

class FinancialReportController extends Controller
{
    public function __construct(private readonly FinancialReportService $service) {}

    public function index(FinancialReportRequest $request): Response
    {
        $filters = $request->validated();

        [$from, $to, $period] = $this->service->resolveRange($filters);

        return Inertia::render('admin/finances/reports/index', [
            'summary'           => $this->service->summary($filters),
            'receivables'       => $this->service->receivables($filters),
            'payables'          => $this->service->payables($filters),
            'expensesByCategory' => $this->service->expensesByCategory($filters),
            'incomesByCategory' => $this->service->incomesByCategory($filters),
            'evolution'         => $this->service->evolution($filters),
            'filters'           => [
                'period' => $period,
                'from'   => $from->toDateString(),
                'to'     => $to->toDateString(),
                'store_id' => $filters['store_id'] ?? '',
            ],
            'stores'            => Store::where('is_active', true)->get(['id', 'name']),
        ]);
    }
}
