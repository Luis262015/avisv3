<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\QuoteRequest;
use App\Models\CashShift;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Quote;
use App\Services\QuoteService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class QuoteController extends Controller
{
    public function __construct(private readonly QuoteService $service) {}

    public function index(): Response
    {
        return Inertia::render('admin/quotes/index', [
            'quotes' => Quote::with(['customer', 'user'])->latest()->paginate(20),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/quotes/create', [
            'customers' => Customer::active()->get(['id', 'name', 'document_type', 'document_number']),
            'products'  => Product::where('status', 'active')->get(['id', 'name', 'sku', 'price']),
        ]);
    }

    public function store(QuoteRequest $request)
    {
        $quote = $this->service->create($request->safe()->except('items'), $request->items);
        return redirect()->route('admin.quotes.show', $quote)->with('success', 'Cotización creada.');
    }

    public function show(Quote $quote): Response
    {
        $quote->load(['customer', 'user', 'items.product', 'sale']);

        return Inertia::render('admin/quotes/show', [
            'quote'      => $quote,
            'isExpired'  => $quote->isExpired(),
            'openShifts' => CashShift::where('status', 'open')->with('cashRegister.store')->get(),
        ]);
    }

    public function edit(Quote $quote): Response
    {
        if (! $quote->isEditable()) {
            return redirect()->route('admin.quotes.show', $quote)
                ->withErrors(['status' => 'Esta cotización no puede editarse.']);
        }

        return Inertia::render('admin/quotes/edit', [
            'quote'     => $quote->load(['customer', 'items.product']),
            'customers' => Customer::active()->get(['id', 'name', 'document_type', 'document_number']),
            'products'  => Product::where('status', 'active')->get(['id', 'name', 'sku', 'price']),
        ]);
    }

    public function update(QuoteRequest $request, Quote $quote)
    {
        try {
            $this->service->update($quote, $request->safe()->except('items'), $request->items);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }
        return redirect()->route('admin.quotes.show', $quote)->with('success', 'Cotización actualizada.');
    }

    public function send(Quote $quote)
    {
        return $this->transition(fn() => $this->service->send($quote), $quote, 'Cotización enviada.');
    }

    public function accept(Quote $quote)
    {
        return $this->transition(fn() => $this->service->accept($quote), $quote, 'Cotización aceptada.');
    }

    public function reject(Quote $quote)
    {
        return $this->transition(fn() => $this->service->reject($quote), $quote, 'Cotización rechazada.');
    }

    public function cancel(Quote $quote)
    {
        return $this->transition(fn() => $this->service->cancel($quote), $quote, 'Cotización cancelada.');
    }

    public function convert(Request $request, Quote $quote)
    {
        $data = $request->validate([
            'cash_shift_id'  => ['required', 'exists:cash_shifts,id'],
            'payment_method' => ['required', 'in:cash,card,transfer,mixed'],
            'amount_paid'    => ['required', 'numeric', 'min:0'],
        ]);

        $shift = CashShift::findOrFail($data['cash_shift_id']);

        try {
            $sale = $this->service->convertToSale($quote, $shift, $data);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return redirect()->route('admin.sales.show', $sale)
            ->with('success', 'Cotización convertida en venta.');
    }

    private function transition(callable $action, Quote $quote, string $message)
    {
        try {
            $action();
        } catch (\RuntimeException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }
        return redirect()->route('admin.quotes.show', $quote)->with('success', $message);
    }
}
