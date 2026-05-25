<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->foreignId('store_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
        });

        // Extend enum with transfer types (MySQL only; SQLite ignores enum constraints)
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE inventory_movements MODIFY COLUMN `type` ENUM('in','out','adjustment','return','transfer_in','transfer_out') NOT NULL");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE inventory_movements MODIFY COLUMN `type` ENUM('in','out','adjustment','return') NOT NULL");
        }

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('store_id');
        });
    }
};
