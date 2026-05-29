<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SupplierEvaluationRequest;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\SupplierEvaluation;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class SupplierEvaluationController extends Controller
{
    public function index(Supplier $supplier): Response
    {
        return Inertia::render('admin/suppliers/evaluations/index', [
            'supplier'    => $supplier,
            'evaluations' => SupplierEvaluation::where('supplier_id', $supplier->id)
                ->with('user:id,name', 'purchase:id,folio')
                ->latest('evaluated_at')
                ->paginate(15),
        ]);
    }

    public function create(Supplier $supplier): Response
    {
        return Inertia::render('admin/suppliers/evaluations/create', [
            'supplier' => $supplier,
            'purchases' => Purchase::where('supplier_id', $supplier->id)
                ->whereIn('status', ['received', 'partial'])
                ->orderByDesc('date')
                ->get(['id', 'folio', 'date', 'total']),
        ]);
    }

    public function store(SupplierEvaluationRequest $request, Supplier $supplier)
    {
        $supplier->evaluations()->create(array_merge(
            $request->validated(),
            ['user_id' => Auth::id()]
        ));

        $supplier->recalculateRating();

        return redirect()->route('admin.suppliers.show', $supplier)
            ->with('success', 'Evaluación registrada. Calificación del proveedor actualizada.');
    }

    public function destroy(Supplier $supplier, SupplierEvaluation $evaluation)
    {
        $evaluation->delete();
        $supplier->recalculateRating();

        return redirect()->route('admin.suppliers.show', $supplier)
            ->with('success', 'Evaluación eliminada.');
    }
}
