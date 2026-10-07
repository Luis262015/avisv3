<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 60)->unique();
            $table->string('name', 80);
            $table->string('tagline')->nullable();
            $table->decimal('price_monthly', 10, 2)->default(0);
            $table->decimal('price_yearly', 10, 2)->default(0);
            $table->string('currency', 3)->default('BOB');
            $table->unsignedSmallInteger('trial_days')->default(0);
            // null = sin límite.
            $table->unsignedInteger('max_stores')->nullable();
            $table->unsignedInteger('max_users')->nullable();
            $table->unsignedInteger('max_products')->nullable();
            $table->unsignedInteger('max_invoices_month')->nullable();
            // Claves de config('plans.modules') que el plan incluye.
            $table->json('modules');
            $table->boolean('is_public')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
