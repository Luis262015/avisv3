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
        // Un combo no es un descuento sobre el carrito: se agrega expandido en sus
        // productos. Sin este aviso el error que llegaba era «no aplica a los
        // productos del carrito», que manda a buscar el problema donde no está.
        if ($promotion->type === 'combo') {
            throw ValidationException::withMessages([
                'promotion_id' => 'Los combos se agregan al carrito como productos, no se seleccionan como promoción.',
            ]);
        }

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
     * Comprueba que un combo pueda aplicarse `$veces` a este carrito.
     *
     * No basta con que el punto de venta diga que se aplicó: el carrito llega del
     * navegador y hay que verificar que **contiene de verdad** los productos del
     * combo en las cantidades que exige. Si no, se estaría consumiendo el cupo de
     * un combo que el cliente no se llevó.
     *
     * @param array<int, array{product_id:int, quantity:float, subtotal:float}> $cart
     */
    public function validateCombo(Promotion $promotion, array $cart, int $veces): void
    {
        if ($promotion->type !== 'combo') {
            throw ValidationException::withMessages([
                'combos' => "«{$promotion->name}» no es un combo.",
            ]);
        }

        if ($veces < 1) {
            throw ValidationException::withMessages([
                'combos' => "Cantidad inválida para el combo «{$promotion->name}».",
            ]);
        }

        if (! $promotion->isCurrentlyValid()) {
            throw ValidationException::withMessages([
                'combos' => "El combo «{$promotion->name}» no está vigente o alcanzó su límite de uso.",
            ]);
        }

        $disponibles = $promotion->usosDisponibles();

        if ($disponibles !== null && $veces > $disponibles) {
            throw ValidationException::withMessages([
                'combos' => "Del combo «{$promotion->name}» solo quedan {$disponibles} usos.",
            ]);
        }

        $enCarrito = [];

        foreach ($cart as $linea) {
            $id = (int) $linea['product_id'];
            $enCarrito[$id] = ($enCarrito[$id] ?? 0) + (float) $linea['quantity'];
        }

        foreach ($promotion->comboItems as $item) {
            $necesarias = (float) $item->quantity * $veces;

            if (($enCarrito[$item->product_id] ?? 0) + 1e-6 < $necesarias) {
                throw ValidationException::withMessages([
                    'combos' => "El carrito no contiene los productos del combo «{$promotion->name}».",
                ]);
            }
        }
    }

    /**
     * Recalcula el descuento de una promoción **ya aplicada** a una venta que se
     * está corrigiendo.
     *
     * A diferencia de {@see validateForCart()} no comprueba vigencia ni cupo: la
     * promoción se aplicó cuando tocaba, y que hoy esté vencida no puede impedir
     * corregir una venta antigua. Lo que sí se vuelve a exigir son las condiciones
     * del carrito —la compra mínima—, porque dejaron de cumplirse ahora mismo: sin
     * esto, bajar la venta de 600 a 100 conservaba el descuento de una promoción
     * que pedía 500 de mínimo.
     *
     * Devuelve 0 en vez de fallar: editar la venta debe poder completarse, solo
     * que sin el descuento.
     *
     * @param array<int, array{product_id:int, category_id:int|null, quantity:float, price:float, subtotal:float}> $cart
     */
    public function recalculateForCart(Promotion $promotion, array $cart): float
    {
        $cartTotal = collect($cart)->sum('subtotal');

        if ($promotion->min_purchase > 0 && $cartTotal < (float) $promotion->min_purchase) {
            return 0.0;
        }

        return round($this->calculateDiscount($promotion, $cart), 2);
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
            'buy_x_get_y' => $this->buyXGetYDiscount($promotion, $applicable),
            // Los combos no descuentan sobre el carrito: se agregan como líneas de
            // producto al precio del combo. Ver validateForCart().
            'combo'       => 0,
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
    /**
     * Descuento de un «lleve X pague Y».
     *
     * Lo que se regala son las unidades **más baratas** del carrito, que es como
     * se entiende un 2x1 en la tienda y lo que el cliente espera. Antes se
     * repartía el precio medio, de modo que en un carrito con un monitor de 900 y
     * un ratón de 100 el 2x1 descontaba 500 en vez de 100: cuatrocientos bolivianos
     * regalados por línea de más, y peor cuanto más dispares los precios.
     *
     * @param array<int, array<string, mixed>> $applicable
     */
    private function buyXGetYDiscount(Promotion $promotion, array $applicable): float
    {
        $buy = (int) $promotion->buy_qty;
        $get = (int) $promotion->get_qty;

        if ($buy <= 0 || $get <= 0) {
            return 0;
        }

        // Se despieza el carrito en unidades sueltas con su precio real, que sale
        // del subtotal de la línea para que un descuento manual ya aplicado no se
        // regale dos veces.
        $unidades = [];

        foreach ($applicable as $linea) {
            $cantidad = (int) floor((float) $linea['quantity']);

            if ($cantidad <= 0 || (float) $linea['quantity'] <= 0) {
                continue;
            }

            $precioUnitario = (float) $linea['subtotal'] / (float) $linea['quantity'];

            for ($i = 0; $i < $cantidad; $i++) {
                $unidades[] = $precioUnitario;
            }
        }

        $totalQty = count($unidades);

        if ($totalQty <= 0) {
            return 0;
        }

        $gratis = (int) (intdiv($totalQty, $buy + $get) * $get);

        if ($gratis <= 0) {
            return 0;
        }

        sort($unidades);

        return (float) array_sum(array_slice($unidades, 0, $gratis));
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
