<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TrainingParticipantRequest;
use App\Models\Employee;
use App\Models\Training;
use Illuminate\Http\Request;

class TrainingParticipantController extends Controller
{
    public function store(Request $request, Training $training)
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
        ]);

        $training->employees()->syncWithoutDetaching([$data['employee_id'] => ['status' => 'enrolled']]);

        return back()->with('success', 'Participante agregado.');
    }

    public function update(TrainingParticipantRequest $request, Training $training, Employee $employee)
    {
        $training->employees()->updateExistingPivot($employee->id, $request->validated());

        return back()->with('success', 'Participación actualizada.');
    }

    public function destroy(Training $training, Employee $employee)
    {
        $training->employees()->detach($employee->id);

        return back()->with('success', 'Participante removido.');
    }
}
