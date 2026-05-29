<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\WarrantyRequest;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Warranty;
use App\Services\WarrantyService;
use Inertia\Inertia;
use Inertia\Response;

class WarrantyController extends Controller
{
    public function __construct(private readonly WarrantyService $service) {}

    public function index(): Response
    {
        return Inertia::render('admin/warranties/index', [
            'warranties' => Warranty::with(['product:id,name', 'customer:id,name'])
                ->withCount('claims')
                ->latest()
                ->paginate(20),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/warranties/create', [
            'products'  => Product::where('status', 'active')->get(['id', 'name', 'sku']),
            'customers' => Customer::active()->get(['id', 'name']),
            'sales'     => Sale::where('status', 'completed')->latest()->take(50)->get(['id', 'folio']),
        ]);
    }

    public function store(WarrantyRequest $request)
    {
        $warranty = $this->service->create($request->validated());
        return redirect()->route('admin.warranties.show', $warranty)->with('success', 'Garantía registrada.');
    }

    public function show(Warranty $warranty): Response
    {
        $warranty->load(['product:id,name,sku', 'customer', 'sale:id,folio', 'claims.user:id,name']);
        return Inertia::render('admin/warranties/show', ['warranty' => $warranty]);
    }

    public function edit(Warranty $warranty): Response
    {
        return Inertia::render('admin/warranties/edit', [
            'warranty'  => $warranty,
            'products'  => Product::where('status', 'active')->get(['id', 'name', 'sku']),
            'customers' => Customer::active()->get(['id', 'name']),
        ]);
    }

    public function update(WarrantyRequest $request, Warranty $warranty)
    {
        $this->service->update($warranty, $request->validated());
        return redirect()->route('admin.warranties.show', $warranty)->with('success', 'Garantía actualizada.');
    }

    public function void(Warranty $warranty)
    {
        $this->service->void($warranty);
        return redirect()->route('admin.warranties.show', $warranty)->with('success', 'Garantía anulada.');
    }

    public function destroy(Warranty $warranty)
    {
        $warranty->delete();
        return redirect()->route('admin.warranties.index')->with('success', 'Garantía eliminada.');
    }
}
