<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SalesReportRequest;
use App\Models\Store;
use App\Models\User;
use App\Services\SalesReportService;
use Inertia\Inertia;
use Inertia\Response;

class SalesReportController extends Controller
{
    public function __construct(private readonly SalesReportService $service) {}

    public function index(SalesReportRequest $request): Response
    {
        $filters = $request->validated();

        return Inertia::render('admin/sales/reports/index', [
            'summary'        => $this->service->summary($filters),
            'byProduct'      => $this->service->byProduct($filters),
            'byCategory'     => $this->service->byCategory($filters),
            'bySeller'       => $this->service->bySeller($filters),
            'byPaymentMethod' => $this->service->byPaymentMethod($filters),
            'salesEvolution' => $this->service->salesEvolution($filters),
            'topCustomers'   => $this->service->topCustomers($filters),
            'filters'        => $filters,
            'stores'         => Store::where('is_active', true)->get(['id', 'name']),
            'sellers'        => User::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
