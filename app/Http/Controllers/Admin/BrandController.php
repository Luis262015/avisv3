<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BrandRequest;
use App\Models\Brand;
use Inertia\Inertia;
use Inertia\Response;

class BrandController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/brands/index', [
            'brands' => Brand::withCount('products')->latest()->paginate(15),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/brands/create');
    }

    public function store(BrandRequest $request)
    {
        Brand::create($request->validated());
        return redirect()->route('admin.brands.index')->with('success', 'Marca creada.');
    }

    public function edit(Brand $brand): Response
    {
        return Inertia::render('admin/brands/edit', compact('brand'));
    }

    public function update(BrandRequest $request, Brand $brand)
    {
        $brand->update($request->validated());
        return redirect()->route('admin.brands.index')->with('success', 'Marca actualizada.');
    }

    public function destroy(Brand $brand)
    {
        $brand->delete();
        return redirect()->route('admin.brands.index')->with('success', 'Marca eliminada.');
    }
}
