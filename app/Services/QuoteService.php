<?php

namespace App\Services;

use App\Models\CashShift;
use App\Models\Quote;
use App\Models\Sale;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class QuoteService
{
    public function __construct(private readonly SaleService $sales) {}

    public function create(array $data, array $items): Quote
    {
        return DB::transaction(function () use ($data, $items) {
            [$subtotal, $lines] = $this->buildLines($items);
            $discount = $data['discount'] ?? 0;
            $tax      = $data['tax'] ?? 0;

            $quote = Quote::create([
                'customer_id' => $data['customer_id'] ?? null,
                'user_id'     => Auth::id(),
                'folio'       => Quote::nextFolio(),
                'date'        => $data['date'],
                'valid_until' => $data['valid_until'] ?? null,
                'status'      => 'draft',
                'subtotal'    => $subtotal,
                'tax'         => $tax,
                'discount'    => $discount,
                'total'       => $subtotal - $discount + $tax,
                'notes'       => $data['notes'] ?? null,
            ]);

            foreach ($lines as $line) {
                $quote->items()->create($line);
            }

            return $quote;
        });
    }

    public function update(Quote $quote, array $data, array $items): Quote
    {
        if (! $quote->isEditable()) {
            throw new \RuntimeException('La cotización no puede editarse en su estado actual.');
        }

        return DB::transaction(function () use ($quote, $data, $items) {
            $quote->items()->delete();
            [$subtotal, $lines] = $this->buildLines($items);

            foreach ($lines as $line) {
                $quote->items()->create($line);
            }

            $discount = $data['discount'] ?? 0;
            $tax      = $data['tax'] ?? 0;

            $quote->update([
                'customer_id' => $data['customer_id'] ?? null,
                'date'        => $data['date'],
                'valid_until' => $data['valid_until'] ?? null,
                'subtotal'    => $subtotal,
                'tax'         => $tax,
                'discount'    => $discount,
                'total'       => $subtotal - $discount + $tax,
                'notes'       => $data['notes'] ?? null,
            ]);

            return $quote;
        });
    }

    public function send(Quote $quote): Quote
    {
        if ($quote->status !== 'draft') {
            throw new \RuntimeException('Solo cotizaciones en borrador pueden enviarse.');
        }
        $quote->update(['status' => 'sent']);
        return $quote;
    }

    public function accept(Quote $quote): Quote
    {
        if (! in_array($quote->status, ['draft', 'sent'])) {
            throw new \RuntimeException('Solo cotizaciones en borrador o enviadas pueden aceptarse.');
        }
        $quote->update(['status' => 'accepted']);
        return $quote;
    }

    public function reject(Quote $quote): Quote
    {
        if (! in_array($quote->status, ['draft', 'sent'])) {
            throw new \RuntimeException('Solo cotizaciones en borrador o enviadas pueden rechazarse.');
        }
        $quote->update(['status' => 'rejected']);
        return $quote;
    }

    public function cancel(Quote $quote): Quote
    {
        if (in_array($quote->status, ['converted', 'cancelled'])) {
            throw new \RuntimeException('La cotización no puede cancelarse en su estado actual.');
        }
        $quote->update(['status' => 'cancelled']);
        return $quote;
    }

    public function convertToSale(Quote $quote, CashShift $shift, array $payment): Sale
    {
        if (in_array($quote->status, ['converted', 'cancelled', 'rejected'])) {
            throw new \RuntimeException('Esta cotización no puede convertirse en venta.');
        }

        return DB::transaction(function () use ($quote, $shift, $payment) {
            $quote->load('items');

            $items = $quote->items->map(fn($i) => [
                'product_id' => $i->product_id,
                'quantity'   => (float) $i->quantity,
                'price'      => (float) $i->price,
                'discount'   => (float) $i->discount,
            ])->all();

            $sale = $this->sales->create($shift, [
                'customer_id'    => $quote->customer_id,
                'discount'       => (float) $quote->discount,
                'tax'            => (float) $quote->tax,
                'amount_paid'    => $payment['amount_paid'],
                'payment_method' => $payment['payment_method'],
                'notes'          => "Generada desde cotización {$quote->folio}",
            ], $items);

            $quote->update(['status' => 'converted', 'sale_id' => $sale->id]);

            return $sale;
        });
    }

    /**
     * @return array{0: float, 1: array<int, array<string, mixed>>}
     */
    private function buildLines(array $items): array
    {
        $subtotal = 0;
        $lines    = [];

        foreach ($items as $item) {
            $lineDiscount = $item['discount'] ?? 0;
            $lineSubtotal = ($item['quantity'] * $item['price']) - $lineDiscount;
            $subtotal    += $lineSubtotal;

            $lines[] = [
                'product_id' => $item['product_id'],
                'quantity'   => $item['quantity'],
                'price'      => $item['price'],
                'discount'   => $lineDiscount,
                'subtotal'   => $lineSubtotal,
            ];
        }

        return [$subtotal, $lines];
    }
}
