<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            // Relaciones laborales: llamadas de atención, sanciones, reconocimientos, quejas.
            $table->enum('type', ['warning', 'suspension', 'memo', 'recognition', 'complaint', 'other'])->default('warning');
            $table->enum('severity', ['low', 'medium', 'high'])->default('low');
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('date');
            $table->string('action_taken')->nullable();
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_incidents');
    }
};
