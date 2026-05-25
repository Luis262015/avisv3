<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CashRegisterRequest;
use App\Models\CashRegister;
use App\Models\Store;
use Inertia\Inertia;
use Inertia\Response;

class CashRegisterController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/cash-registers/index', [
            'registers' => CashRegister::with('store')->latest()->paginate(15),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/cash-registers/create', [
            'stores' => Store::where('is_active', true)->get(['id', 'name']),
        ]);
    }

    public function store(CashRegisterRequest $request)
    {
        CashRegister::create($request->validated());
        return redirect()->route('admin.cash-registers.index')->with('success', 'Caja creada.');
    }

    public function edit(CashRegister $cashRegister): Response
    {
        return Inertia::render('admin/cash-registers/edit', [
            'register' => $cashRegister,
            'stores'   => Store::where('is_active', true)->get(['id', 'name']),
        ]);
    }

    public function update(CashRegisterRequest $request, CashRegister $cashRegister)
    {
        $cashRegister->update($request->validated());
        return redirect()->route('admin.cash-registers.index')->with('success', 'Caja actualizada.');
    }

    public function destroy(CashRegister $cashRegister)
    {
        $cashRegister->delete();
        return redirect()->route('admin.cash-registers.index')->with('success', 'Caja eliminada.');
    }
}
