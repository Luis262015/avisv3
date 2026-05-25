<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\StoreStock;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockTransferService
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function create(array $data, array $items): StockTransfer
    {
        return DB::transaction(function () use ($data, $items) {
            $transfer = StockTransfer::create([
                'folio'         => $this->generateFolio(),
                'from_store_id' => $data['from_store_id'],
                'to_store_id'   => $data['to_store_id'],
                'user_id'       => Auth::id(),
                'status'        => 'pending',
                'notes'         => $data['notes'] ?? null,
            ]);

            foreach ($items as $item) {
                $transfer->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity'   => $item['quantity'],
                ]);
            }

            return $transfer;
        });
    }

    public function complete(StockTransfer $transfer): StockTransfer
    {
        if ($transfer->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => 'Solo se pueden completar transferencias pendientes.',
            ]);
        }

        return DB::transaction(function () use ($transfer) {
            $transfer->load(['items.product', 'fromStore', 'toStore']);

            // Validate source stock for all items before moving anything
            foreach ($transfer->items as $item) {
                if (! $item->product->track_inventory) {
                    continue;
                }

                $sourceStock = StoreStock::where('store_id', $transfer->from_store_id)
                    ->where('product_id', $item->product_id)
                    ->value('stock') ?? 0;

                if ($sourceStock < $item->quantity) {
                    throw ValidationException::withMessages([
                        'items' => "Stock insuficiente de \"{$item->product->name}\" en {$transfer->fromStore->name}. Disponible: {$sourceStock}",
                    ]);
                }
            }

            foreach ($transfer->items as $item) {
                $product = $item->product;

                $this->inventory->recordMovement(
                    $product,
                    'transfer_out',
                    $item->quantity,
                    $transfer,
                    "Transferencia #{$transfer->folio} → {$transfer->toStore->name}",
                    $transfer->from_store_id
                );

                $this->inventory->recordMovement(
                    $product,
                    'transfer_in',
                    $item->quantity,
                    $transfer,
                    "Transferencia #{$transfer->folio} ← {$transfer->fromStore->name}",
                    $transfer->to_store_id
                );
            }

            $transfer->update([
                'status'       => 'completed',
                'completed_at' => now(),
            ]);

            return $transfer;
        });
    }

    public function cancel(StockTransfer $transfer): StockTransfer
    {
        if ($transfer->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => 'Solo se pueden cancelar transferencias pendientes.',
            ]);
        }

        $transfer->update([
            'status'       => 'cancelled',
            'cancelled_at' => now(),
        ]);

        return $transfer;
    }

    private function generateFolio(): string
    {
        $last = StockTransfer::max('id') ?? 0;
        return 'TR-' . str_pad($last + 1, 5, '0', STR_PAD_LEFT);
    }
}
