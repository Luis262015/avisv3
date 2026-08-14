<?php

namespace App\Services;

use App\Models\CashShift;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Sale;
use App\Models\StoreStock;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

            $this->registrarCombos($sale, $data['combos'] ?? [], $items);

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
                        product: $product,
                        storeId: $storeId,
                        type: 'out',
                        quantity: $item['quantity'],
                        reference: $sale,
                        reason: "Venta #{$sale->folio}",
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
                        product: $oldItem->product,
                        storeId: $storeId,
                        type: 'return',
                        quantity: $oldItem->quantity,
                        reference: $sale,
                        reason: "Corrección venta #{$sale->folio}",
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
                        product: $product,
                        storeId: $storeId,
                        type: 'out',
                        quantity: $item['quantity'],
                        reference: $sale,
                        reason: "Corrección venta #{$sale->folio}",
                    );
                }
            }

            $tax        = $data['tax'] ?? 0;

            // Se conserva la promoción con la que nació la venta y se recalcula su
            // descuento contra el carrito corregido. `recalculateForCart` vuelve a
            // exigir las condiciones —la compra mínima— pero no la vigencia: la
            // promoción ya se usó, y que hoy esté vencida no puede impedir corregir
            // una venta de la semana pasada.
            if ($sale->promotion_id && ($promotion = Promotion::find($sale->promotion_id))) {
                $discount = min($this->promotions->recalculateForCart($promotion, $this->buildCart($items)), $subtotal);
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
            $sale->load(['items.product', 'siatInvoice', 'cashShift.cashRegister', 'combos']);

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
                    product: $item->product,
                    storeId: $storeId,
                    type: 'return',
                    quantity: $pending,
                    reference: $sale,
                    reason: "Cancelación de venta #{$sale->folio}",
                );
            }

            // Una venta anulada no debe seguir consumiendo el cupo de la promoción.
            if ($sale->promotion_id) {
                Promotion::find($sale->promotion_id)?->decrementUsage();
            }

            // Lo mismo para cada combo, tantas veces como se hubiera aplicado.
            foreach ($sale->combos as $combo) {
                $combo->decrementUsage((int) $combo->pivot->quantity);
            }

            $this->cancelReceivables($sale);

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
     * Anula las cuentas por cobrar de una venta anulada: si la venta ya no existe,
     * la deuda que generó tampoco. Las que tienen cobros registrados se dejan
     * intactas, porque ese dinero sí entró y debe resolverse como devolución.
     */
    private function cancelReceivables(Sale $sale): void
    {
        $sale->loadMissing('receivables');

        foreach ($sale->receivables as $receivable) {
            if (in_array($receivable->status, ['paid', 'cancelled'], true)) {
                continue;
            }

            if ((float) $receivable->amount_paid > 0) {
                Log::warning('Venta anulada con cobros ya registrados en su cuenta por cobrar', [
                    'sale_id'       => $sale->id,
                    'receivable_id' => $receivable->id,
                    'amount_paid'   => $receivable->amount_paid,
                ]);
                continue;
            }

            $receivable->update(['status' => 'cancelled', 'balance' => 0]);
        }
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
    /**
     * Deja constancia de los combos aplicados y consume su cupo.
     *
     * El punto de venta expande cada combo en líneas de producto, así que sin este
     * registro la venta no dejaba rastro de que se hubiera aplicado uno: el
     * `usage_limit` de un combo no limitaba nada porque `used_count` no subía
     * nunca. Se valida contra el carrito antes de anotar nada, porque la lista de
     * combos llega del navegador.
     *
     * @param  array<int, array{promotion_id:int|string, quantity?:int}>  $combos
     * @param  array<int, array<string, mixed>>  $items
     */
    private function registrarCombos(Sale $sale, array $combos, array $items): void
    {
        if ($combos === []) {
            return;
        }

        $cart = $this->buildCart($items);

        // Un mismo combo repetido en la petición se acumula: la tabla lo guarda
        // una sola vez con su cantidad.
        $veces = [];

        foreach ($combos as $entrada) {
            $id = (int) $entrada['promotion_id'];
            $veces[$id] = ($veces[$id] ?? 0) + max(1, (int) ($entrada['quantity'] ?? 1));
        }

        $promociones = Promotion::with('comboItems')->findMany(array_keys($veces))->keyBy('id');

        foreach ($veces as $id => $cantidad) {
            $combo = $promociones->get($id);

            if ($combo === null) {
                throw ValidationException::withMessages(['combos' => 'El combo indicado ya no existe.']);
            }

            $this->promotions->validateCombo($combo, $cart, $cantidad);

            $sale->combos()->attach($combo->id, [
                'quantity'    => $cantidad,
                // Se congela el precio: cambiarlo mañana no puede reescribir esta venta.
                'combo_price' => $combo->combo_price ?? 0,
            ]);

            $combo->incrementUsage($cantidad);
        }
    }

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
