<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE promotions MODIFY COLUMN type ENUM('percentage','fixed','buy_x_get_y','combo') NOT NULL");

        Schema::table('promotions', function (Blueprint $table) {
            $table->decimal('combo_price', 12, 2)->nullable()->after('value');
        });
    }

    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->dropColumn('combo_price');
        });

        DB::statement("ALTER TABLE promotions MODIFY COLUMN type ENUM('percentage','fixed','buy_x_get_y') NOT NULL");
    }
};
