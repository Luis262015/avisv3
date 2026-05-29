<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LeaveRequestRequest;
use App\Models\Employee;
use App\Models\LeaveRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class LeaveRequestController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('admin/leave-requests/index', [
            'leaves' => LeaveRequest::query()
                ->with(['employee:id,first_name,last_name', 'reviewer:id,name'])
                ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
                ->latest('start_date')
                ->paginate(15)
                ->withQueryString(),
            'employees' => Employee::active()->orderBy('first_name')->get(['id', 'first_name', 'last_name']),
            'filters'   => $request->only(['status']),
        ]);
    }

    public function store(LeaveRequestRequest $request)
    {
        $data  = $request->validated();
        $start = Carbon::parse($data['start_date']);
        $end   = Carbon::parse($data['end_date']);

        LeaveRequest::create([
            ...$data,
            'days'   => $start->diffInDays($end) + 1,
            'status' => 'pending',
        ]);

        return back()->with('success', 'Solicitud de ausencia registrada.');
    }

    public function approve(Request $request, LeaveRequest $leaveRequest)
    {
        $leaveRequest->update([
            'status'       => 'approved',
            'reviewed_by'  => $request->user()->id,
            'reviewed_at'  => now(),
            'review_notes' => $request->input('review_notes'),
        ]);

        return back()->with('success', 'Ausencia aprobada.');
    }

    public function reject(Request $request, LeaveRequest $leaveRequest)
    {
        $leaveRequest->update([
            'status'       => 'rejected',
            'reviewed_by'  => $request->user()->id,
            'reviewed_at'  => now(),
            'review_notes' => $request->input('review_notes'),
        ]);

        return back()->with('success', 'Ausencia rechazada.');
    }

    public function destroy(LeaveRequest $leaveRequest)
    {
        $leaveRequest->delete();
        return back()->with('success', 'Solicitud eliminada.');
    }
}
