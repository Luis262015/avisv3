<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('folio')->unique();
            $table->date('date');
            $table->string('reason')->nullable();
            $table->enum('refund_method', ['cash', 'card', 'transfer', 'store_credit'])->default('cash');
            $table->decimal('refund_amount', 12, 2)->default(0);
            $table->enum('status', ['pending', 'approved', 'completed', 'rejected'])->default('pending');
            $table->boolean('restock')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_returns');
    }
};
