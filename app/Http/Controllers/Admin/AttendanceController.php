<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AttendanceRequest;
use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    public function index(Request $request): Response
    {
        $date = $request->date('date')?->toDateString() ?? now()->toDateString();

        $records = Attendance::query()
            ->with('employee:id,first_name,last_name,position')
            ->whereDate('date', $date)
            ->when($request->integer('employee_id'), fn ($q, $id) => $q->where('employee_id', $id))
            ->get()
            ->keyBy('employee_id');

        return Inertia::render('admin/attendances/index', [
            'date'      => $date,
            'employees' => Employee::active()
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name', 'position'])
                ->map(fn ($e) => [
                    'id'         => $e->id,
                    'name'       => $e->full_name,
                    'position'   => $e->position,
                    'attendance' => $records->get($e->id),
                ]),
        ]);
    }

    public function store(AttendanceRequest $request)
    {
        $data = $request->validated();

        Attendance::updateOrCreate(
            ['employee_id' => $data['employee_id'], 'date' => $data['date']],
            $data
        );

        return back()->with('success', 'Asistencia registrada.');
    }

    public function destroy(Attendance $attendance)
    {
        $attendance->delete();
        return back()->with('success', 'Registro eliminado.');
    }
}
