<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('cancellation_reason', 200)->nullable()->after('notes');
            $table->timestamp('cancelled_at')->nullable()->after('cancellation_reason');
            $table->foreignId('cancelled_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->after('cancelled_at');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['cancelled_by_user_id']);
            $table->dropColumn(['cancellation_reason', 'cancelled_at', 'cancelled_by_user_id']);
        });
    }
};
