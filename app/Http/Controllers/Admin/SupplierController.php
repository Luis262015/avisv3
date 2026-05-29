<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SupplierRequest;
use App\Models\Supplier;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/suppliers/index', [
            'suppliers' => Supplier::withCount('purchases')
                ->withAvg('evaluations', 'overall_score')
                ->latest()
                ->paginate(15),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/suppliers/create');
    }

    public function store(SupplierRequest $request)
    {
        Supplier::create($request->validated());
        return redirect()->route('admin.suppliers.index')->with('success', 'Proveedor creado.');
    }

    public function show(Supplier $supplier): Response
    {
        $supplier->load([
            'evaluations.user',
            'evaluations.purchase:id,folio',
        ]);

        $stats = [
            'total_purchases'  => $supplier->purchases()->count(),
            'total_amount'     => $supplier->purchases()->whereIn('status', ['received', 'partial'])->sum('total'),
            'pending_payables' => $supplier->purchases()
                ->whereHas('payable', fn($q) => $q->whereIn('status', ['pending', 'partial']))
                ->sum('total'),
        ];

        return Inertia::render('admin/suppliers/show', compact('supplier', 'stats'));
    }

    public function edit(Supplier $supplier): Response
    {
        return Inertia::render('admin/suppliers/edit', compact('supplier'));
    }

    public function update(SupplierRequest $request, Supplier $supplier)
    {
        $supplier->update($request->validated());
        return redirect()->route('admin.suppliers.index')->with('success', 'Proveedor actualizado.');
    }

    public function destroy(Supplier $supplier)
    {
        $supplier->delete();
        return redirect()->route('admin.suppliers.index')->with('success', 'Proveedor eliminado.');
    }
}
