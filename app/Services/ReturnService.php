<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleReturn;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReturnService
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function create(Sale $sale, array $data, array $items): SaleReturn
    {
        return DB::transaction(function () use ($sale, $data, $items) {
            $sale->load('items');
            $this->validateQuantities($sale, $items);

            $lines    = [];
            $refund   = 0;
            $soldById = $sale->items->keyBy('id');

            foreach ($items as $item) {
                if (($item['quantity'] ?? 0) <= 0) {
                    continue;
                }
                $saleItem  = isset($item['sale_item_id']) ? $soldById->get($item['sale_item_id']) : null;
                $unitPrice = $saleItem ? (float) $saleItem->price : (float) ($item['unit_price'] ?? 0);
                $subtotal  = $unitPrice * (float) $item['quantity'];
                $refund   += $subtotal;

                $lines[] = [
                    'sale_item_id' => $item['sale_item_id'] ?? null,
                    'product_id'   => $item['product_id'],
                    'quantity'     => $item['quantity'],
                    'unit_price'   => $unitPrice,
                    'subtotal'     => $subtotal,
                ];
            }

            if (empty($lines)) {
                throw ValidationException::withMessages([
                    'items' => 'Debes indicar al menos un producto a devolver.',
                ]);
            }

            $return = SaleReturn::create([
                'sale_id'       => $sale->id,
                'customer_id'   => $sale->customer_id,
                'user_id'       => Auth::id(),
                'folio'         => SaleReturn::nextFolio(),
                'date'          => $data['date'] ?? now()->toDateString(),
                'reason'        => $data['reason'] ?? null,
                'refund_method' => $data['refund_method'] ?? 'cash',
                'refund_amount' => round($refund, 2),
                'status'        => 'pending',
                'restock'       => $data['restock'] ?? true,
                'notes'         => $data['notes'] ?? null,
            ]);

            foreach ($lines as $line) {
                $return->items()->create($line);
            }

            return $return;
        });
    }

    public function approve(SaleReturn $return): SaleReturn
    {
        if ($return->status !== 'pending') {
            throw new \RuntimeException('Solo devoluciones pendientes pueden aprobarse.');
        }
        $return->update(['status' => 'approved']);
        return $return;
    }

    public function reject(SaleReturn $return): SaleReturn
    {
        if (in_array($return->status, ['completed', 'rejected'])) {
            throw new \RuntimeException('La devolución no puede rechazarse en su estado actual.');
        }
        $return->update(['status' => 'rejected']);
        return $return;
    }

    public function complete(SaleReturn $return): SaleReturn
    {
        if (! in_array($return->status, ['pending', 'approved'])) {
            throw new \RuntimeException('La devolución no puede completarse en su estado actual.');
        }

        return DB::transaction(function () use ($return) {
            $return->load(['items.product', 'sale.cashShift.cashRegister']);

            if ($return->restock) {
                $storeId = $return->sale->cashShift->cashRegister->store_id ?? null;

                foreach ($return->items as $item) {
                    $product = $item->product;
                    if ($product && $product->track_inventory) {
                        $this->inventory->recordMovement(
                            $product,
                            'return',
                            (float) $item->quantity,
                            $return,
                            "Devolución #{$return->folio}",
                            $storeId
                        );
                    }
                }
            }

            $return->update(['status' => 'completed']);

            return $return;
        });
    }

    private function validateQuantities(Sale $sale, array $items): void
    {
        $soldById = $sale->items->keyBy('id');

        foreach ($items as $item) {
            if (($item['quantity'] ?? 0) <= 0) {
                continue;
            }
            if (! empty($item['sale_item_id'])) {
                $saleItem = $soldById->get($item['sale_item_id']);
                if ($saleItem && $item['quantity'] > (float) $saleItem->quantity) {
                    $product = Product::find($saleItem->product_id);
                    throw ValidationException::withMessages([
                        'items' => "La cantidad a devolver de \"{$product?->name}\" supera lo vendido ({$saleItem->quantity}).",
                    ]);
                }
            }
        }
    }
}
