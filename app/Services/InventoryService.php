<?php

namespace App\Services;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\StoreStock;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class InventoryService
{
    public function recordMovement(
        Product $product,
        string $type,
        float $quantity,
        ?Model $reference = null,
        ?string $reason = null,
        ?int $storeId = null
    ): InventoryMovement {
        if ($storeId !== null) {
            return $this->recordStoreMovement($product, $type, $quantity, $reference, $reason, $storeId);
        }

        // Legacy path: update global stock directly
        $stockBefore = $product->stock;
        $stockAfter  = $this->calculateStockAfter($stockBefore, $type, $quantity);

        $product->update(['stock' => $stockAfter]);

        return InventoryMovement::create([
            'product_id'     => $product->id,
            'user_id'        => Auth::id(),
            'type'           => $type,
            'quantity'       => abs($quantity),
            'stock_before'   => $stockBefore,
            'stock_after'    => $stockAfter,
            'reference_id'   => $reference?->id,
            'reference_type' => $reference ? get_class($reference) : null,
            'reason'         => $reason,
        ]);
    }

    public function adjust(Product $product, int $newStock, string $reason, ?int $storeId = null): InventoryMovement
    {
        if ($storeId !== null) {
            $storeStock = StoreStock::firstOrCreate(
                ['store_id' => $storeId, 'product_id' => $product->id],
                ['stock' => 0]
            );
            $difference = $newStock - $storeStock->stock;
            return $this->recordMovement($product, 'adjustment', $difference, null, $reason, $storeId);
        }

        $difference = $newStock - $product->stock;
        return $this->recordMovement($product, 'adjustment', $difference, null, $reason);
    }

    private function recordStoreMovement(
        Product $product,
        string $type,
        float $quantity,
        ?Model $reference,
        ?string $reason,
        int $storeId
    ): InventoryMovement {
        $storeStock = StoreStock::firstOrCreate(
            ['store_id' => $storeId, 'product_id' => $product->id],
            ['stock' => 0]
        );

        $stockBefore = $storeStock->stock;
        $stockAfter  = $this->calculateStockAfter($stockBefore, $type, $quantity);

        $storeStock->update(['stock' => $stockAfter]);

        // Sync global stock as sum of all store stocks
        $product->update([
            'stock' => StoreStock::where('product_id', $product->id)->sum('stock'),
        ]);

        return InventoryMovement::create([
            'product_id'     => $product->id,
            'store_id'       => $storeId,
            'user_id'        => Auth::id(),
            'type'           => $type,
            'quantity'       => abs($quantity),
            'stock_before'   => $stockBefore,
            'stock_after'    => $stockAfter,
            'reference_id'   => $reference?->id,
            'reference_type' => $reference ? get_class($reference) : null,
            'reason'         => $reason,
        ]);
    }

    private function calculateStockAfter(int|float $before, string $type, float $quantity): int
    {
        return (int) match ($type) {
            'in', 'return', 'transfer_in' => $before + $quantity,
            'out', 'transfer_out'         => $before - $quantity,
            default                       => $before + $quantity, // adjustment: quantity may be negative
        };
    }
}
