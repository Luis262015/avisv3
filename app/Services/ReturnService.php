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
        if ($sale->status === 'cancelled') {
            throw ValidationException::withMessages([
                'sale' => 'No se puede devolver contra una venta anulada; su stock ya fue repuesto.',
            ]);
        }

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

                // Sin tienda el movimiento entra por la vía global y queda pisado
                // al recalcularse product.stock como suma de las tiendas.
                if (! $storeId) {
                    throw new \RuntimeException(
                        'La venta de origen no tiene tienda asociada; no se puede reponer el stock.'
                    );
                }

                foreach ($return->items as $item) {
                    $product = $item->product;
                    if ($product && $product->track_inventory) {
                        $this->inventory->recordMovement(
                            product: $product,
                            storeId: $storeId,
                            type: 'return',
                            quantity: (float) $item->quantity,
                            reference: $return,
                            reason: "Devolución #{$return->folio}",
                        );
                    }
                }
            }

            $return->update(['status' => 'completed']);

            return $return;
        });
    }

    /**
     * Valida contra el saldo devolvible real: lo vendido menos lo ya comprometido
     * en devoluciones anteriores. Comparar solo contra lo vendido permitía devolver
     * la misma unidad en varias devoluciones e inflar el stock.
     */
    private function validateQuantities(Sale $sale, array $items): void
    {
        $soldById       = $sale->items->keyBy('id');
        $alreadyReturned = $sale->returnedQuantitiesByItem();

        foreach ($items as $item) {
            $quantity = (float) ($item['quantity'] ?? 0);

            if ($quantity <= 0 || empty($item['sale_item_id'])) {
                continue;
            }

            $saleItem = $soldById->get($item['sale_item_id']);

            if (! $saleItem) {
                throw ValidationException::withMessages([
                    'items' => 'Una de las líneas a devolver no pertenece a esta venta.',
                ]);
            }

            $returned  = (float) ($alreadyReturned[$saleItem->id] ?? 0);
            $available = (float) $saleItem->quantity - $returned;

            if ($quantity > $available) {
                $product = Product::find($saleItem->product_id);
                $message = $returned > 0
                    ? sprintf(
                        'De "%s" quedan %s por devolver: se vendieron %s y ya hay %s en devoluciones.',
                        $product?->name,
                        $this->trim($available),
                        $this->trim((float) $saleItem->quantity),
                        $this->trim($returned)
                    )
                    : sprintf(
                        'La cantidad a devolver de "%s" supera lo vendido (%s).',
                        $product?->name,
                        $this->trim((float) $saleItem->quantity)
                    );

                throw ValidationException::withMessages(['items' => $message]);
            }
        }
    }

    private function trim(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
