<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('tax_id', 20)->nullable()->after('rfc');
            $table->string('payment_terms', 100)->nullable()->after('tax_id');
            $table->unsignedSmallInteger('lead_time_days')->nullable()->after('payment_terms');
            $table->string('website')->nullable()->after('lead_time_days');
            $table->string('bank_account', 100)->nullable()->after('website');
            $table->decimal('avg_rating', 3, 2)->nullable()->after('bank_account');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn(['tax_id', 'payment_terms', 'lead_time_days', 'website', 'bank_account', 'avg_rating']);
        });
    }
};
