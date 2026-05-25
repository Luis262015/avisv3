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
            'suppliers' => Supplier::withCount('purchases')->latest()->paginate(15),
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
