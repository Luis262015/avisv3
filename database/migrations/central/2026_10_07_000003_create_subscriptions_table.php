<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->index();
            $table->string('billing_cycle', 10)->default('monthly');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_start')->nullable();
            // null = no vence (plan gratuito).
            $table->timestamp('current_period_end')->nullable()->index();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('subscription_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->string('billing_cycle', 10);
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('BOB');
            $table->string('method', 20);
            $table->string('status', 20)->default('pending')->index();
            $table->string('reference', 120)->nullable();
            $table->string('proof_path')->nullable();
            $table->text('notes')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->string('gateway', 40)->nullable();
            $table->string('gateway_reference', 191)->nullable()->index();
            $table->json('gateway_payload')->nullable();
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
        Schema::dropIfExists('subscriptions');
    }
};
