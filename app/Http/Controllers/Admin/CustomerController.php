<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CustomerRequest;
use App\Models\Customer;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/customers/index', [
            'customers' => Customer::withCount(['sales', 'quotes', 'salesOrders'])
                ->latest()
                ->paginate(15),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/customers/create');
    }

    public function store(CustomerRequest $request)
    {
        Customer::create($request->validated());
        return redirect()->route('admin.customers.index')->with('success', 'Cliente creado.');
    }

    public function show(Customer $customer): Response
    {
        $customer->loadCount(['sales', 'quotes', 'salesOrders', 'returns']);

        $stats = [
            'total_sales'  => $customer->sales()->where('status', 'completed')->count(),
            'total_amount' => $customer->sales()->where('status', 'completed')->sum('total'),
        ];

        return Inertia::render('admin/customers/show', [
            'customer' => $customer,
            'stats'    => $stats,
            'sales'    => $customer->sales()->latest()->take(20)->get(['id', 'folio', 'total', 'status', 'created_at']),
            'quotes'   => $customer->quotes()->latest()->take(20)->get(['id', 'folio', 'total', 'status', 'date']),
        ]);
    }

    public function edit(Customer $customer): Response
    {
        return Inertia::render('admin/customers/edit', compact('customer'));
    }

    public function update(CustomerRequest $request, Customer $customer)
    {
        $customer->update($request->validated());
        return redirect()->route('admin.customers.index')->with('success', 'Cliente actualizado.');
    }

    public function destroy(Customer $customer)
    {
        $customer->delete();
        return redirect()->route('admin.customers.index')->with('success', 'Cliente eliminado.');
    }
}
