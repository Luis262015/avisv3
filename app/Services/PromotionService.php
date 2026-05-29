<?php

namespace App\Services;

use App\Models\Promotion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PromotionService
{
    public function create(array $data): Promotion
    {
        return DB::transaction(function () use ($data) {
            $promotion = Promotion::create($this->attributes($data));
            $this->syncScope($promotion, $data);
            $this->syncComboItems($promotion, $data);
            return $promotion;
        });
    }

    public function update(Promotion $promotion, array $data): Promotion
    {
        return DB::transaction(function () use ($promotion, $data) {
            $promotion->update($this->attributes($data));
            $this->syncScope($promotion, $data);
            $this->syncComboItems($promotion, $data);
            return $promotion;
        });
    }

    public function toggle(Promotion $promotion): Promotion
    {
        $promotion->update(['is_active' => ! $promotion->is_active]);
        return $promotion;
    }

    /**
     * Validate a promotion against a cart and return the discount amount.
     * Throws ValidationException when the promotion cannot be applied.
     *
     * @param array<int, array{product_id:int, category_id:int|null, quantity:float, price:float, subtotal:float}> $cart
     */
    public function validateForCart(Promotion $promotion, array $cart): float
    {
        if (! $promotion->isCurrentlyValid()) {
            throw ValidationException::withMessages([
                'promotion_id' => 'La promoción no está vigente o alcanzó su límite de uso.',
            ]);
        }

        $cartTotal = collect($cart)->sum('subtotal');

        if ($promotion->min_purchase > 0 && $cartTotal < (float) $promotion->min_purchase) {
            throw ValidationException::withMessages([
                'promotion_id' => "La compra mínima para esta promoción es de \${$promotion->min_purchase}.",
            ]);
        }

        $discount = $this->calculateDiscount($promotion, $cart);

        if ($discount <= 0) {
            throw ValidationException::withMessages([
                'promotion_id' => 'Esta promoción no aplica a los productos del carrito.',
            ]);
        }

        return round($discount, 2);
    }

    /**
     * @param array<int, array{product_id:int, category_id:int|null, quantity:float, price:float, subtotal:float}> $cart
     */
    public function calculateDiscount(Promotion $promotion, array $cart): float
    {
        $applicable = $this->applicableLines($promotion, $cart);
        $base       = array_sum(array_column($applicable, 'subtotal'));

        if ($base <= 0) {
            return 0;
        }

        return match ($promotion->type) {
            'percentage'  => $base * ((float) $promotion->value / 100),
            'fixed'       => min((float) $promotion->value, $base),
            'buy_x_get_y' => $this->buyXGetYDiscount($promotion, $applicable, $base),
            default       => 0,
        };
    }

    /**
     * @param array<int, array<string, mixed>> $cart
     * @return array<int, array<string, mixed>>
     */
    private function applicableLines(Promotion $promotion, array $cart): array
    {
        if ($promotion->scope === 'all') {
            return $cart;
        }

        if ($promotion->scope === 'product') {
            $ids = $promotion->products()->pluck('products.id')->all();
            return array_values(array_filter($cart, fn($l) => in_array($l['product_id'], $ids)));
        }

        // category scope
        $ids = $promotion->categories()->pluck('categories.id')->all();
        return array_values(array_filter($cart, fn($l) => $l['category_id'] !== null && in_array($l['category_id'], $ids)));
    }

    /**
     * @param array<int, array<string, mixed>> $applicable
     */
    private function buyXGetYDiscount(Promotion $promotion, array $applicable, float $base): float
    {
        $buy = (int) $promotion->buy_qty;
        $get = (int) $promotion->get_qty;

        if ($buy <= 0 || $get <= 0) {
            return 0;
        }

        $totalQty = array_sum(array_column($applicable, 'quantity'));
        if ($totalQty <= 0) {
            return 0;
        }

        $groupSize = $buy + $get;
        $freeUnits = floor($totalQty / $groupSize) * $get;
        $unitPrice = $base / $totalQty;

        return $freeUnits * $unitPrice;
    }

    private function attributes(array $data): array
    {
        $isCombo = $data['type'] === 'combo';

        return [
            'name'         => $data['name'],
            'code'         => $data['code'] ?? null,
            'type'         => $data['type'],
            'value'        => $data['value'] ?? 0,
            'combo_price'  => $isCombo ? ($data['combo_price'] ?? 0) : null,
            // Combos no usan alcance por producto/categoría; siempre aplican como bloque.
            'scope'        => $isCombo ? 'all' : $data['scope'],
            'min_purchase' => $data['min_purchase'] ?? 0,
            'buy_qty'      => $data['type'] === 'buy_x_get_y' ? ($data['buy_qty'] ?? null) : null,
            'get_qty'      => $data['type'] === 'buy_x_get_y' ? ($data['get_qty'] ?? null) : null,
            'starts_at'    => $data['starts_at'] ?? null,
            'ends_at'      => $data['ends_at'] ?? null,
            'usage_limit'  => $data['usage_limit'] ?? null,
            'is_active'    => $data['is_active'] ?? true,
            'notes'        => $data['notes'] ?? null,
        ];
    }

    private function syncScope(Promotion $promotion, array $data): void
    {
        if ($promotion->scope === 'product') {
            $promotion->products()->sync($data['product_ids'] ?? []);
            $promotion->categories()->detach();
        } elseif ($promotion->scope === 'category') {
            $promotion->categories()->sync($data['category_ids'] ?? []);
            $promotion->products()->detach();
        } else {
            $promotion->products()->detach();
            $promotion->categories()->detach();
        }
    }

    /**
     * Replace the products that make up a combo. Non-combo promotions never
     * carry combo items, so we clear them when the type is anything else.
     */
    private function syncComboItems(Promotion $promotion, array $data): void
    {
        $promotion->comboItems()->delete();

        if ($promotion->type !== 'combo') {
            return;
        }

        foreach ($data['combo_items'] ?? [] as $item) {
            $promotion->comboItems()->create([
                'product_id' => $item['product_id'],
                'quantity'   => $item['quantity'] ?? 1,
            ]);
        }
    }
}
