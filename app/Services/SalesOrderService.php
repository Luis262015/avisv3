<?php

namespace App\Services;

use App\Models\CashShift;
use App\Models\Sale;
use App\Models\SalesOrder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SalesOrderService
{
    public function __construct(private readonly SaleService $sales) {}

    public function create(array $data, array $items): SalesOrder
    {
        return DB::transaction(function () use ($data, $items) {
            [$subtotal, $lines] = $this->buildLines($items);
            $discount = $data['discount'] ?? 0;
            $tax      = $data['tax'] ?? 0;

            $order = SalesOrder::create([
                'customer_id'      => $data['customer_id'] ?? null,
                'user_id'          => Auth::id(),
                'quote_id'         => $data['quote_id'] ?? null,
                'folio'            => SalesOrder::nextFolio(),
                'date'             => $data['date'],
                'expected_date'    => $data['expected_date'] ?? null,
                'status'           => 'pending',
                'payment_status'   => 'unpaid',
                'subtotal'         => $subtotal,
                'tax'              => $tax,
                'discount'         => $discount,
                'total'            => $subtotal - $discount + $tax,
                'shipping_address' => $data['shipping_address'] ?? null,
                'notes'            => $data['notes'] ?? null,
            ]);

            foreach ($lines as $line) {
                $order->items()->create($line);
            }

            return $order;
        });
    }

    public function update(SalesOrder $order, array $data, array $items): SalesOrder
    {
        if (! $order->isEditable()) {
            throw new \RuntimeException('El pedido no puede editarse en su estado actual.');
        }

        return DB::transaction(function () use ($order, $data, $items) {
            $order->items()->delete();
            [$subtotal, $lines] = $this->buildLines($items);

            foreach ($lines as $line) {
                $order->items()->create($line);
            }

            $discount = $data['discount'] ?? 0;
            $tax      = $data['tax'] ?? 0;

            $order->update([
                'customer_id'      => $data['customer_id'] ?? null,
                'date'             => $data['date'],
                'expected_date'    => $data['expected_date'] ?? null,
                'subtotal'         => $subtotal,
                'tax'              => $tax,
                'discount'         => $discount,
                'total'            => $subtotal - $discount + $tax,
                'shipping_address' => $data['shipping_address'] ?? null,
                'notes'            => $data['notes'] ?? null,
            ]);

            return $order;
        });
    }

    public function confirm(SalesOrder $order): SalesOrder
    {
        if ($order->status !== 'pending') {
            throw new \RuntimeException('Solo pedidos pendientes pueden confirmarse.');
        }
        $order->update(['status' => 'confirmed']);
        return $order;
    }

    public function markPreparing(SalesOrder $order): SalesOrder
    {
        if ($order->status !== 'confirmed') {
            throw new \RuntimeException('Solo pedidos confirmados pueden pasar a preparación.');
        }
        $order->update(['status' => 'preparing']);
        return $order;
    }

    public function ship(SalesOrder $order, array $shipmentData): SalesOrder
    {
        if (! in_array($order->status, ['confirmed', 'preparing'])) {
            throw new \RuntimeException('El pedido debe estar confirmado o en preparación para registrar el envío.');
        }

        return DB::transaction(function () use ($order, $shipmentData) {
            $order->shipment()->updateOrCreate([], [
                'carrier'         => $shipmentData['carrier'] ?? null,
                'tracking_number' => $shipmentData['tracking_number'] ?? null,
                'status'          => 'shipped',
                'shipped_at'      => now(),
                'address'         => $shipmentData['address'] ?? $order->shipping_address,
                'cost'            => $shipmentData['cost'] ?? 0,
                'notes'           => $shipmentData['notes'] ?? null,
            ]);

            $order->update(['status' => 'shipped']);

            return $order;
        });
    }

    public function deliver(SalesOrder $order, CashShift $shift, array $payment): Sale
    {
        if (! in_array($order->status, ['shipped', 'preparing', 'confirmed'])) {
            throw new \RuntimeException('El pedido no puede marcarse como entregado en su estado actual.');
        }

        return DB::transaction(function () use ($order, $shift, $payment) {
            $sale = $this->convertToSale($order, $shift, $payment);

            $order->update([
                'status'         => 'delivered',
                'payment_status' => 'paid',
            ]);

            if ($order->shipment) {
                $order->shipment->update(['status' => 'delivered', 'delivered_at' => now()]);
            }

            return $sale;
        });
    }

    public function cancel(SalesOrder $order): SalesOrder
    {
        if (in_array($order->status, ['delivered', 'cancelled'])) {
            throw new \RuntimeException('El pedido no puede cancelarse en su estado actual.');
        }
        $order->update(['status' => 'cancelled']);
        return $order;
    }

    public function convertToSale(SalesOrder $order, CashShift $shift, array $payment): Sale
    {
        if ($order->sale_id) {
            throw new \RuntimeException('Este pedido ya generó una venta.');
        }

        return DB::transaction(function () use ($order, $shift, $payment) {
            $order->load('items');

            $items = $order->items->map(fn($i) => [
                'product_id' => $i->product_id,
                'quantity'   => (float) $i->quantity,
                'price'      => (float) $i->price,
                'discount'   => (float) $i->discount,
            ])->all();

            $sale = $this->sales->create($shift, [
                'customer_id'    => $order->customer_id,
                'discount'       => (float) $order->discount,
                'tax'            => (float) $order->tax,
                'amount_paid'    => $payment['amount_paid'],
                'payment_method' => $payment['payment_method'],
                'notes'          => "Generada desde pedido {$order->folio}",
            ], $items);

            $order->update(['sale_id' => $sale->id]);

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
