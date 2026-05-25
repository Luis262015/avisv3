<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receivables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('customer_name');
            $table->string('customer_phone')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('description');
            $table->decimal('amount', 12, 2);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->decimal('balance', 12, 2);
            $table->date('due_date');
            $table->enum('status', ['pending', 'partial', 'paid', 'cancelled'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('receivable_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receivable_id')->constrained()->cascadeOnDelete();
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
        Schema::dropIfExists('receivable_payments');
        Schema::dropIfExists('receivables');
    }
};
