<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\HrReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HrReportController extends Controller
{
    public function __construct(private readonly HrReportService $service) {}

    public function index(Request $request): Response
    {
        $year = $request->integer('year') ?: (int) now()->year;

        return Inertia::render('admin/hr-reports/index', [
            'year'                  => $year,
            'summary'               => $this->service->summary(),
            'headcountByDepartment' => $this->service->headcountByDepartment(),
            'contractDistribution'  => $this->service->contractDistribution(),
            'payrollEvolution'      => $this->service->payrollEvolution($year),
            'trainingSummary'       => $this->service->trainingSummary($year),
            'expiringDocuments'     => $this->service->expiringDocuments(),
            'endingContracts'       => $this->service->endingContracts(),
        ]);
    }
}
