<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->foreignId('purchase_order_id')->nullable()->after('store_id')
                ->constrained('purchase_orders')->nullOnDelete();
            $table->string('invoice_number', 50)->nullable()->after('folio');
            $table->date('invoice_date')->nullable()->after('invoice_number');
            $table->timestamp('received_at')->nullable()->after('status');
            $table->string('payment_status', 20)->default('unpaid')->after('received_at');
            $table->string('document_path')->nullable()->after('notes');
            $table->text('audit_notes')->nullable()->after('document_path');
        });

        // Extend status enum to include 'partial'
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE purchases MODIFY COLUMN status ENUM('pending', 'partial', 'received', 'cancelled') DEFAULT 'pending'");
        }
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropForeign(['purchase_order_id']);
            $table->dropColumn([
                'purchase_order_id', 'invoice_number', 'invoice_date',
                'received_at', 'payment_status', 'document_path', 'audit_notes',
            ]);
        });
    }
};
