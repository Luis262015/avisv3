<?php

namespace App\Services;

use App\Models\CashShift;
use App\Models\Product;
use App\Models\Promotion;
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
        private readonly PromotionService $promotions,
    ) {}

    public function create(CashShift $shift, array $data, array $items): Sale
    {
        return DB::transaction(function () use ($shift, $data, $items) {
            $shift->load('cashRegister');
            $storeId = $shift->cashRegister->store_id;

            $this->validateStock($items, $storeId);

            $subtotal = collect($items)->sum(fn($i) => $i['quantity'] * $i['price'] - ($i['discount'] ?? 0));
            $tax      = $data['tax'] ?? 0;

            [$discount, $promotionId] = $this->resolvePromotion($data, $items, $subtotal);

            $total    = $subtotal - $discount + $tax;
            $paid     = $data['amount_paid'];
            $change   = $paid - $total;

            $sale = Sale::create([
                'cash_shift_id'  => $shift->id,
                'user_id'        => Auth::id(),
                'customer_id'    => $data['customer_id'] ?? null,
                'promotion_id'   => $promotionId,
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

            if ($promotionId) {
                Promotion::find($promotionId)?->incrementUsage();
            }

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

            $tax        = $data['tax'] ?? 0;

            // Keep the originally applied promotion and recompute its discount;
            // otherwise honour the manual global discount.
            if ($sale->promotion_id && ($promotion = Promotion::find($sale->promotion_id))) {
                $discount = min($this->promotions->calculateDiscount($promotion, $this->buildCart($items)), $subtotal);
            } else {
                $discount = $data['discount'] ?? 0;
            }

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

    /**
     * Resolve the effective discount for a sale. When a valid promotion is
     * supplied, its computed discount overrides the manual global discount.
     *
     * @return array{0: float, 1: int|null}
     */
    private function resolvePromotion(array $data, array $items, float $subtotal): array
    {
        $promotionId = $data['promotion_id'] ?? null;

        if (! $promotionId) {
            return [(float) ($data['discount'] ?? 0), null];
        }

        $promotion = Promotion::find($promotionId);
        if (! $promotion) {
            return [(float) ($data['discount'] ?? 0), null];
        }

        $discount = $this->promotions->validateForCart($promotion, $this->buildCart($items));

        return [min($discount, $subtotal), $promotionId];
    }

    /**
     * @return array<int, array{product_id:int, category_id:int|null, quantity:float, price:float, subtotal:float}>
     */
    private function buildCart(array $items): array
    {
        $categories = Product::whereIn('id', collect($items)->pluck('product_id'))
            ->pluck('category_id', 'id');

        return collect($items)->map(fn($i) => [
            'product_id'  => (int) $i['product_id'],
            'category_id' => $categories[$i['product_id']] ?? null,
            'quantity'    => (float) $i['quantity'],
            'price'       => (float) $i['price'],
            'subtotal'    => (float) $i['quantity'] * (float) $i['price'] - (float) ($i['discount'] ?? 0),
        ])->all();
    }

    private function generateFolio(): string
    {
        $last = Sale::max('id') ?? 0;
        return 'V-' . str_pad($last + 1, 6, '0', STR_PAD_LEFT);
    }
}
