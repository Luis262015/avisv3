<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseAuditLog;
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
                'supplier_id'       => $data['supplier_id'] ?? null,
                'store_id'          => $data['store_id'] ?? null,
                'user_id'           => Auth::id(),
                'purchase_order_id' => $data['purchase_order_id'] ?? null,
                'folio'             => $this->generateFolio(),
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
                'supplier_id'       => $data['supplier_id'] ?? null,
                'store_id'          => $data['store_id'] ?? null,
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
                $item->update(['received_quantity' => $item->quantity]);
            }

            $purchase->update([
                'status'      => 'received',
                'received_at' => now(),
            ]);

            $this->createPayableIfNeeded($purchase);
            $this->log($purchase, 'received', "Compra #{$purchase->folio} recibida completamente");

            return $purchase;
        });
    }

    public function receivePartial(Purchase $purchase, array $receivedItems): Purchase
    {
        return DB::transaction(function () use ($purchase, $receivedItems) {
            $purchase->load('items.product');
            $storeId   = $purchase->store_id;
            $allFilled = true;

            $receivedMap = collect($receivedItems)->keyBy('id');

            foreach ($purchase->items as $item) {
                $entry       = $receivedMap->get($item->id);
                $receivedQty = (float) ($entry['received_quantity'] ?? 0);

                if ($receivedQty <= 0) {
                    $allFilled = false;
                    continue;
                }

                $this->inventory->recordMovement(
                    $item->product,
                    'in',
                    $receivedQty,
                    $purchase,
                    "Recepción parcial compra #{$purchase->folio}",
                    $storeId
                );

                $item->product->update(['cost' => $item->cost]);

                $newReceived = (float) ($item->received_quantity ?? 0) + $receivedQty;
                $item->update(['received_quantity' => $newReceived]);

                if ($newReceived < (float) $item->quantity) {
                    $allFilled = false;
                }
            }

            $newStatus = $allFilled ? 'received' : 'partial';

            $purchase->update([
                'status'      => $newStatus,
                'received_at' => $purchase->received_at ?? now(),
            ]);

            if ($newStatus === 'received') {
                $this->createPayableIfNeeded($purchase);
            }

            $this->log($purchase, 'received_partial', "Recepción parcial registrada para compra #{$purchase->folio}");

            return $purchase;
        });
    }

    public function cancel(Purchase $purchase): Purchase
    {
        return DB::transaction(function () use ($purchase) {
            if (in_array($purchase->status, ['received', 'partial'])) {
                $purchase->load('items.product');
                $storeId = $purchase->store_id;

                foreach ($purchase->items as $item) {
                    $qty = (float) ($item->received_quantity ?? $item->quantity);
                    if ($qty > 0) {
                        $this->inventory->recordMovement(
                            $item->product,
                            'out',
                            $qty,
                            $purchase,
                            "Cancelación de compra #{$purchase->folio}",
                            $storeId
                        );
                    }
                }
            }

            $purchase->update(['status' => 'cancelled']);
            $purchase->payable?->update(['status' => 'cancelled']);
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
            'due_date'    => now()->addDays(30)->toDateString(),
            'status'      => 'pending',
        ]);
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

    private function generateFolio(): string
    {
        return Purchase::nextFolio();
    }
}
