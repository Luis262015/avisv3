<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Purchase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PurchaseService
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function create(array $data, array $items): Purchase
    {
        return DB::transaction(function () use ($data, $items) {
            $subtotal = collect($items)->sum(fn($i) => $i['quantity'] * $i['cost']);
            $tax      = $data['tax'] ?? 0;

            $purchase = Purchase::create([
                'supplier_id' => $data['supplier_id'] ?? null,
                'store_id'    => $data['store_id'] ?? null,
                'user_id'     => Auth::id(),
                'folio'       => $this->generateFolio(),
                'date'        => $data['date'],
                'subtotal'    => $subtotal,
                'tax'         => $tax,
                'total'       => $subtotal + $tax,
                'status'      => 'pending',
                'notes'       => $data['notes'] ?? null,
            ]);

            foreach ($items as $item) {
                $purchase->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity'   => $item['quantity'],
                    'cost'       => $item['cost'],
                    'subtotal'   => $item['quantity'] * $item['cost'],
                ]);
            }

            return $purchase;
        });
    }

    public function update(Purchase $purchase, array $data, array $items): Purchase
    {
        return DB::transaction(function () use ($purchase, $data, $items) {
            $purchase->load('items.product');

            if ($purchase->status === 'received') {
                $storeId = $purchase->store_id;
                foreach ($purchase->items as $oldItem) {
                    $this->inventory->recordMovement(
                        $oldItem->product,
                        'out',
                        $oldItem->quantity,
                        $purchase,
                        "Corrección compra #{$purchase->folio}",
                        $storeId
                    );
                }
            }

            $purchase->items()->delete();
            $subtotal = 0;

            foreach ($items as $item) {
                $lineSubtotal = $item['quantity'] * $item['cost'];
                $subtotal += $lineSubtotal;

                $purchase->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity'   => $item['quantity'],
                    'cost'       => $item['cost'],
                    'subtotal'   => $lineSubtotal,
                ]);

                if ($purchase->status === 'received') {
                    $product = Product::find($item['product_id']);
                    $this->inventory->recordMovement(
                        $product,
                        'in',
                        $item['quantity'],
                        $purchase,
                        "Corrección compra #{$purchase->folio}",
                        $purchase->store_id
                    );
                    $product->update(['cost' => $item['cost']]);
                }
            }

            $tax = $data['tax'] ?? 0;
            $purchase->update([
                'supplier_id' => $data['supplier_id'] ?? null,
                'store_id'    => $data['store_id'] ?? null,
                'date'        => $data['date'],
                'subtotal'    => $subtotal,
                'tax'         => $tax,
                'total'       => $subtotal + $tax,
                'notes'       => $data['notes'] ?? null,
            ]);

            return $purchase;
        });
    }

    public function receive(Purchase $purchase): Purchase
    {
        return DB::transaction(function () use ($purchase) {
            $purchase->load('items.product');
            $storeId = $purchase->store_id;

            foreach ($purchase->items as $item) {
                $this->inventory->recordMovement(
                    $item->product,
                    'in',
                    $item->quantity,
                    $purchase,
                    "Recepción de compra #{$purchase->folio}",
                    $storeId
                );

                $item->product->update(['cost' => $item->cost]);
            }

            $purchase->update(['status' => 'received']);

            return $purchase;
        });
    }

    public function cancel(Purchase $purchase): Purchase
    {
        return DB::transaction(function () use ($purchase) {
            if ($purchase->status === 'received') {
                $purchase->load('items.product');
                $storeId = $purchase->store_id;

                foreach ($purchase->items as $item) {
                    $this->inventory->recordMovement(
                        $item->product,
                        'out',
                        $item->quantity,
                        $purchase,
                        "Cancelación de compra #{$purchase->folio}",
                        $storeId
                    );
                }
            }

            $purchase->update(['status' => 'cancelled']);
            return $purchase;
        });
    }

    private function generateFolio(): string
    {
        $last = Purchase::max('id') ?? 0;
        return 'C-' . str_pad($last + 1, 6, '0', STR_PAD_LEFT);
    }
}
