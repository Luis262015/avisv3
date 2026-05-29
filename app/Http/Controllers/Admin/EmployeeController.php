<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EmployeeRequest;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function index(Request $request): Response
    {
        $employees = Employee::query()
            ->with('department:id,name')
            ->when($request->string('search')->toString(), function ($q, $search) {
                $q->where(fn ($w) => $w
                    ->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('employee_code', 'like', "%{$search}%")
                    ->orWhere('position', 'like', "%{$search}%"));
            })
            ->when($request->string('status')->toString(), fn ($q, $status) => $q->where('status', $status))
            ->when($request->integer('department_id'), fn ($q, $id) => $q->where('department_id', $id))
            ->orderBy('first_name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/employees/index', [
            'employees'   => $employees,
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'filters'     => $request->only(['search', 'status', 'department_id']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/employees/create', [
            'departments' => Department::active()->orderBy('name')->get(['id', 'name']),
            'users'       => User::orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function store(EmployeeRequest $request)
    {
        Employee::create($request->validated());
        return redirect()->route('admin.employees.index')->with('success', 'Empleado registrado.');
    }

    public function show(Employee $employee): Response
    {
        $employee->load([
            'department:id,name',
            'user:id,name,email',
            'documents' => fn ($q) => $q->latest('issued_at'),
            'incidents' => fn ($q) => $q->latest('date'),
            'leaveRequests' => fn ($q) => $q->latest('start_date')->take(20),
            'trainings' => fn ($q) => $q->latest('start_date'),
            'payrollItems' => fn ($q) => $q->with('payroll:id,label,status')->latest()->take(12),
            'attendances' => fn ($q) => $q->latest('date')->take(30),
        ]);

        return Inertia::render('admin/employees/show', [
            'employee' => $employee,
            'stats'    => [
                'years_of_service' => $employee->yearsOfService(),
                'documents'        => $employee->documents->count(),
                'incidents'        => $employee->incidents->count(),
                'trainings'        => $employee->trainings->count(),
            ],
        ]);
    }

    public function edit(Employee $employee): Response
    {
        return Inertia::render('admin/employees/edit', [
            'employee'    => $employee,
            'departments' => Department::active()->orderBy('name')->get(['id', 'name']),
            'users'       => User::orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function update(EmployeeRequest $request, Employee $employee)
    {
        $employee->update($request->validated());
        return redirect()->route('admin.employees.index')->with('success', 'Empleado actualizado.');
    }

    public function destroy(Employee $employee)
    {
        $employee->delete();
        return redirect()->route('admin.employees.index')->with('success', 'Empleado eliminado.');
    }
}
