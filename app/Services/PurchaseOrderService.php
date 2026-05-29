<?php

namespace App\Services;

use App\Models\Purchase;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PurchaseOrderService
{
    public function create(array $data, array $items): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $items) {
            $subtotal = collect($items)->sum(fn($i) => $i['quantity'] * $i['cost']);
            $tax      = $data['tax'] ?? 0;

            $order = PurchaseOrder::create([
                'supplier_id'   => $data['supplier_id'] ?? null,
                'store_id'      => $data['store_id'] ?? null,
                'user_id'       => Auth::id(),
                'folio'         => $this->generateFolio(),
                'date'          => $data['date'],
                'expected_date' => $data['expected_date'] ?? null,
                'status'        => 'draft',
                'subtotal'      => $subtotal,
                'tax'           => $tax,
                'total'         => $subtotal + $tax,
                'notes'         => $data['notes'] ?? null,
            ]);

            foreach ($items as $item) {
                $order->items()->create([
                    'product_id'        => $item['product_id'],
                    'quantity'          => $item['quantity'],
                    'quantity_received' => 0,
                    'cost'              => $item['cost'],
                    'subtotal'          => $item['quantity'] * $item['cost'],
                ]);
            }

            return $order;
        });
    }

    public function update(PurchaseOrder $order, array $data, array $items): PurchaseOrder
    {
        if (! $order->isEditable()) {
            throw new \RuntimeException('La orden no puede editarse en su estado actual.');
        }

        return DB::transaction(function () use ($order, $data, $items) {
            $existingReceived = $order->items()->pluck('quantity_received', 'product_id');
            $order->items()->delete();
            $subtotal = 0;

            foreach ($items as $item) {
                $lineSubtotal = $item['quantity'] * $item['cost'];
                $subtotal    += $lineSubtotal;

                $order->items()->create([
                    'product_id'        => $item['product_id'],
                    'quantity'          => $item['quantity'],
                    'quantity_received' => $existingReceived->get($item['product_id'], 0),
                    'cost'              => $item['cost'],
                    'subtotal'          => $lineSubtotal,
                ]);
            }

            $tax = $data['tax'] ?? 0;
            $order->update([
                'supplier_id'   => $data['supplier_id'] ?? null,
                'store_id'      => $data['store_id'] ?? null,
                'date'          => $data['date'],
                'expected_date' => $data['expected_date'] ?? null,
                'subtotal'      => $subtotal,
                'tax'           => $tax,
                'total'         => $subtotal + $tax,
                'notes'         => $data['notes'] ?? null,
            ]);

            return $order;
        });
    }

    public function confirm(PurchaseOrder $order): PurchaseOrder
    {
        if ($order->status !== 'draft') {
            throw new \RuntimeException('Solo órdenes en borrador pueden confirmarse.');
        }
        $order->update(['status' => 'confirmed']);
        return $order;
    }

    public function markSent(PurchaseOrder $order): PurchaseOrder
    {
        if ($order->status !== 'confirmed') {
            throw new \RuntimeException('Solo órdenes confirmadas pueden marcarse como enviadas.');
        }
        $order->update(['status' => 'sent']);
        return $order;
    }

    public function convertToPurchase(PurchaseOrder $order): Purchase
    {
        if (! in_array($order->status, ['confirmed', 'sent'])) {
            throw new \RuntimeException('Solo órdenes confirmadas o enviadas pueden convertirse en compra.');
        }

        return DB::transaction(function () use ($order) {
            $order->load('items');

            $purchase = Purchase::create([
                'supplier_id'       => $order->supplier_id,
                'store_id'          => $order->store_id,
                'user_id'           => Auth::id(),
                'purchase_order_id' => $order->id,
                'folio'             => Purchase::nextFolio(),
                'date'              => now()->toDateString(),
                'subtotal'          => $order->subtotal,
                'tax'               => $order->tax,
                'total'             => $order->total,
                'status'            => 'pending',
                'payment_status'    => 'unpaid',
                'notes'             => $order->notes,
            ]);

            foreach ($order->items as $item) {
                $purchase->items()->create([
                    'product_id' => $item->product_id,
                    'quantity'   => $item->quantity,
                    'cost'       => $item->cost,
                    'subtotal'   => $item->subtotal,
                ]);
            }

            $order->update(['status' => 'received']);

            return $purchase;
        });
    }

    public function cancel(PurchaseOrder $order): PurchaseOrder
    {
        if ($order->status === 'cancelled') {
            throw new \RuntimeException('La orden ya está cancelada.');
        }
        $order->update(['status' => 'cancelled']);
        return $order;
    }

    private function generateFolio(): string
    {
        return PurchaseOrder::nextFolio();
    }
}
