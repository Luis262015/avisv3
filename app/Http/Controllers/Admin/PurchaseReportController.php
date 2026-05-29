<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PurchaseReportRequest;
use App\Models\Store;
use App\Models\Supplier;
use App\Services\PurchaseReportService;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseReportController extends Controller
{
    public function __construct(private readonly PurchaseReportService $service) {}

    public function index(PurchaseReportRequest $request): Response
    {
        $filters = $request->validated();

        return Inertia::render('admin/purchases/reports/index', [
            'summary'       => $this->service->summary($filters),
            'bySupplier'    => $this->service->bySupplier($filters),
            'byProduct'     => $this->service->byProduct($filters),
            'costEvolution' => $this->service->costEvolution($filters),
            'compliance'    => $this->service->supplierComplianceReport($filters),
            'filters'       => $filters,
            'suppliers'     => Supplier::where('is_active', true)->get(['id', 'name']),
            'stores'        => Store::where('is_active', true)->get(['id', 'name']),
        ]);
    }
}
