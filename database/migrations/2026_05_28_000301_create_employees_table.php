<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            // Cuenta de acceso (opcional): un empleado puede o no tener login.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('employee_code')->unique();

            // ── Información personal ────────────────────────────────────────
            $table->string('first_name');
            $table->string('last_name');
            $table->enum('document_type', ['ci', 'passport', 'other'])->default('ci');
            $table->string('document_number', 30)->nullable();
            $table->date('birth_date')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->enum('marital_status', ['single', 'married', 'divorced', 'widowed', 'free_union'])->nullable();
            $table->string('nationality')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('address', 500)->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone', 30)->nullable();
            $table->string('photo_path')->nullable();

            // ── Información profesional / laboral ───────────────────────────
            $table->string('position');
            $table->date('hire_date');
            $table->date('termination_date')->nullable();
            $table->enum('contract_type', ['indefinite', 'fixed_term', 'part_time', 'intern', 'services'])->default('indefinite');
            $table->enum('status', ['active', 'on_leave', 'suspended', 'terminated'])->default('active');
            $table->decimal('base_salary', 12, 2)->default(0);

            // ── Datos para nómina (Bolivia) ─────────────────────────────────
            $table->string('bank_name')->nullable();
            $table->string('bank_account', 100)->nullable();
            $table->string('afp_name')->nullable();
            $table->string('afp_number', 60)->nullable();
            $table->string('cuns', 60)->nullable(); // Código Único Nacional de Seguridad (CNS/CUNS)

            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
