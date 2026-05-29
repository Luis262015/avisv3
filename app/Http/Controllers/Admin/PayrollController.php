<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PayrollItemRequest;
use App\Http\Requests\Admin\PayrollRequest;
use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Services\PayrollService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PayrollController extends Controller
{
    public function __construct(private readonly PayrollService $service) {}

    public function index(): Response
    {
        return Inertia::render('admin/payrolls/index', [
            'payrolls' => Payroll::withCount('items')
                ->with('user:id,name')
                ->orderByDesc('period_year')
                ->orderByDesc('period_month')
                ->paginate(15),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/payrolls/create');
    }

    public function store(PayrollRequest $request): RedirectResponse
    {
        $payroll = $this->service->generate($request->validated(), $request->user()->id);

        return redirect()->route('admin.payrolls.show', $payroll)
            ->with('success', 'Planilla generada.');
    }

    public function show(Payroll $payroll): Response
    {
        $payroll->load([
            'user:id,name',
            'items' => fn ($q) => $q->with('employee:id,first_name,last_name,position,employee_code')
                ->join('employees', 'employees.id', '=', 'payroll_items.employee_id')
                ->orderBy('employees.first_name')
                ->select('payroll_items.*'),
        ]);

        return Inertia::render('admin/payrolls/show', [
            'payroll' => $payroll,
        ]);
    }

    public function updateItem(PayrollItemRequest $request, Payroll $payroll, PayrollItem $item): RedirectResponse
    {
        abort_unless($item->payroll_id === $payroll->id, 404);

        if ($payroll->status !== 'draft') {
            return back()->with('error', 'Solo se pueden editar planillas en borrador.');
        }

        $this->service->recalculateItem($item, $request->validated());

        return back()->with('success', 'Boleta actualizada.');
    }

    public function approve(Payroll $payroll): RedirectResponse
    {
        if ($payroll->status !== 'draft') {
            return back()->with('error', 'La planilla ya fue procesada.');
        }

        $payroll->update(['status' => 'approved']);

        return back()->with('success', 'Planilla aprobada.');
    }

    public function markPaid(Payroll $payroll): RedirectResponse
    {
        if ($payroll->status !== 'approved') {
            return back()->with('error', 'Debe aprobar la planilla antes de marcarla como pagada.');
        }

        $payroll->update([
            'status'   => 'paid',
            'pay_date' => $payroll->pay_date ?? now()->toDateString(),
        ]);

        return back()->with('success', 'Planilla marcada como pagada.');
    }

    public function destroy(Payroll $payroll): RedirectResponse
    {
        if ($payroll->status === 'paid') {
            return back()->with('error', 'No se puede eliminar una planilla pagada.');
        }

        $payroll->delete();

        return redirect()->route('admin.payrolls.index')->with('success', 'Planilla eliminada.');
    }
}
