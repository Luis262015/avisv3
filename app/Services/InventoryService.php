<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\StoreStock;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Todo movimiento de existencias, siempre atado a una tienda.
 *
 * **Por qué la tienda es obligatoria:** las existencias reales viven en
 * `store_product_stocks`; `products.stock` es solo el total denormalizado, que se
 * recalcula aquí como la suma de todas las tiendas. Antes existía una ruta que
 * escribía ese total directamente cuando no se indicaba tienda, y bastaba con
 * usarla una vez para que el total dejara de cuadrar con sus partes sin que nada
 * lo avisara. Al exigir la tienda, ese estado deja de ser representable.
 */
class InventoryService
{
    /**
     * La tienda va en segundo lugar, y no al final como antes, porque un
     * parámetro obligatorio no puede seguir a los opcionales: el orden delata
     * que ya no es un extra.
     */
    public function recordMovement(
        Product $product,
        int $storeId,
        string $type,
        float $quantity,
        ?Model $reference = null,
        ?string $reason = null,
    ): InventoryMovement {
        return DB::transaction(function () use ($product, $type, $quantity, $reference, $reason, $storeId) {
            $storeStock = StoreStock::firstOrCreate(
                ['store_id' => $storeId, 'product_id' => $product->id],
                ['stock' => 0]
            );

            $stockBefore = $storeStock->stock;
            $stockAfter  = $this->calculateStockAfter($stockBefore, $type, $quantity);

            $storeStock->update(['stock' => $stockAfter]);

            // El total del producto es la suma de sus tiendas, nunca un valor propio.
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
        });
    }

    /** Deja las existencias de una tienda en `$newStock`, sea subiendo o bajando. */
    public function adjust(Product $product, int $newStock, string $reason, int $storeId): InventoryMovement
    {
        $actual = (int) (StoreStock::where('store_id', $storeId)
            ->where('product_id', $product->id)
            ->value('stock') ?? 0);

        return $this->recordMovement(
            product: $product,
            storeId: $storeId,
            type: 'adjustment',
            quantity: $newStock - $actual,
            reason: $reason,
        );
    }

    /** Fija el mínimo propio de una tienda; `null` devuelve el producto a su mínimo global. */
    public function setMinimoTienda(Product $product, int $storeId, ?int $minStock): StoreStock
    {
        $storeStock = StoreStock::firstOrCreate(
            ['store_id' => $storeId, 'product_id' => $product->id],
            ['stock' => 0]
        );

        $storeStock->update(['min_stock' => $minStock]);

        return $storeStock;
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
