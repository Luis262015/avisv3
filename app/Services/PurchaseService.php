<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseAuditLog;
use App\Models\PurchaseOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseService
{
    /** Plazo de pago por defecto cuando el proveedor no define condiciones. */
    private const DEFAULT_PAYMENT_DAYS = 30;

    public function __construct(private readonly InventoryService $inventory) {}

    public function create(array $data, array $items): Purchase
    {
        return DB::transaction(function () use ($data, $items) {
            $subtotal = collect($items)->sum(fn($i) => $i['quantity'] * $i['cost']);
            $tax      = $data['tax'] ?? 0;

            $purchase = Purchase::create([
                'supplier_id'       => $data['supplier_id'] ?? null,
                'store_id'          => $data['store_id'],
                'user_id'           => Auth::id(),
                'purchase_order_id' => $data['purchase_order_id'] ?? null,
                'folio'             => Purchase::nextFolio(),
                'invoice_number'    => $data['invoice_number'] ?? null,
                'invoice_date'      => $data['invoice_date'] ?? null,
                'date'              => $data['date'],
                'subtotal'          => $subtotal,
                'tax'               => $tax,
                'total'             => $subtotal + $tax,
                'status'            => 'pending',
                'payment_status'    => 'unpaid',
                'notes'             => $data['notes'] ?? null,
                'audit_notes'       => $data['audit_notes'] ?? null,
            ]);

            foreach ($items as $item) {
                $purchase->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity'   => $item['quantity'],
                    'cost'       => $item['cost'],
                    'subtotal'   => $item['quantity'] * $item['cost'],
                ]);
            }

            $this->log($purchase, 'created', "Compra #{$purchase->folio} registrada");

            return $purchase;
        });
    }

    /**
     * Solo se editan compras pendientes. Una vez que hay mercadería recibida el
     * inventario, la CxP y la orden de compra ya dependen de estas líneas: se
     * corrige con una recepción parcial o con una cancelación, no reescribiendo.
     */
    public function update(Purchase $purchase, array $data, array $items): Purchase
    {
        if (! $purchase->isEditable()) {
            throw new \RuntimeException(
                'Solo pueden editarse compras pendientes. Cancele la compra para revertir el inventario.'
            );
        }

        return DB::transaction(function () use ($purchase, $data, $items) {
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
            }

            $tax = $data['tax'] ?? 0;
            $purchase->update([
                'supplier_id'       => $data['supplier_id'] ?? null,
                'store_id'          => $data['store_id'],
                'purchase_order_id' => $data['purchase_order_id'] ?? $purchase->purchase_order_id,
                'invoice_number'    => $data['invoice_number'] ?? null,
                'invoice_date'      => $data['invoice_date'] ?? null,
                'date'              => $data['date'],
                'subtotal'          => $subtotal,
                'tax'               => $tax,
                'total'             => $subtotal + $tax,
                'notes'             => $data['notes'] ?? null,
                'audit_notes'       => $data['audit_notes'] ?? $purchase->audit_notes,
            ]);

            $this->log($purchase, 'updated', "Compra #{$purchase->folio} actualizada");

            return $purchase;
        });
    }

    public function receive(Purchase $purchase): Purchase
    {
        $this->assertHasStore($purchase);

        return DB::transaction(function () use ($purchase) {
            $purchase->load('items.product');

            foreach ($purchase->items as $item) {
                $pending = $item->pendingQuantity();

                if ($pending <= 0) {
                    continue;
                }

                // Antes del movimiento: el CPP necesita las existencias previas.
                $this->applyWeightedAverageCost($item->product, $pending, (float) $item->cost);

                $this->inventory->recordMovement(
                    product: $item->product,
                    storeId: $purchase->store_id,
                    type: 'in',
                    quantity: $pending,
                    reference: $purchase,
                    reason: "Recepción de compra #{$purchase->folio}",
                );

                $item->update(['received_quantity' => $item->quantity]);
            }

            $purchase->update([
                'status'      => 'received',
                'received_at' => $purchase->received_at ?? now(),
            ]);

            $this->syncPurchaseOrder($purchase);
            $this->createPayableIfNeeded($purchase);
            $this->log($purchase, 'received', "Compra #{$purchase->folio} recibida completamente");

            return $purchase;
        });
    }

    public function receivePartial(Purchase $purchase, array $receivedItems): Purchase
    {
        $this->assertHasStore($purchase);

        return DB::transaction(function () use ($purchase, $receivedItems) {
            $purchase->load('items.product');

            $receivedMap = collect($receivedItems)->keyBy('id');
            $allFilled   = true;
            $anyReceived = false;

            foreach ($purchase->items as $item) {
                $entry       = $receivedMap->get($item->id);
                $receivedQty = (float) ($entry['received_quantity'] ?? 0);
                $pending     = $item->pendingQuantity();

                if ($receivedQty > $pending) {
                    throw ValidationException::withMessages([
                        'items' => sprintf(
                            'No se puede recibir %s de "%s": solo quedan %s pendientes.',
                            rtrim(rtrim(number_format($receivedQty, 2, '.', ''), '0'), '.'),
                            $item->product->name,
                            rtrim(rtrim(number_format($pending, 2, '.', ''), '0'), '.')
                        ),
                    ]);
                }

                if ($receivedQty <= 0) {
                    if ($pending > 0) {
                        $allFilled = false;
                    }
                    continue;
                }

                $anyReceived = true;

                $this->applyWeightedAverageCost($item->product, $receivedQty, (float) $item->cost);

                $this->inventory->recordMovement(
                    product: $item->product,
                    storeId: $purchase->store_id,
                    type: 'in',
                    quantity: $receivedQty,
                    reference: $purchase,
                    reason: "Recepción parcial compra #{$purchase->folio}",
                );

                $newReceived = (float) ($item->received_quantity ?? 0) + $receivedQty;
                $item->update(['received_quantity' => $newReceived]);

                if ($newReceived < (float) $item->quantity) {
                    $allFilled = false;
                }
            }

            if (! $anyReceived) {
                throw ValidationException::withMessages([
                    'items' => 'Debe indicar al menos una cantidad recibida mayor a cero.',
                ]);
            }

            $newStatus = $allFilled ? 'received' : 'partial';

            $purchase->update([
                'status'      => $newStatus,
                'received_at' => $purchase->received_at ?? now(),
            ]);

            $this->syncPurchaseOrder($purchase);

            if ($newStatus === 'received') {
                $this->createPayableIfNeeded($purchase);
            }

            $this->log($purchase, 'received_partial', "Recepción parcial registrada para compra #{$purchase->folio}");

            return $purchase;
        });
    }

    public function cancel(Purchase $purchase): Purchase
    {
        $purchase->loadMissing('payable');

        if ($purchase->payable && (float) $purchase->payable->amount_paid > 0) {
            throw new \RuntimeException(
                'La compra tiene pagos registrados en su cuenta por pagar. Anule primero los pagos.'
            );
        }

        return DB::transaction(function () use ($purchase) {
            if (in_array($purchase->status, ['received', 'partial'], true)) {
                $purchase->load('items.product');

                foreach ($purchase->items as $item) {
                    $qty = (float) ($item->received_quantity ?? 0);

                    if ($qty <= 0) {
                        continue;
                    }

                    $this->inventory->recordMovement(
                        product: $item->product,
                        storeId: $purchase->store_id,
                        type: 'out',
                        quantity: $qty,
                        reference: $purchase,
                        reason: "Cancelación de compra #{$purchase->folio}",
                    );

                    $item->update(['received_quantity' => 0]);
                }
            }

            $purchase->update(['status' => 'cancelled']);
            $purchase->payable?->update(['status' => 'cancelled', 'balance' => 0]);

            $this->syncPurchaseOrder($purchase);
            $this->log($purchase, 'cancelled', "Compra #{$purchase->folio} cancelada");

            return $purchase;
        });
    }

    public function attachDocument(Purchase $purchase, string $path): Purchase
    {
        $purchase->update(['document_path' => $path]);
        $this->log($purchase, 'document_attached', "Documento de factura adjuntado a compra #{$purchase->folio}");
        return $purchase;
    }

    /**
     * Costo promedio ponderado (CPP): el costo del producto se recalcula mezclando
     * las existencias actuales con lo que entra, en lugar de pisarse con el último
     * costo de compra.
     *
     *     nuevo = (stock_actual × costo_actual + cantidad × costo_compra)
     *             ÷ (stock_actual + cantidad)
     *
     * Debe invocarse ANTES de registrar el movimiento de inventario: necesita las
     * existencias previas a la entrada.
     */
    private function applyWeightedAverageCost(Product $product, float $incomingQty, float $incomingCost): void
    {
        if ($incomingQty <= 0) {
            return;
        }

        // Una misma compra puede tocar el producto en varias líneas; releer evita
        // calcular sobre existencias ya desactualizadas por la línea anterior.
        $product->refresh();

        $currentQty  = (float) $product->stock;
        $currentCost = (float) $product->cost;

        // Sin existencias previas —o con stock negativo por descuadre— promediar no
        // aporta nada: el costo de esta compra es la única referencia válida.
        $newCost = $currentQty > 0
            ? (($currentQty * $currentCost) + ($incomingQty * $incomingCost)) / ($currentQty + $incomingQty)
            : $incomingCost;

        $product->update(['cost' => round($newCost, 2)]);
    }

    /**
     * Refleja en la orden de compra lo realmente recibido a través de sus compras.
     * Sin esto, PurchaseOrder::pendingQuantityFor() siempre devolvía la cantidad
     * completa y la orden quedaba marcada como recibida sin haber llegado nada.
     */
    private function syncPurchaseOrder(Purchase $purchase): void
    {
        if (! $purchase->purchase_order_id) {
            return;
        }

        $order = PurchaseOrder::with('items')->find($purchase->purchase_order_id);

        if (! $order || $order->status === 'cancelled') {
            return;
        }

        // Suma de lo recibido por producto en todas las compras vivas de la orden.
        $receivedByProduct = DB::table('purchase_items')
            ->join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id')
            ->where('purchases.purchase_order_id', $order->id)
            ->where('purchases.status', '!=', 'cancelled')
            ->groupBy('purchase_items.product_id')
            ->select('purchase_items.product_id', DB::raw('SUM(purchase_items.received_quantity) as received'))
            ->get()
            ->pluck('received', 'product_id');

        $allFilled = true;
        $anyFilled = false;

        foreach ($order->items as $item) {
            $received = (float) ($receivedByProduct[$item->product_id] ?? 0);
            $item->update(['quantity_received' => $received]);

            if ($received < (float) $item->quantity) {
                $allFilled = false;
            }
            if ($received > 0) {
                $anyFilled = true;
            }
        }

        $order->update([
            'status' => match (true) {
                $allFilled => 'received',
                $anyFilled => 'partial',
                default    => $order->purchases()->where('status', '!=', 'cancelled')->exists()
                    ? 'partial'
                    : 'confirmed',
            },
        ]);
    }

    private function createPayableIfNeeded(Purchase $purchase): void
    {
        if ($purchase->payable()->exists()) {
            return;
        }

        $description = "Compra #{$purchase->folio}";
        if ($purchase->invoice_number) {
            $description .= " - Factura {$purchase->invoice_number}";
        }

        $purchase->payable()->create([
            'supplier_id' => $purchase->supplier_id,
            'user_id'     => Auth::id(),
            'description' => $description,
            'amount'      => $purchase->total,
            'amount_paid' => 0,
            'balance'     => $purchase->total,
            'due_date'    => $this->dueDateFor($purchase),
            'status'      => 'pending',
        ]);
    }

    /**
     * Deriva el vencimiento de las condiciones de pago del proveedor
     * (campo de texto libre, p. ej. "30 días" / "Contado").
     */
    private function dueDateFor(Purchase $purchase): string
    {
        $purchase->loadMissing('supplier');
        $terms = $purchase->supplier?->payment_terms;

        $days = self::DEFAULT_PAYMENT_DAYS;

        if ($terms !== null && preg_match('/\d+/', $terms, $match)) {
            $days = (int) $match[0];
        } elseif ($terms !== null && preg_match('/contado|inmediato/i', $terms)) {
            $days = 0;
        }

        $base = $purchase->invoice_date ?? $purchase->received_at ?? now();

        return Carbon::parse($base)->addDays($days)->toDateString();
    }

    private function assertHasStore(Purchase $purchase): void
    {
        if (! $purchase->store_id) {
            throw new \RuntimeException(
                'La compra no tiene tienda asignada. Asigne una tienda antes de recibir la mercadería.'
            );
        }
    }

    private function log(Purchase $purchase, string $action, string $description, array $metadata = []): void
    {
        PurchaseAuditLog::create([
            'purchase_id' => $purchase->id,
            'user_id'     => Auth::id(),
            'action'      => $action,
            'description' => $description,
            'metadata'    => $metadata ?: null,
        ]);
    }
}
