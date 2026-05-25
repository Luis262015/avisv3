<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryRequest;
use App\Models\Category;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/categories/index', [
            'categories' => Category::with('parent')->withCount('products')->latest()->paginate(15),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/categories/create', [
            'parents' => Category::where('is_active', true)->get(['id', 'name']),
        ]);
    }

    public function store(CategoryRequest $request)
    {
        Category::create($request->validated());
        return redirect()->route('admin.categories.index')->with('success', 'Categoría creada.');
    }

    public function edit(Category $category): Response
    {
        return Inertia::render('admin/categories/edit', [
            'category' => $category,
            'parents'  => Category::where('is_active', true)->where('id', '!=', $category->id)->get(['id', 'name']),
        ]);
    }

    public function update(CategoryRequest $request, Category $category)
    {
        $category->update($request->validated());
        return redirect()->route('admin.categories.index')->with('success', 'Categoría actualizada.');
    }

    public function destroy(Category $category)
    {
        $category->delete();
        return redirect()->route('admin.categories.index')->with('success', 'Categoría eliminada.');
    }
}
