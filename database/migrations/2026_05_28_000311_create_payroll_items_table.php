<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();

            // ── Ganados ─────────────────────────────────────────────────────
            $table->decimal('base_salary', 12, 2)->default(0);
            $table->unsignedTinyInteger('worked_days')->default(30);
            $table->decimal('antiquity_bonus', 12, 2)->default(0); // bono de antigüedad
            $table->decimal('overtime_amount', 12, 2)->default(0);  // horas extra
            $table->decimal('other_earnings', 12, 2)->default(0);   // otros bonos
            $table->decimal('gross_salary', 12, 2)->default(0);     // total ganado

            // ── Deducciones (Bolivia) ───────────────────────────────────────
            $table->decimal('afp_deduction', 12, 2)->default(0);    // aporte laboral 12.71%
            $table->decimal('rc_iva_deduction', 12, 2)->default(0); // RC-IVA
            $table->decimal('loans_deduction', 12, 2)->default(0);  // préstamos/anticipos
            $table->decimal('other_deductions', 12, 2)->default(0);
            $table->decimal('total_deductions', 12, 2)->default(0);

            // ── Líquido pagable ─────────────────────────────────────────────
            $table->decimal('net_salary', 12, 2)->default(0);

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['payroll_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_items');
    }
};
