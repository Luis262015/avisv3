<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Qué combos se aplicaron en cada venta.
 *
 * Hacía falta una tabla aparte porque `sales.promotion_id` es una sola columna
 * —sirve para la promoción de descuento— y una venta puede llevar varios combos,
 * incluso el mismo repetido. Sin este registro, el punto de venta expandía el
 * combo en líneas de producto y el sistema no se enteraba: `used_count` nunca
 * subía y el `usage_limit` de un combo no limitaba nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('combo_sale', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1)
                ->comment('Cuántas veces se aplicó el mismo combo en esta venta');
            $table->decimal('combo_price', 12, 2)
                ->comment('Precio del combo al momento de la venta; cambiarlo después no reescribe la historia');
            $table->timestamps();

            $table->unique(['sale_id', 'promotion_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('combo_sale');
    }
};
