<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_product_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->integer('stock')->default(0);
            $table->unique(['store_id', 'product_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_product_stocks');
    }
};
