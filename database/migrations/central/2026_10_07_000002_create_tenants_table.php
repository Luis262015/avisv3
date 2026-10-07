<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            // Subdominio: {slug}.dominio-base
            $table->string('slug', 40)->unique();
            // Dominio propio opcional, además del subdominio.
            $table->string('domain')->nullable()->unique();
            $table->string('database', 64)->unique();
            // La empresa que ya existía antes del arrendamiento: comparte la
            // base central y sus archivos siguen en la raíz del disco.
            $table->boolean('is_legacy')->default(false);
            $table->string('nit', 20)->nullable();
            $table->string('owner_name');
            $table->string('owner_email');
            $table->string('phone', 30)->nullable();
            $table->string('city', 80)->nullable();
            $table->string('status', 20)->default('provisioning')->index();
            $table->string('suspension_reason')->nullable();
            $table->text('notes')->nullable();
            // Entrada única tras el alta: se guarda solo el hash.
            $table->string('access_token', 64)->nullable();
            $table->timestamp('access_token_expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
