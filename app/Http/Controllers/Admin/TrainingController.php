<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TrainingRequest;
use App\Models\Employee;
use App\Models\Training;
use Inertia\Inertia;
use Inertia\Response;

class TrainingController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/trainings/index', [
            'trainings' => Training::withCount('employees')
                ->orderByDesc('start_date')
                ->paginate(15),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/trainings/create', [
            'employees' => Employee::active()->orderBy('first_name')->get(['id', 'first_name', 'last_name']),
        ]);
    }

    public function store(TrainingRequest $request)
    {
        $data = $request->validated();

        $training = Training::create($data);
        $training->employees()->sync($data['employee_ids'] ?? []);

        return redirect()->route('admin.trainings.show', $training)->with('success', 'Capacitación creada.');
    }

    public function show(Training $training): Response
    {
        $training->load(['employees:id,first_name,last_name,position']);

        return Inertia::render('admin/trainings/show', [
            'training' => $training,
            'employees' => Employee::active()->orderBy('first_name')->get(['id', 'first_name', 'last_name']),
        ]);
    }

    public function edit(Training $training): Response
    {
        return Inertia::render('admin/trainings/edit', [
            'training'  => $training->load('employees:id'),
            'employees' => Employee::active()->orderBy('first_name')->get(['id', 'first_name', 'last_name']),
        ]);
    }

    public function update(TrainingRequest $request, Training $training)
    {
        $data = $request->validated();

        $training->update($data);
        $training->employees()->syncWithoutDetaching($data['employee_ids'] ?? []);

        return redirect()->route('admin.trainings.show', $training)->with('success', 'Capacitación actualizada.');
    }

    public function destroy(Training $training)
    {
        $training->delete();
        return redirect()->route('admin.trainings.index')->with('success', 'Capacitación eliminada.');
    }
}
