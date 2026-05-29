<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaleReturnRequest;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Services\ReturnService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SaleReturnController extends Controller
{
    public function __construct(private readonly ReturnService $service) {}

    public function index(): Response
    {
        return Inertia::render('admin/returns/index', [
            'returns' => SaleReturn::with(['sale:id,folio', 'customer:id,name', 'user:id,name'])
                ->latest()
                ->paginate(20),
        ]);
    }

    public function create(Request $request): Response
    {
        $sale = null;
        if ($request->filled('sale_id')) {
            $sale = Sale::with(['items.product:id,name,sku', 'customer:id,name'])
                ->find($request->sale_id);
        }

        return Inertia::render('admin/returns/create', [
            'sale'         => $sale,
            'recentSales'  => Sale::where('status', 'completed')
                ->with('customer:id,name')
                ->latest()
                ->take(50)
                ->get(['id', 'folio', 'total', 'customer_id', 'created_at']),
        ]);
    }

    public function store(SaleReturnRequest $request)
    {
        $sale = Sale::findOrFail($request->sale_id);

        try {
            $return = $this->service->create($sale, $request->safe()->except('items'), $request->items);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return redirect()->route('admin.returns.show', $return)->with('success', 'Devolución registrada.');
    }

    public function show(SaleReturn $return): Response
    {
        $return->load(['sale:id,folio', 'customer', 'user:id,name', 'items.product:id,name,sku']);
        return Inertia::render('admin/returns/show', ['return' => $return]);
    }

    public function approve(SaleReturn $return)
    {
        return $this->transition(fn() => $this->service->approve($return), $return, 'Devolución aprobada.');
    }

    public function complete(SaleReturn $return)
    {
        return $this->transition(fn() => $this->service->complete($return), $return, 'Devolución completada.');
    }

    public function reject(SaleReturn $return)
    {
        return $this->transition(fn() => $this->service->reject($return), $return, 'Devolución rechazada.');
    }

    private function transition(callable $action, SaleReturn $return, string $message)
    {
        try {
            $action();
        } catch (\RuntimeException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }
        return redirect()->route('admin.returns.show', $return)->with('success', $message);
    }
}
