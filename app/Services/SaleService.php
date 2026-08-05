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
            $storeId  = $shift->cashRegister->store_id;
            $products = $this->loadProducts($items);

            $this->validateStock($items, $products, $storeId);

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
                'folio'          => Sale::nextFolio(),
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

                $product = $products[$item['product_id']];
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
        $sale->load(['items.product', 'cashShift.cashRegister', 'siatInvoice']);

        if ($sale->status === 'cancelled') {
            throw ValidationException::withMessages([
                'status' => 'No se puede editar una venta cancelada.',
            ]);
        }

        // Una factura electrónica emitida ya fue declarada al SIAT con estos importes;
        // cambiarlos por detrás dejaría la venta y la factura contradiciéndose.
        if ($sale->siatInvoice && $sale->siatInvoice->estado !== 'anulada') {
            throw ValidationException::withMessages([
                'status' => 'Esta venta tiene una factura electrónica emitida. Anule la factura antes de editarla.',
            ]);
        }

        // El turno cerrado ya tiene su arqueo calculado; cambiar importes lo descuadra.
        if (! $sale->cashShift->isOpen()) {
            throw ValidationException::withMessages([
                'status' => 'El turno de caja de esta venta ya está cerrado. Solo puede anularse.',
            ]);
        }

        // Reescribir las líneas invalidaría las devoluciones que apuntan a ellas.
        if ($sale->returns()->whereIn('status', ['pending', 'approved', 'completed'])->exists()) {
            throw ValidationException::withMessages([
                'status' => 'Esta venta tiene devoluciones registradas y ya no puede editarse.',
            ]);
        }

        return DB::transaction(function () use ($sale, $data, $items) {
            $storeId  = $sale->cashShift->cashRegister->store_id;
            $products = $this->loadProducts($items);

            $this->validateStockForUpdate($sale, $items, $products, $storeId);

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

                $product = $products[$item['product_id']];
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

            // Lo ya repuesto por devoluciones completadas no se devuelve otra vez.
            $alreadyRestocked = $sale->restockedQuantitiesByItem();

            foreach ($sale->items as $item) {
                if (! $item->product->track_inventory) {
                    continue;
                }

                $pending = (float) $item->quantity - (float) ($alreadyRestocked[$item->id] ?? 0);

                if ($pending <= 0) {
                    continue;
                }

                $this->inventory->recordMovement(
                    $item->product,
                    'return',
                    $pending,
                    $sale,
                    "Cancelación de venta #{$sale->folio}",
                    $storeId
                );
            }

            // Una venta anulada no debe seguir consumiendo el cupo de la promoción.
            if ($sale->promotion_id) {
                Promotion::find($sale->promotion_id)?->decrementUsage();
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

    /**
     * Carga de una sola vez los productos del carrito. Antes cada línea disparaba
     * su propio Product::find dentro del bucle (N+1 en la ruta más caliente del POS).
     *
     * @return \Illuminate\Support\Collection<int, Product>
     */
    private function loadProducts(array $items): \Illuminate\Support\Collection
    {
        return Product::whereIn('id', collect($items)->pluck('product_id')->unique())
            ->get()
            ->keyBy('id');
    }

    /**
     * Suma las cantidades por producto antes de comparar: un mismo producto puede
     * venir en varias líneas del carrito y validarlas por separado dejaba pasar
     * ventas por encima del stock disponible.
     *
     * @return array<int, float>
     */
    private function quantitiesByProduct(array $items): array
    {
        $totals = [];

        foreach ($items as $item) {
            $id = (int) $item['product_id'];
            $totals[$id] = ($totals[$id] ?? 0) + (float) $item['quantity'];
        }

        return $totals;
    }

    private function validateStock(array $items, \Illuminate\Support\Collection $products, int $storeId): void
    {
        foreach ($this->quantitiesByProduct($items) as $productId => $quantity) {
            $product = $products[$productId];

            if (! $product->track_inventory) {
                continue;
            }

            $available = (float) (StoreStock::where('store_id', $storeId)
                ->where('product_id', $productId)
                ->value('stock') ?? 0);

            if ($available < $quantity) {
                throw ValidationException::withMessages([
                    'items' => "Stock insuficiente para \"{$product->name}\" en esta tienda. Disponible: {$available}",
                ]);
            }
        }
    }

    private function validateStockForUpdate(Sale $sale, array $items, \Illuminate\Support\Collection $products, int $storeId): void
    {
        $oldByProduct = $sale->items->groupBy('product_id');

        foreach ($this->quantitiesByProduct($items) as $productId => $quantity) {
            $product = $products[$productId];

            if (! $product->track_inventory) {
                continue;
            }

            // Lo que la venta actual ya había descontado vuelve a estar disponible.
            $returning = (float) ($oldByProduct->get($productId)?->sum('quantity') ?? 0);

            $storeStock = (float) (StoreStock::where('store_id', $storeId)
                ->where('product_id', $productId)
                ->value('stock') ?? 0);

            $available = $storeStock + $returning;

            if ($available < $quantity) {
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

}
