<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->decimal('amount', 12, 2);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->decimal('balance', 12, 2);
            $table->date('due_date');
            $table->enum('status', ['pending', 'partial', 'paid', 'cancelled'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('payable_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payable_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->enum('payment_method', ['cash', 'card', 'transfer'])->default('cash');
            $table->date('date');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payable_payments');
        Schema::dropIfExists('payables');
    }
};
