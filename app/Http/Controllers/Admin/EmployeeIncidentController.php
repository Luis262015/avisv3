<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EmployeeIncidentRequest;
use App\Models\Employee;
use App\Models\EmployeeIncident;

class EmployeeIncidentController extends Controller
{
    public function store(EmployeeIncidentRequest $request, Employee $employee)
    {
        $employee->incidents()->create([
            ...$request->validated(),
            'registered_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Registro laboral agregado.');
    }

    public function destroy(Employee $employee, EmployeeIncident $incident)
    {
        abort_unless($incident->employee_id === $employee->id, 404);

        $incident->delete();

        return back()->with('success', 'Registro eliminado.');
    }
}
