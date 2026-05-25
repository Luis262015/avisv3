<?php

namespace App\Services;

use App\Models\CashShift;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StoreStock;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleService
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly SiatService $siat,
    ) {}

    public function create(CashShift $shift, array $data, array $items): Sale
    {
        return DB::transaction(function () use ($shift, $data, $items) {
            $shift->load('cashRegister');
            $storeId = $shift->cashRegister->store_id;

            $this->validateStock($items, $storeId);

            $subtotal = collect($items)->sum(fn($i) => $i['quantity'] * $i['price']);
            $discount = $data['discount'] ?? 0;
            $tax      = $data['tax'] ?? 0;
            $total    = $subtotal - $discount + $tax;
            $paid     = $data['amount_paid'];
            $change   = $paid - $total;

            $sale = Sale::create([
                'cash_shift_id'  => $shift->id,
                'user_id'        => Auth::id(),
                'folio'          => $this->generateFolio(),
                'subtotal'       => $subtotal,
                'tax'            => $tax,
                'discount'       => $discount,
                'total'          => $total,
                'amount_paid'    => $paid,
                'change_amount'  => max(0, $change),
                'payment_method' => $data['payment_method'],
                'status'         => 'completed',
                'notes'          => $data['notes'] ?? null,
            ]);

            foreach ($items as $item) {
                $sale->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity'   => $item['quantity'],
                    'price'      => $item['price'],
                    'discount'   => $item['discount'] ?? 0,
                    'subtotal'   => $item['quantity'] * $item['price'] - ($item['discount'] ?? 0),
                ]);

                $product = Product::find($item['product_id']);
                if ($product->track_inventory) {
                    $this->inventory->recordMovement(
                        $product,
                        'out',
                        $item['quantity'],
                        $sale,
                        "Venta #{$sale->folio}",
                        $storeId
                    );
                }
            }

            return $sale;
        });
    }

    public function update(Sale $sale, array $data, array $items): Sale
    {
        return DB::transaction(function () use ($sale, $data, $items) {
            $sale->load(['items.product', 'cashShift.cashRegister']);

            if ($sale->status === 'cancelled') {
                throw ValidationException::withMessages([
                    'status' => 'No se puede editar una venta cancelada.',
                ]);
            }

            $storeId = $sale->cashShift->cashRegister->store_id;

            $this->validateStockForUpdate($sale, $items, $storeId);

            foreach ($sale->items as $oldItem) {
                if ($oldItem->product->track_inventory) {
                    $this->inventory->recordMovement(
                        $oldItem->product,
                        'return',
                        $oldItem->quantity,
                        $sale,
                        "Corrección venta #{$sale->folio}",
                        $storeId
                    );
                }
            }

            $sale->items()->delete();
            $subtotal = 0;

            foreach ($items as $item) {
                $lineDiscount = $item['discount'] ?? 0;
                $lineSubtotal = ($item['quantity'] * $item['price']) - $lineDiscount;
                $subtotal += $lineSubtotal;

                $sale->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity'   => $item['quantity'],
                    'price'      => $item['price'],
                    'discount'   => $lineDiscount,
                    'subtotal'   => $lineSubtotal,
                ]);

                $product = Product::find($item['product_id']);
                if ($product->track_inventory) {
                    $this->inventory->recordMovement(
                        $product,
                        'out',
                        $item['quantity'],
                        $sale,
                        "Corrección venta #{$sale->folio}",
                        $storeId
                    );
                }
            }

            $discount   = $data['discount'] ?? 0;
            $tax        = $data['tax'] ?? 0;
            $total      = $subtotal - $discount + $tax;
            $amountPaid = $data['amount_paid'];

            $sale->update([
                'payment_method' => $data['payment_method'],
                'notes'          => $data['notes'] ?? null,
                'subtotal'       => $subtotal,
                'discount'       => $discount,
                'tax'            => $tax,
                'total'          => $total,
                'amount_paid'    => $amountPaid,
                'change_amount'  => max(0, $amountPaid - $total),
            ]);

            return $sale;
        });
    }

    public function cancel(Sale $sale, string $reason = '', ?int $cancelledBy = null): Sale
    {
        return DB::transaction(function () use ($sale, $reason, $cancelledBy) {
            $sale->load(['items.product', 'siatInvoice', 'cashShift.cashRegister']);

            if ($sale->siatInvoice && $sale->siatInvoice->estado !== 'anulada') {
                $this->siat->cancelInvoice(
                    $sale->siatInvoice,
                    $reason ?: "Cancelación de venta #{$sale->folio}"
                );
            }

            $storeId = $sale->cashShift->cashRegister->store_id;

            foreach ($sale->items as $item) {
                if ($item->product->track_inventory) {
                    $this->inventory->recordMovement(
                        $item->product,
                        'return',
                        $item->quantity,
                        $sale,
                        "Cancelación de venta #{$sale->folio}",
                        $storeId
                    );
                }
            }

            $sale->update([
                'status'               => 'cancelled',
                'cancellation_reason'  => $reason ?: null,
                'cancelled_at'         => now(),
                'cancelled_by_user_id' => $cancelledBy,
            ]);

            return $sale;
        });
    }

    private function validateStock(array $items, int $storeId): void
    {
        foreach ($items as $item) {
            $product = Product::find($item['product_id']);
            if (! $product->track_inventory) {
                continue;
            }

            $available = StoreStock::where('store_id', $storeId)
                ->where('product_id', $product->id)
                ->value('stock') ?? 0;

            if ($available < $item['quantity']) {
                throw ValidationException::withMessages([
                    'items' => "Stock insuficiente para \"{$product->name}\" en esta tienda. Disponible: {$available}",
                ]);
            }
        }
    }

    private function validateStockForUpdate(Sale $sale, array $items, int $storeId): void
    {
        $oldByProduct = $sale->items->keyBy('product_id');

        foreach ($items as $item) {
            $product = Product::find($item['product_id']);
            if (! $product?->track_inventory) {
                continue;
            }

            $returning = (float) ($oldByProduct->get($item['product_id'])?->quantity ?? 0);

            $storeStock = StoreStock::where('store_id', $storeId)
                ->where('product_id', $product->id)
                ->value('stock') ?? 0;

            $available = $storeStock + $returning;

            if ($available < $item['quantity']) {
                throw ValidationException::withMessages([
                    'items' => "Stock insuficiente para \"{$product->name}\" en esta tienda. Disponible: {$available}",
                ]);
            }
        }
    }

    private function generateFolio(): string
    {
        $last = Sale::max('id') ?? 0;
        return 'V-' . str_pad($last + 1, 6, '0', STR_PAD_LEFT);
    }
}
