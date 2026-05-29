<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PromotionRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use App\Services\PromotionService;
use Inertia\Inertia;
use Inertia\Response;

class PromotionController extends Controller
{
    public function __construct(private readonly PromotionService $service) {}

    public function index(): Response
    {
        return Inertia::render('admin/promotions/index', [
            'promotions' => Promotion::withCount(['products', 'categories', 'sales', 'comboItems'])->latest()->paginate(20),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/promotions/create', [
            'products'   => Product::where('status', 'active')->get(['id', 'name', 'sku', 'price']),
            'categories' => Category::get(['id', 'name']),
        ]);
    }

    public function store(PromotionRequest $request)
    {
        $this->service->create($request->validated());
        return redirect()->route('admin.promotions.index')->with('success', 'Promoción creada.');
    }

    public function edit(Promotion $promotion): Response
    {
        return Inertia::render('admin/promotions/edit', [
            'promotion'  => $promotion->load(['products:id', 'categories:id', 'comboItems']),
            'products'   => Product::where('status', 'active')->get(['id', 'name', 'sku', 'price']),
            'categories' => Category::get(['id', 'name']),
        ]);
    }

    public function update(PromotionRequest $request, Promotion $promotion)
    {
        $this->service->update($promotion, $request->validated());
        return redirect()->route('admin.promotions.index')->with('success', 'Promoción actualizada.');
    }

    public function toggle(Promotion $promotion)
    {
        $this->service->toggle($promotion);
        return back()->with('success', 'Estado de la promoción actualizado.');
    }

    public function destroy(Promotion $promotion)
    {
        $promotion->delete();
        return redirect()->route('admin.promotions.index')->with('success', 'Promoción eliminada.');
    }
}
