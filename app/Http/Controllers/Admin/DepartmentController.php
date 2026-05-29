<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DepartmentRequest;
use App\Models\Department;
use App\Models\Employee;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/departments/index', [
            'departments' => Department::withCount('employees')
                ->with('manager:id,first_name,last_name')
                ->orderBy('name')
                ->get(),
            'employees' => Employee::active()->orderBy('first_name')->get(['id', 'first_name', 'last_name']),
        ]);
    }

    public function store(DepartmentRequest $request)
    {
        Department::create($request->validated());
        return back()->with('success', 'Área creada.');
    }

    public function update(DepartmentRequest $request, Department $department)
    {
        $department->update($request->validated());
        return back()->with('success', 'Área actualizada.');
    }

    public function destroy(Department $department)
    {
        $department->delete();
        return back()->with('success', 'Área eliminada.');
    }
}
