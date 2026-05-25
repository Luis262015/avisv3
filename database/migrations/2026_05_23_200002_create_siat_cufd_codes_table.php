<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('siat_cufd_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('codigo', 512)->comment('CUFD devuelto por SIN');
            $table->string('codigo_control', 64)->comment('Código de control del CUFD');
            $table->timestamp('fecha_vigencia')->comment('Expiración (24h desde emisión)');
            $table->unsignedInteger('consecutivo')->default(0)->comment('Contador de facturas con este CUFD');
            $table->string('estado', 20)->default('activo')->comment('activo|vencido');
            $table->timestamps();

            $table->index(['store_id', 'estado', 'fecha_vigencia']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('siat_cufd_codes');
    }
};
