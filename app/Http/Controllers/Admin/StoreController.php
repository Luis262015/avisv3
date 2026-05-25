<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRequest;
use App\Models\Store;
use Inertia\Inertia;
use Inertia\Response;

class StoreController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/stores/index', [
            'stores' => Store::latest()->paginate(15),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/stores/create');
    }

    public function store(StoreRequest $request)
    {
        Store::create($request->validated());
        return redirect()->route('admin.stores.index')->with('success', 'Tienda creada.');
    }

    public function edit(Store $store): Response
    {
        return Inertia::render('admin/stores/edit', compact('store'));
    }

    public function update(StoreRequest $request, Store $store)
    {
        $store->update($request->validated());
        return redirect()->route('admin.stores.index')->with('success', 'Tienda actualizada.');
    }

    public function destroy(Store $store)
    {
        $store->delete();
        return redirect()->route('admin.stores.index')->with('success', 'Tienda eliminada.');
    }
}
