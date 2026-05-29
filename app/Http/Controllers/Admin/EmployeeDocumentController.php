<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EmployeeDocumentRequest;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeDocumentController extends Controller
{
    public function store(EmployeeDocumentRequest $request, Employee $employee)
    {
        $data = $request->validated();

        if ($request->hasFile('file')) {
            $data['file_path'] = $request->file('file')->store("employees/{$employee->id}/documents", 'private');
        }

        unset($data['file']);
        $employee->documents()->create($data);

        return back()->with('success', 'Documento agregado.');
    }

    public function download(Employee $employee, EmployeeDocument $document): StreamedResponse
    {
        abort_unless($document->employee_id === $employee->id, 404);
        abort_unless($document->file_path && Storage::disk('private')->exists($document->file_path), 404);

        return Storage::disk('private')->download($document->file_path, $document->name);
    }

    public function destroy(Employee $employee, EmployeeDocument $document)
    {
        abort_unless($document->employee_id === $employee->id, 404);

        if ($document->file_path) {
            Storage::disk('private')->delete($document->file_path);
        }

        $document->delete();

        return back()->with('success', 'Documento eliminado.');
    }
}
