<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/products/index', [
            'products' => Product::with(['category', 'brand', 'primaryImage'])
                ->withTrashed()
                ->latest()
                ->paginate(20),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/products/create', $this->formData());
    }

    public function store(ProductRequest $request)
    {
        $data = $request->safe()->except(['tags', 'images']);
        $product = Product::create($data);

        if ($request->filled('tags')) {
            $product->tags()->sync($request->tags);
        }

        $this->handleImages($request, $product);

        return redirect()->route('admin.products.index')->with('success', 'Producto creado.');
    }

    public function edit(Product $product): Response
    {
        $product->load(['tags', 'images', 'category', 'brand']);
        return Inertia::render('admin/products/edit', [
            ...$this->formData(),
            'product' => $product,
        ]);
    }

    public function update(ProductRequest $request, Product $product)
    {
        $data = $request->safe()->except(['tags', 'images']);
        $product->update($data);

        if ($request->has('tags')) {
            $product->tags()->sync($request->tags ?? []);
        }

        $this->handleImages($request, $product);

        return redirect()->route('admin.products.index')->with('success', 'Producto actualizado.');
    }

    public function destroy(Product $product)
    {
        $product->delete();
        return redirect()->route('admin.products.index')->with('success', 'Producto eliminado.');
    }

    public function destroyImage(ProductImage $image)
    {
        Storage::disk('public')->delete($image->path);
        $image->delete();
        return back()->with('success', 'Imagen eliminada.');
    }

    public function setPrimaryImage(ProductImage $image)
    {
        ProductImage::where('product_id', $image->product_id)->update(['is_primary' => false]);
        $image->update(['is_primary' => true]);
        return back()->with('success', 'Imagen principal actualizada.');
    }

    private function formData(): array
    {
        return [
            'categories' => Category::where('is_active', true)->get(['id', 'name']),
            'brands'     => Brand::where('is_active', true)->get(['id', 'name']),
            'tags'       => Tag::all(['id', 'name']),
        ];
    }

    private function handleImages(Request $request, Product $product): void
    {
        if (! $request->hasFile('images')) {
            return;
        }

        $hasPrimary = $product->images()->where('is_primary', true)->exists();

        foreach ($request->file('images') as $index => $file) {
            $path = $file->store("products/{$product->id}", 'public');
            $product->images()->create([
                'path'       => $path,
                'is_primary' => ! $hasPrimary && $index === 0,
                'sort_order' => $product->images()->count() + $index,
            ]);
            if (! $hasPrimary && $index === 0) {
                $hasPrimary = true;
            }
        }
    }
}
